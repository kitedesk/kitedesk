<?php

namespace App\Domain\Workflows\Engine;

use Carbon\CarbonImmutable;

/**
 * What a node decided: which output to continue from, plus a short log for the run history.
 */
final class NodeResult
{
    public const string CONTINUE_LOOP = 'continue';

    public const string BREAK_LOOP = 'break';

    public const string STOP = 'stop';

    public const string WAIT = 'wait';

    /**
     * @param  array<string, mixed>  $output
     * @param  list<mixed>|null  $items  Items to loop over ("For each").
     */
    private function __construct(
        public readonly ?string $handle,
        public readonly array $output = [],
        public readonly ?string $control = null,
        public readonly ?CarbonImmutable $waitUntil = null,
        public readonly ?string $waitFor = null,
        public readonly ?array $items = null,
    ) {}

    /**
     * @param  array<string, mixed>  $output
     */
    public static function next(array $output = []): self
    {
        return new self('out', $output);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function branch(string $handle, array $output = []): self
    {
        return new self($handle, $output);
    }

    /**
     * @param  list<mixed>  $items
     * @param  array<string, mixed>  $output
     */
    public static function loop(array $items, array $output = []): self
    {
        return new self('done', $output, items: $items);
    }

    /**
     * Pause the run until the given time, or until something happens first (`$for` = "reply").
     *
     * @param  array<string, mixed>  $output
     */
    public static function wait(CarbonImmutable $until, ?string $for = null, array $output = []): self
    {
        return new self(null, $output, self::WAIT, $until, $for);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function stop(array $output = []): self
    {
        return new self(null, $output, self::STOP);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function breakLoop(array $output = []): self
    {
        return new self(null, $output, self::BREAK_LOOP);
    }

    /**
     * Skip to the next loop item.
     *
     * @param  array<string, mixed>  $output
     */
    public static function skipItem(array $output = []): self
    {
        return new self(null, $output, self::CONTINUE_LOOP);
    }
}
