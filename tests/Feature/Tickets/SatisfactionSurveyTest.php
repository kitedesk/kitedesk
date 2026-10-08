<?php

use App\Domain\Reports\TicketReport;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Mail\SatisfactionSurveyEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function enableSurvey(int $delayHours = 24): void
{
    (new SatisfactionSurvey)->update(true, $delayHours)->save();
}

function solvedTicket(array $attributes = []): Ticket
{
    return Ticket::factory()->status(TicketStatus::Solved)->create($attributes);
}

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-10-12 12:00:00');
});

test('admins turn the survey on and choose the delay; agents cannot', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.satisfaction.update'), ['enabled' => true, 'delay_hours' => 4])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $survey = SatisfactionSurvey::current();
    expect($survey->enabled)->toBeTrue()
        ->and($survey->delayHours)->toBe(4)
        ->and($survey->enabledSince?->toDateTimeString())->toBe('2026-10-12 12:00:00');

    $this->actingAs(User::factory()->agent()->create())
        ->put(route('admin.satisfaction.update'), ['enabled' => false, 'delay_hours' => 4])
        ->assertForbidden();
});

test('the survey is emailed once to the requester after the delay', function () {
    enableSurvey(24);
    $ticket = solvedTicket(['solved_at' => now()->addHour()]);
    $this->travel(2)->hours();

    $this->artisan('tickets:send-satisfaction-surveys')->assertSuccessful();
    Mail::assertNothingQueued();

    $this->travel(1)->days();
    $this->artisan('tickets:send-satisfaction-surveys')->assertSuccessful();
    $this->artisan('tickets:send-satisfaction-surveys')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(SatisfactionSurveyEmail::class, fn (SatisfactionSurveyEmail $mail): bool => $mail->hasTo($ticket->requester->email)
        && $mail->rating->ticket_id === $ticket->id);
    $rating = $ticket->satisfactionRating()->sole();
    expect($rating)
        ->sent_at->not->toBeNull()
        ->score->toBeNull()
        ->and((new SatisfactionSurveyEmail($rating))->render())
        ->toContain(e($rating->urlFor(1)), e($rating->urlFor(5)));
});

test('no survey goes out when it is off or the ticket does not qualify', function () {
    $solvedBefore = solvedTicket(['solved_at' => now()->subHour()]);

    $this->artisan('tickets:send-satisfaction-surveys')->assertSuccessful();
    Mail::assertNothingQueued();

    enableSurvey(0);
    Ticket::factory()->status(TicketStatus::Open)->create();
    solvedTicket(['requester_id' => User::factory()->agent()]);
    $mergeTarget = solvedTicket();
    solvedTicket(['merged_into_id' => $mergeTarget->id]);
    SatisfactionRating::factory()->rated(5)->create(['ticket_id' => solvedTicket()->id]);
    $this->travel(1)->minutes();

    $this->artisan('tickets:send-satisfaction-surveys')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(SatisfactionSurveyEmail::class, fn (SatisfactionSurveyEmail $mail): bool => $mail->rating->ticket_id === $mergeTarget->id);
    expect($solvedBefore->satisfactionRating()->exists())->toBeFalse();
});

test('a star link shows the page without saving, and the page saves the answer', function () {
    $rating = SatisfactionRating::factory()->create();
    $url = $rating->urlFor(4);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guest/satisfaction')
            ->where('chosenScore', 4)
            ->where('canRate', true));
    expect($rating->fresh()->score)->toBeNull();

    $this->post($url, ['score' => 4, 'comment' => '  Quick and friendly.  '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $rating->refresh();
    expect($rating->score)->toBe(4)
        ->and($rating->comment)->toBe('Quick and friendly.')
        ->and($rating->rated_at)->not->toBeNull();

    $activity = Activity::query()->forSubject($rating->ticket)->where('event', 'rated')->sole();
    expect($activity->causer_id)->toBe($rating->ticket->requester_id)
        ->and($activity->properties->get('score'))->toBe(4);
});

test('star links must be signed and scores between 1 and 5', function () {
    $rating = SatisfactionRating::factory()->create();

    $this->get(route('guest.satisfaction.show', ['rating' => $rating, 'score' => 5]))->assertForbidden();
    $this->post($rating->urlFor(5).'x', ['score' => 5])->assertForbidden();

    $expiring = $rating->urlFor(5);
    $this->travel(SatisfactionRating::LINK_DAYS + 1)->days();
    $this->post($expiring, ['score' => 5])->assertForbidden();
    $this->travelBack();

    $this->post($rating->urlFor(5), ['score' => 6])->assertSessionHasErrors('score');
    $this->post($rating->urlFor(5), ['score' => 0])->assertSessionHasErrors('score');
});

test('a reopened ticket cannot be rated from the link', function () {
    $rating = SatisfactionRating::factory()->create();
    $rating->ticket->update(['status' => TicketStatus::Open]);

    $this->post($rating->urlFor(5), ['score' => 5])->assertForbidden();
});

test('requesters rate their solved tickets from the portal', function () {
    enableSurvey();
    $customer = User::factory()->create();
    $ticket = solvedTicket(['requester_id' => $customer->id]);

    $this->actingAs($customer)
        ->get(route('portal.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('satisfaction', ['score' => null, 'comment' => null]));

    $this->post(route('portal.tickets.satisfaction.store', $ticket), ['score' => 2, 'comment' => 'Took too long.'])
        ->assertSessionHasNoErrors();

    expect($ticket->satisfactionRating)->score->toBe(2)->sent_at->toBeNull();

    // Already rated, so the survey email is skipped.
    $this->travel(2)->days();
    $this->artisan('tickets:send-satisfaction-surveys');
    Mail::assertNothingQueued();
});

test('only the requester of a solved ticket can rate it, and only while the survey is on', function () {
    $customer = User::factory()->create();
    $open = Ticket::factory()->status(TicketStatus::Open)->create(['requester_id' => $customer->id]);
    $solved = solvedTicket(['requester_id' => $customer->id]);

    $this->actingAs($customer)->post(route('portal.tickets.satisfaction.store', $solved), ['score' => 5])->assertForbidden();

    enableSurvey();
    $this->actingAs($customer)->post(route('portal.tickets.satisfaction.store', $open), ['score' => 5])->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('portal.tickets.satisfaction.store', $solved), ['score' => 5])->assertForbidden();
});

test('people following a request by magic link can rate it', function () {
    enableSurvey();
    $ticket = solvedTicket();

    $this->withSession(['guest_tickets.'.$ticket->id => $ticket->requester_id])
        ->post(route('guest.tickets.satisfaction.store', $ticket), ['score' => 5])
        ->assertSessionHasNoErrors();

    expect($ticket->satisfactionRating->score)->toBe(5);

    $this->flushSession();
    $this->post(route('guest.tickets.satisfaction.store', $ticket), ['score' => 1])->assertForbidden();
});

test('agents see the rating on the ticket', function () {
    $rating = SatisfactionRating::factory()->rated(3, 'Okay.')->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.tickets.show', $rating->ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('satisfaction.score', 3)
            ->where('satisfaction.comment', 'Okay.'));
});

test('reports count the share of 4 and 5 star ratings given in the range', function () {
    $agent = User::factory()->agent()->create();

    foreach ([4, 5, 2] as $score) {
        SatisfactionRating::factory()->rated($score)->create([
            'ticket_id' => Ticket::factory()->assignedTo($agent)->status(TicketStatus::Solved),
        ]);
    }
    SatisfactionRating::factory()->create(); // Sent, not answered.
    SatisfactionRating::factory()->rated(1)->create(['rated_at' => now()->subMonths(2)]);

    $report = (new TicketReport(now()->subDays(6)->toImmutable(), now()->toImmutable()))->build();

    expect($report['totals']['satisfaction'])->toBe(66.7)
        ->and($report['totals']['satisfaction_responses'])->toBe(3)
        ->and(collect($report['breakdowns']['agent'])->firstWhere('key', (string) $agent->id))
        ->satisfaction->toBe(66.7)
        ->satisfaction_responses->toBe(3);
});
