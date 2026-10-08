<?php

namespace App\Http\Controllers\Admin\KnowledgeBase;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait ReordersSiblings
{
    /**
     * Swap a record with its neighbour, renumbering the sibling positions to 0..n.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $siblings
     * @param  TModel  $record
     */
    protected function moveAmongSiblings(Builder $siblings, Model $record, bool $up): void
    {
        DB::transaction(function () use ($siblings, $record, $up): void {
            $ordered = $siblings->orderBy('position')->orderBy('id')->get()->values();
            $index = $ordered->search(fn (Model $sibling): bool => $sibling->is($record));

            if ($index === false) {
                return;
            }

            $target = $up ? $index - 1 : $index + 1;

            if ($target < 0 || $target >= $ordered->count()) {
                return;
            }

            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            foreach (array_values($items) as $position => $item) {
                if ($item->getAttribute('position') !== $position) {
                    $item->forceFill(['position' => $position])->save();
                }
            }
        });
    }
}
