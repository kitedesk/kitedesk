<?php

namespace App\Domain\Support;

use BackedEnum;

/**
 * Turns backed enums into `{value, label}` lists for select inputs.
 */
class EnumOptions
{
    /**
     * @param  class-string<BackedEnum>  $enum
     * @return list<array{value: string|int, label: string}>
     */
    public static function for(string $enum): array
    {
        return array_map(fn (BackedEnum $case): array => [
            'value' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
        ], $enum::cases());
    }
}
