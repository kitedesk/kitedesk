<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Enums\TicketNumberReset;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\TicketNumberFormat;
use App\Domain\Tickets\Support\TicketNumberGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTicketNumberFormatRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How new ticket numbers are generated. Existing tickets keep their numbers.
 */
class TicketNumberController extends Controller
{
    public function edit(TicketNumberGenerator $numbers): Response
    {
        $format = TicketNumberFormat::current();

        return Inertia::render('admin/ticket-numbers/edit', [
            'settings' => [
                'format' => $format->format,
                'reset' => $format->reset->value,
                'next' => $numbers->peek(),
            ],
            'resets' => EnumOptions::for(TicketNumberReset::class),
            'latest' => Ticket::query()->latest('id')->first()?->reference(),
        ]);
    }

    public function update(SaveTicketNumberFormatRequest $request, TicketNumberGenerator $numbers): RedirectResponse
    {
        DB::transaction(function () use ($request, $numbers): void {
            (new TicketNumberFormat(
                $request->string('format')->toString(),
                TicketNumberReset::from($request->string('reset')->toString()),
            ))->save();

            $numbers->setNext($request->integer('next'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket numbers updated. New tickets use the new format.')]);

        return back();
    }
}
