<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Support\Models\Setting;
use App\Domain\Tickets\Enums\TicketNumberReset;
use Carbon\CarbonInterface;

/**
 * How new ticket numbers look, e.g. "{seq}" → 42, "TKT-{seq:5}" → TKT-00042,
 * "{yyyy}-{seq:4}" → 2026-0042 or "{random:8}" → 48210937.
 */
final readonly class TicketNumberFormat
{
    public const string SETTING = 'ticket_numbers';

    public const string DEFAULT = '{seq}';

    /**
     * Matches every supported token.
     */
    public const string TOKENS = '/\{(seq|random)(?::(\d{1,2}))?\}|\{(yyyy|yy|mm|dd)\}/';

    public function __construct(
        public string $format = self::DEFAULT,
        public TicketNumberReset $reset = TicketNumberReset::Never,
    ) {}

    public static function current(): self
    {
        $setting = Setting::get(self::SETTING);
        $setting = is_array($setting) ? $setting : [];

        return new self(
            is_string($setting['format'] ?? null) ? $setting['format'] : self::DEFAULT,
            TicketNumberReset::tryFrom((string) ($setting['reset'] ?? '')) ?? TicketNumberReset::Never,
        );
    }

    public function save(): void
    {
        Setting::put(self::SETTING, ['format' => $this->format, 'reset' => $this->reset->value]);
    }

    public function usesSequence(): bool
    {
        return str_contains($this->format, '{seq');
    }

    /**
     * Why the format can't produce unique numbers, or null when it is fine.
     */
    public static function problemWith(string $format): ?string
    {
        if (preg_match('/^[A-Za-z0-9\-_.\/]*$/', (string) preg_replace(self::TOKENS, '', $format)) !== 1) {
            return __('Use only letters, numbers, - _ . / and the tokens shown below.');
        }

        preg_match_all('/\{random(?::(\d{1,2}))?\}/', $format, $random);
        $randomDigits = array_sum(array_map(fn (string $digits): int => $digits === '' ? 6 : (int) $digits, $random[1]));

        if (! str_contains($format, '{seq') && $randomDigits < 6) {
            return __('Include {seq}, or {random} with at least 6 digits, so every ticket gets a different number.');
        }

        if (mb_strlen(self::sample($format)) > 40) {
            return __('Ticket numbers can be at most 40 characters long.');
        }

        return null;
    }

    /**
     * The number for the given counter value and moment.
     */
    public function render(int $sequence, CarbonInterface $at): string
    {
        return self::fill($this->format, $sequence, $at);
    }

    private static function sample(string $format): string
    {
        return self::fill($format, 99999, now());
    }

    private static function fill(string $format, int $sequence, CarbonInterface $at): string
    {
        return (string) preg_replace_callback(self::TOKENS, function (array $match) use ($sequence, $at): string {
            $width = (int) ($match[2] ?? 0);

            return match ($match[1] !== '' ? $match[1] : $match[3]) {
                'seq' => str_pad((string) $sequence, $width, '0', STR_PAD_LEFT),
                'random' => self::randomDigits($width > 0 ? $width : 6),
                'yyyy' => $at->format('Y'),
                'yy' => $at->format('y'),
                'mm' => $at->format('m'),
                default => $at->format('d'),
            };
        }, $format);
    }

    private static function randomDigits(int $length): string
    {
        $digits = (string) random_int(1, 9);

        for ($i = 1; $i < $length; $i++) {
            $digits .= random_int(0, 9);
        }

        return $digits;
    }
}
