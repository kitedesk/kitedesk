<?php

namespace App\Domain\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Classifies a ticket for the "Classify with AI" workflow step. Every answer is limited to the
 * given choices, with a confidence the step compares to the workflow's threshold.
 */
#[MaxTokens(600)]
#[Temperature(0)]
#[Timeout(60)]
class TicketClassifier implements Agent, HasStructuredOutput
{
    use Promptable, UsesKiteDeskProvider;

    /**
     * @param  array<string, array{description: string, choices: list<string>, multiple?: bool}>  $fields  What to classify, keyed
     *                                                                                                     by field. No choices = free text.
     */
    public function __construct(public array $fields) {}

    public function instructions(): string
    {
        $fields = collect($this->fields)
            ->map(fn (array $field, string $key): string => '- '.$key.': '.$field['description']
                .($field['choices'] !== [] ? "\n  Choices: ".implode(' | ', $field['choices']) : ''))
            ->join("\n");

        return $this->houseRules()."\n\n".<<<TEXT
            Classify the support ticket you are given. For each field with choices, pick from them only, using the exact text of the choice.
            Give each answer a confidence between 0 and 1: how sure you are that a careful agent would choose the same. If nothing fits, answer null with a low confidence.
            Judge mainly by the customer's own messages.

            Fields:
            {$fields}
            TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->fields as $key => $field) {
            $choice = $field['choices'] !== [] ? $schema->string()->enum($field['choices']) : $schema->string();

            $properties[$key] = $schema->object([
                'value' => ($field['multiple'] ?? false)
                    ? $schema->array()->items($choice)->required()
                    : $choice->nullable()->required(),
                'confidence' => $schema->number()->min(0)->max(1)->required(),
            ])->withoutAdditionalProperties()->required();
        }

        return [
            ...$properties,
            'reason' => $schema->string()->description('One short sentence explaining the classification, for the run log.')->required(),
        ];
    }
}
