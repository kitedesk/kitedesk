<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Validation\Rule;

/**
 * Stores a value for later nodes as `{{vars.<name>}}`. Variables are kept while the run waits.
 *
 * Settings: `name`, `value` (placeholders allowed) and `kind`: text, number or list
 * (comma- or line-separated).
 */
class SetVariableNode extends Node
{
    public function __construct(private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'set_variable';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/i'],
            'value' => ['present', 'nullable', 'string', 'max:5000'],
            'kind' => ['required', Rule::in(['text', 'number', 'list'])],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $text = trim($this->placeholders->text((string) ($data['value'] ?? ''), $context));

        $value = match ($data['kind'] ?? 'text') {
            'number' => is_numeric($text) ? $text + 0 : 0,
            'list' => array_values(array_filter(array_map(trim(...), preg_split('/[,\n]/', $text) ?: []), fn (string $item): bool => $item !== '')),
            default => $text,
        };

        $context->vars[(string) $data['name']] = $value;

        return NodeResult::next(['name' => $data['name'], 'value' => $value]);
    }
}
