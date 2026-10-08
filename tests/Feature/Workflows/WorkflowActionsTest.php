<?php

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Models\Group;
use App\Domain\Support\PublicNetwork;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketReplied;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Domain\Workflows\Notifications\WorkflowAlert;
use App\Mail\WorkflowEmail;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/helpers.php';

test('reply sends a public message to the requester and can solve the ticket', function () {
    Notification::fake();
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['reply', ['body' => '<p>Hi {{requester.first_name}}, we got it.</p>', 'status_after' => 'solved']],
    ])->create();

    $ticket = workflowTicket(requester: User::factory()->create(['name' => 'Bea Souza']))->refresh();
    $reply = $ticket->messages()->where('is_internal', false)->latest('id')->first();

    expect($reply?->body)->toContain('Hi Bea, we got it.')
        ->and($reply?->author_id)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Solved)
        ->and($ticket->last_agent_reply_at)->not->toBeNull();
    Notification::assertSentTo($ticket->requester, TicketReplied::class);
});

test('placeholders are escaped in rich text', function () {
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [['add_note', ['body' => '<p>{{ticket.subject}}</p>']]])->create();

    $note = workflowTicket(['subject' => '<script>alert(1)</script>'])->messages()->where('is_internal', true)->sole();

    expect($note->body)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

test('send email goes to the chosen addresses from the ticket mailbox', function () {
    Mail::fake();
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['send_email', ['to' => 'address', 'address' => 'boss@example.com, not-an-email', 'subject' => 'New: {{ticket.subject}}', 'body' => '<p>FYI</p>']],
    ])->create();

    workflowTicket(['subject' => 'Server down']);

    Mail::assertQueued(WorkflowEmail::class, fn (WorkflowEmail $mail): bool => $mail->hasTo('boss@example.com') && $mail->emailSubject === 'New: Server down');
    Mail::assertQueued(WorkflowEmail::class, 1);
});

test('notify alerts the chosen agents', function () {
    Notification::fake();
    $group = Group::factory()->create();
    [$ana, $bo] = User::factory()->agent()->count(2)->create()->all();
    $group->agents()->attach([$ana->id, $bo->id]);
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['notify', ['to' => 'group', 'message' => 'Look at {{ticket.number}}']],
    ])->create(['name' => 'Escalate']);

    $ticket = workflowTicket(['group_id' => $group->id]);

    Notification::assertSentTo([$ana, $bo], WorkflowAlert::class, fn (WorkflowAlert $alert): bool => $alert->message === "Look at {$ticket->reference()}" && $alert->workflowName === 'Escalate');
});

test('add cc copies people, creating accounts for new addresses', function () {
    $known = User::factory()->create(['email' => 'known@example.com']);
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['add_cc', ['emails' => ['known@example.com', 'NEW@example.com']]],
    ])->create();

    $ticket = workflowTicket();

    expect($ticket->collaborators()->pluck('email')->sort()->values()->all())->toBe(['known@example.com', 'new@example.com'])
        ->and($ticket->collaborators()->whereKey($known->id)->exists())->toBeTrue();
});

test('auto assign uses the group assignment mode', function () {
    $group = Group::factory()->create(['assignment_mode' => AssignmentMode::LeastBusy]);
    $agent = User::factory()->agent()->create();
    $group->agents()->attach($agent);
    Workflow::factory()->chain(WorkflowTrigger::CustomerReplied, [['auto_assign']])->create();
    $ticket = Ticket::factory()->create(['group_id' => $group->id]);

    app(AddMessage::class)->handle($ticket, $ticket->requester, '<p>Anyone?</p>');

    expect($ticket->refresh()->assignee_id)->toBe($agent->id)
        ->and($ticket->status)->toBe(TicketStatus::Open);
});

test('http requests send the rendered request and keep the response for later steps', function () {
    Http::fake(['crm.test/*' => Http::response(['plan' => 'gold', 'items' => ['a', 'b']])]);
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['http_request', [
            'method' => 'POST',
            'url' => 'https://crm.test/lookup?email={{requester.email}}',
            'headers' => [['name' => 'X-Token', 'value' => 'secret']],
            'body' => '{"ticket": "{{ticket.id}}"}',
            'save_as' => 'crm',
        ]],
        ['add_tags', ['tags' => ['{{vars.crm.body.plan}}']]],
    ])->create();

    $ticket = workflowTicket(requester: User::factory()->create(['email' => 'vip@example.com']));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://crm.test/lookup?email=vip@example.com'
        && $request->header('X-Token') === ['secret']
        && $request->body() === '{"ticket": "'.$ticket->id.'"}');
    expect($ticket->tags()->pluck('name')->all())->toBe(['gold']);
});

test('http requests to internal addresses are blocked', function () {
    Http::fake();
    PublicNetwork::resolveUsing(fn (string $host): array => ['10.0.0.5']);
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['http_request', ['method' => 'GET', 'url' => 'https://intranet.test/']],
    ])->create();

    workflowTicket();

    Http::assertNothingSent();
    expect(WorkflowRun::query()->sole())
        ->status->toBe(RunStatus::Failed)
        ->error->toBe('Blocked: the URL does not point to a public internet address.');
});
