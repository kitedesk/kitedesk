<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Ai\Agents\TicketClassifier;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Ai\Support\TicketContext;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

/**
 * Asks the AI assistant to classify the ticket: category, priority, type, tags, sentiment,
 * language and custom intents, each limited to valid choices and given a confidence.
 *
 * Fields with "apply" switched on are set on the ticket when the confidence reaches the
 * threshold (setting the category re-runs routing). Everything is saved as
 * `{{vars.<save_as>.<field>}}` for later steps. Outputs: "confident" when every chosen field
 * reached the threshold, "unsure" when some didn't (those aren't applied), "failed" when the
 * assistant is off or the provider fails. Test runs ask the model but change nothing.
 */
class AiClassifyNode extends TicketActionNode
{
    /**
     * Every field the step can infer, and whether it can be applied to the ticket.
     */
    public const array FIELDS = [
        'category' => true,
        'priority' => true,
        'type' => true,
        'tags' => true,
        'sentiment' => false,
        'language' => false,
        'intent' => false,
    ];

    public const array SENTIMENTS = ['positive', 'neutral', 'negative', 'angry'];

    /**
     * Ticket context sent for classification: the opening messages carry the intent.
     */
    private const int CONTEXT_MESSAGES = 10;

    public function __construct(private UpdateTicket $updateTicket) {}

    public function type(): string
    {
        return 'ai_classify';
    }

    public function outputs(array $data): array
    {
        return ['confident', 'unsure', 'failed'];
    }

    protected function actionRules(): array
    {
        return [
            'fields' => ['required', 'array:'.implode(',', array_keys(self::FIELDS))],
            'fields.*.enabled' => ['sometimes', 'boolean'],
            'fields.*.apply' => ['sometimes', 'boolean'],
            'fields.tags.options' => ['required_if_accepted:fields.tags.enabled', 'array', 'max:50'],
            'fields.tags.options.*' => ['string', 'max:50'],
            'fields.intent.options' => ['required_if_accepted:fields.intent.enabled', 'array', 'max:30'],
            'fields.intent.options.*' => ['string', 'max:60'],
            'keep_existing' => ['sometimes', 'boolean'],
            'min_confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'save_as' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/i'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $settings = AiSettings::current();

        if (! $settings->isAvailable()) {
            return NodeResult::branch('failed', ['error' => __('The AI assistant is not set up.')]);
        }

        $categories = $this->categoryChoices();
        $fields = $this->fieldsToClassify($data, $categories);

        if ($fields === []) {
            return NodeResult::branch('failed', ['error' => __('Choose at least one thing to classify.')]);
        }

        try {
            $response = (new TicketClassifier($fields))->prompt(TicketContext::for($ticket, self::CONTEXT_MESSAGES));

            if (! $response instanceof StructuredAgentResponse) {
                throw new RuntimeException('The model did not return structured output.');
            }

            $answer = $response->structured;
        } catch (Throwable $exception) {
            Log::warning('AI classification failed in a workflow.', ['ticket' => $ticket->id, 'workflow' => $context->workflowId, 'exception' => $exception]);

            return NodeResult::branch('failed', ['error' => Str::limit($exception->getMessage(), 200)]);
        }

        $threshold = ((int) $data['min_confidence']) / 100;
        $results = [];
        $unsure = [];

        foreach (array_keys($fields) as $key) {
            $value = $this->validValue($answer[$key]['value'] ?? null, $fields[$key]);
            $confidence = round((float) ($answer[$key]['confidence'] ?? 0), 2);
            $results[$key] = ['value' => $value, 'confidence' => $confidence];

            // An empty tag list is a valid answer; a missing single value is not.
            if ($value === null || $confidence < $threshold) {
                $unsure[] = $key;
            }
        }

        $changes = $this->changes($data, $ticket, $results, $unsure, $categories);

        if ($changes !== [] && ! $context->simulating) {
            $this->updateTicket->handle($ticket, $changes);
        }

        $context->vars[(string) $data['save_as']] = [
            ...array_map(fn (array $result): mixed => $result['value'], $results),
            ...(isset($results['category']) ? ['category_id' => is_string($results['category']['value']) ? $categories->get($results['category']['value']) : null] : []),
            'confidence' => array_map(fn (array $result): float => $result['confidence'], $results),
            'reason' => is_string($answer['reason'] ?? null) ? $answer['reason'] : null,
        ];

        return NodeResult::branch($unsure === [] ? 'confident' : 'unsure', array_filter([
            'ticket' => $ticket->reference(),
            'results' => $results,
            'applied' => array_keys($changes),
            'unsure' => $unsure,
            'reason' => $answer['reason'] ?? null,
        ], fn (mixed $value): bool => $value !== [] && $value !== null));
    }

    /**
     * Categories the ticket can be put in, as "Parent › Child" => id. A category with active
     * subcategories is left out: tickets go in one of its subcategories.
     *
     * @return Collection<string, int>
     */
    private function categoryChoices(): Collection
    {
        return TicketCategory::query()->active()->roots()->ordered()
            ->with(['children' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->flatMap(fn (TicketCategory $category): array => $category->children->isEmpty()
                ? [$category->name => $category->id]
                : $category->children->mapWithKeys(fn (TicketCategory $child): array => [$category->name.' › '.$child->name => $child->id])->all());
    }

    /**
     * The fields switched on in the step, with their choices for the model.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<string, int>  $categories
     * @return array<string, array{description: string, choices: list<string>, multiple?: bool}>
     */
    private function fieldsToClassify(array $data, Collection $categories): array
    {
        $enabled = fn (string $key): bool => (bool) data_get($data, "fields.{$key}.enabled", false);
        $options = fn (string $key): array => array_values(array_unique(array_filter(array_map(
            fn (mixed $option): string => trim((string) $option),
            (array) data_get($data, "fields.{$key}.options", []),
        ))));
        $descriptions = TicketCategory::query()->active()->whereNotNull('description')->pluck('description', 'name');

        return array_filter([
            'category' => $enabled('category') && $categories->isNotEmpty() ? [
                'description' => 'The ticket category.'.($descriptions->isNotEmpty()
                    ? ' Category notes: '.$descriptions->map(fn (string $text, string $name): string => "{$name}: {$text}")->join('; ')
                    : ''),
                'choices' => array_values($categories->keys()->all()),
            ] : null,
            'priority' => $enabled('priority') ? [
                'description' => 'How urgent it is: urgent = service down, security issue or many people blocked; high = one person blocked or a deadline at risk; normal = needs an answer; low = questions and suggestions.',
                'choices' => array_column(TicketPriority::cases(), 'value'),
            ] : null,
            'type' => $enabled('type') ? [
                'description' => 'question = asking how to do something; incident = something is broken for them; problem = the cause behind several incidents; task = something they ask the team to do.',
                'choices' => array_column(TicketType::cases(), 'value'),
            ] : null,
            'tags' => $enabled('tags') && $options('tags') !== [] ? [
                'description' => 'Every tag that applies; none is fine.',
                'choices' => $options('tags'),
                'multiple' => true,
            ] : null,
            'sentiment' => $enabled('sentiment') ? [
                'description' => 'How the customer feels, from their latest messages.',
                'choices' => self::SENTIMENTS,
            ] : null,
            'language' => $enabled('language') ? [
                'description' => 'The language the customer writes in, as a two-letter ISO 639-1 code such as en, pt or es.',
                'choices' => [],
            ] : null,
            'intent' => $enabled('intent') && $options('intent') !== [] ? [
                'description' => 'What the customer wants.',
                'choices' => $options('intent'),
            ] : null,
        ]);
    }

    /**
     * The answer when it is one of the choices (models occasionally stray), else null.
     *
     * @param  array{choices: list<string>, multiple?: bool}  $field
     * @return string|list<string>|null
     */
    private function validValue(mixed $value, array $field): string|array|null
    {
        if ($field['multiple'] ?? false) {
            return array_values(array_intersect(is_array($value) ? $value : [], $field['choices']));
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if ($field['choices'] === []) {
            return Str::substr(Str::lower(trim($value)), 0, 10);
        }

        return in_array($value, $field['choices'], true) ? $value : null;
    }

    /**
     * What to change on the ticket: confident answers for fields with "apply" on.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{value: mixed, confidence: float}>  $results
     * @param  list<string>  $unsure
     * @param  Collection<string, int>  $categories
     * @return array<string, mixed>
     */
    private function changes(array $data, Ticket $ticket, array $results, array $unsure, Collection $categories): array
    {
        $keepExisting = (bool) ($data['keep_existing'] ?? true);
        $applies = fn (string $key): bool => isset($results[$key])
            && (self::FIELDS[$key] ?? false)
            && (bool) data_get($data, "fields.{$key}.apply", false)
            && ! in_array($key, $unsure, true);
        $changes = [];

        if ($applies('category') && ! ($keepExisting && $ticket->category_id !== null)) {
            $changes['category_id'] = $categories->get((string) $results['category']['value']);
        }

        if ($applies('priority') && $results['priority']['value'] !== $ticket->priority->value) {
            $changes['priority'] = $results['priority']['value'];
        }

        if ($applies('type') && ! ($keepExisting && $ticket->type !== null)) {
            $changes['type'] = $results['type']['value'];
        }

        if ($applies('tags')) {
            $current = $ticket->tags()->pluck('name')->all();
            $tags = array_values(array_unique([...$current, ...array_map(
                fn (string $tag): string => Str::of($tag)->trim()->lower()->replaceMatches('/\s+/', '_')->toString(),
                (array) $results['tags']['value'],
            )]));

            if ($tags !== $current) {
                $changes['tags'] = $tags;
            }
        }

        return array_filter($changes, fn (mixed $value): bool => $value !== null);
    }
}
