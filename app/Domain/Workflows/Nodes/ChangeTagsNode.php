<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Support\Str;

/**
 * Adds or removes tags (`add_tags` / `remove_tags`), keeping the ticket's other tags.
 */
class ChangeTagsNode extends TicketActionNode
{
    public function __construct(private string $mode, private UpdateTicket $updateTicket, private Placeholders $placeholders) {}

    public function type(): string
    {
        return $this->mode === 'remove' ? 'remove_tags' : 'add_tags';
    }

    protected function actionRules(): array
    {
        return [
            'tags' => ['required', 'array', 'min:1', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $tags = array_map(
            fn (string $tag): string => Str::of($this->placeholders->text($tag, $context))->trim()->lower()->replaceMatches('/\s+/', '_')->toString(),
            $data['tags'] ?? [],
        );
        $current = $ticket->tags()->pluck('name')->all();

        $updated = $this->mode === 'remove'
            ? array_values(array_diff($current, $tags))
            : array_values(array_unique([...$current, ...array_filter($tags)]));

        if (! $context->simulating && $updated !== $current) {
            $this->updateTicket->handle($ticket, ['tags' => $updated]);
        }

        return NodeResult::next(['ticket' => $ticket->reference(), 'tags' => $tags]);
    }
}
