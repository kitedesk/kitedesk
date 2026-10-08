<?php

namespace App\Domain\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a `position` column in order when admins move items up or down.
 */
class Positions
{
    /**
     * Swap the item with its neighbour among the given siblings.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $siblings
     * @param  TModel  $item
     * @param  'up'|'down'  $direction
     */
    public static function move(Builder $siblings, Model $item, string $direction): void
    {
        DB::transaction(function () use ($siblings, $item, $direction): void {
            $ordered = $siblings->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values()->all();
            $index = collect($ordered)->search(fn (Model $sibling): bool => $sibling->is($item));
            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($ordered[$swapWith])) {
                return;
            }

            [$ordered[$index], $ordered[$swapWith]] = [$ordered[$swapWith], $ordered[$index]];

            foreach ($ordered as $position => $sibling) {
                if ($sibling->getAttribute('position') !== $position) {
                    $sibling->forceFill(['position' => $position])->save();
                }
            }
        });
    }
}
