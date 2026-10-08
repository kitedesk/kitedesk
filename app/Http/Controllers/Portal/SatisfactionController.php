<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Tickets\Actions\RateTicket;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\GuestAccess;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RateTicketRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Answers to the satisfaction survey: from the star links in the survey email (signed, no
 * login), from the portal, and from a request opened through a magic link.
 */
class SatisfactionController extends Controller
{
    /**
     * The page a star in the email opens. It saves nothing by itself, since mail scanners
     * open every link; the page submits the chosen score once it loads in a browser.
     */
    public function show(Request $request, SatisfactionRating $rating): Response
    {
        $ticket = $rating->ticket;
        $score = $request->integer('score');

        return Inertia::render('guest/satisfaction', [
            'ticket' => ['number' => $ticket->reference(), 'subject' => $ticket->subject],
            'chosenScore' => $score >= 1 && $score <= 5 ? $score : null,
            'rating' => $rating->score !== null ? ['score' => $rating->score, 'comment' => $rating->comment] : null,
            'canRate' => SatisfactionSurvey::isRateable($ticket),
            'submitUrl' => $request->fullUrl(),
        ]);
    }

    public function store(RateTicketRequest $request, SatisfactionRating $rating, RateTicket $rateTicket): RedirectResponse
    {
        $ticket = $rating->ticket;
        abort_unless(SatisfactionSurvey::isRateable($ticket), 403);

        $this->rate($request, $ticket, $rating->user ?? $ticket->requester, $rateTicket);

        return back();
    }

    public function portal(RateTicketRequest $request, Ticket $ticket, RateTicket $rateTicket): RedirectResponse
    {
        Gate::authorize('rate', $ticket);

        $this->rate($request, $ticket, $request->user(), $rateTicket);

        return back();
    }

    public function guest(RateTicketRequest $request, Ticket $ticket, RateTicket $rateTicket): RedirectResponse
    {
        $guest = GuestAccess::userFor($request, $ticket);
        abort_unless($guest !== null && SatisfactionSurvey::current()->canRate($ticket, $guest), 403);

        $this->rate($request, $ticket, $guest, $rateTicket);

        return back();
    }

    private function rate(RateTicketRequest $request, Ticket $ticket, User $customer, RateTicket $rateTicket): void
    {
        $rateTicket->handle($ticket, $customer, $request->integer('score'), $request->string('comment')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks for your feedback!')]);
    }
}
