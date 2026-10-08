<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Mail\Actions\ReceiveEmail;
use App\Domain\Mail\Jobs\ProcessInboundEmail;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundAttachment;
use App\Domain\Mail\Support\InboundEmail;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Mail\Support\ReplyParser;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake();

    $this->mailbox = Mailbox::factory()->create(['address' => 'help@support.test']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function inboundEmail(array $overrides = []): InboundEmail
{
    return new InboundEmail(...[
        'fromEmail' => 'jane@customer.test',
        'fromName' => 'Jane Doe',
        'to' => [['email' => 'help@support.test', 'name' => 'Help']],
        'cc' => [],
        'subject' => 'Printer is on fire',
        'text' => "It is really on fire.\n\nJane",
        'html' => '',
        'messageId' => 'abc123@customer.test',
        'inReplyTo' => null,
        'references' => [],
        'headers' => [],
        'attachments' => [],
        ...$overrides,
    ]);
}

function receive(InboundEmail $email, ?Mailbox $mailbox = null): ?TicketMessage
{
    return app(ReceiveEmail::class)->handle($mailbox ?? test()->mailbox, $email);
}

test('a new email opens a ticket from the sender, with copies, defaults and attachments', function () {
    $group = Group::factory()->create();
    $category = TicketCategory::factory()->create();
    $this->mailbox->update(['default_group_id' => $group->id, 'default_category_id' => $category->id]);

    $message = receive(inboundEmail([
        'subject' => 'Fwd: Printer is on fire',
        'cc' => [['email' => 'Boss@Customer.test', 'name' => 'The Boss'], ['email' => 'help@support.test', 'name' => '']],
        'attachments' => [InboundAttachment::fromContents('photo.png', 'image/png', 'png-bytes')],
    ]));

    $ticket = $message->ticket;
    $jane = User::query()->where('email', 'jane@customer.test')->sole();

    expect($ticket->channel)->toBe(TicketChannel::Email)
        ->and($ticket->subject)->toBe('Printer is on fire')
        ->and($ticket->requester_id)->toBe($jane->id)
        ->and($jane->name)->toBe('Jane Doe')
        ->and($ticket->mailbox_id)->toBe($this->mailbox->id)
        ->and($ticket->group_id)->toBe($group->id)
        ->and($ticket->category_id)->toBe($category->id)
        ->and($ticket->collaborators->pluck('email')->all())->toBe(['boss@customer.test'])
        ->and($message->email_message_id)->toBe('abc123@customer.test')
        ->and($message->body)->toContain('It is really on fire.')
        ->and($message->getMedia('attachments')->sole()->file_name)->toBe('photo.png');
});

test('replies are threaded by our Message-ID and lose the quoted conversation', function () {
    $ticket = receive(inboundEmail())->ticket;
    $agentReply = TicketMessage::factory()->for($ticket)->create(['author_id' => User::factory()->agent()->create()->id, 'is_internal' => false]);

    $message = receive(inboundEmail([
        'messageId' => 'reply-1@customer.test',
        'inReplyTo' => MessageIds::forMessage($agentReply),
        'subject' => 'Re: something unrelated',
        'html' => '<div>Still burning!</div><div class="gmail_quote">On Mon, Support wrote:<blockquote>old stuff</blockquote></div>',
    ]));

    expect($message->ticket_id)->toBe($ticket->id)
        ->and($message->body)->toContain('Still burning!')
        ->and($message->body)->not->toContain('old stuff')
        ->and(Ticket::query()->count())->toBe(1);
});

test('replies are threaded by a stored Message-ID in References', function () {
    $ticket = receive(inboundEmail())->ticket;

    $message = receive(inboundEmail([
        'messageId' => 'reply-2@customer.test',
        'references' => ['unknown@elsewhere.test', 'abc123@customer.test'],
        'text' => "Any news?\n\nOn Mon, 5 Oct 2026, Help <help@support.test> wrote:\n> We are on it",
    ]));

    expect($message->ticket_id)->toBe($ticket->id)
        ->and($message->body)->toBe('<p>Any news?</p>');
});

test('a [#id] subject token only threads mail from people on the ticket', function () {
    $ticket = receive(inboundEmail())->ticket;

    $fromRequester = receive(inboundEmail(['messageId' => 'r3@customer.test', 'subject' => "Re: [#{$ticket->id}] Printer"]));
    $fromStranger = receive(inboundEmail(['messageId' => 'r4@x.test', 'fromEmail' => 'stranger@x.test', 'subject' => "[#{$ticket->id}] Printer"]));

    expect($fromRequester->ticket_id)->toBe($ticket->id)
        ->and($fromStranger->ticket_id)->not->toBe($ticket->id);
});

test('the same email is only processed once', function () {
    receive(inboundEmail());

    expect(receive(inboundEmail()))->toBeNull()
        ->and(TicketMessage::query()->count())->toBe(1);
});

test('automatic replies, our own mail and banned senders are ignored', function (array $overrides) {
    config(['kitedesk.mail.banlist' => ['@spam.test']]);

    expect(receive(inboundEmail($overrides)))->toBeNull()
        ->and(Ticket::query()->count())->toBe(0);
})->with([
    'out of office' => [['headers' => ['auto-submitted' => 'auto-replied']]],
    'bulk mail' => [['headers' => ['precedence' => 'bulk']]],
    'bounce' => [['fromEmail' => 'MAILER-DAEMON@customer.test']],
    'loop from our mailbox' => [['fromEmail' => 'help@support.test']],
    'banned domain' => [['fromEmail' => 'bot@spam.test']],
]);

test('deactivated people can neither write in nor be copied', function () {
    User::factory()->deactivated()->create(['email' => 'jane@customer.test']);
    User::factory()->deactivated()->create(['email' => 'boss@customer.test']);

    expect(receive(inboundEmail()))->toBeNull();

    $message = receive(inboundEmail([
        'fromEmail' => 'bob@customer.test',
        'cc' => [['email' => 'boss@customer.test', 'name' => 'Boss']],
    ]));

    expect($message->ticket->collaborators)->toBeEmpty();
});

test('a reply to a closed ticket opens a follow-up ticket', function () {
    $ticket = receive(inboundEmail())->ticket;
    $ticket->forceFill(['status' => TicketStatus::Closed])->save();

    $message = receive(inboundEmail(['messageId' => 'later@customer.test', 'inReplyTo' => 'abc123@customer.test', 'subject' => 'Re: Printer is on fire']));

    expect($message->ticket_id)->not->toBe($ticket->id)
        ->and($message->ticket->tags->pluck('name')->all())->toBe(['follow_up'])
        ->and($message->metadata['follow_up_of'])->toBe($ticket->id)
        ->and($message->ticket->linkedTickets()->pluck('tickets.id')->all())->toBe([$ticket->id]);
});

test('replies to a merged ticket land on the ticket it was merged into', function () {
    $duplicate = receive(inboundEmail())->ticket;
    $target = Ticket::factory()->for($duplicate->requester, 'requester')->create();
    $duplicate->forceFill(['merged_into_id' => $target->id, 'status' => TicketStatus::Closed])->save();

    $message = receive(inboundEmail(['messageId' => 'after-merge@customer.test', 'inReplyTo' => 'abc123@customer.test', 'subject' => 'Re: Printer is on fire']));

    expect($message->ticket_id)->toBe($target->id);
});

test('agents can answer by email and customer replies reopen solved tickets', function () {
    $agent = User::factory()->agent()->create(['email' => 'sam@support.test']);
    $ticket = receive(inboundEmail())->ticket;

    $reply = receive(inboundEmail([
        'messageId' => 'sam-1@support.test',
        'fromEmail' => 'sam@support.test',
        'inReplyTo' => 'abc123@customer.test',
        'headers' => ['authentication-results' => 'mx.support.test; dkim=pass header.d=support.test; dmarc=pass header.from=support.test'],
    ]));
    expect($reply->is_internal)->toBeFalse()
        ->and($reply->author_id)->toBe($agent->id)
        ->and($ticket->refresh()->first_responded_at)->not->toBeNull();

    $ticket->forceFill(['status' => TicketStatus::Solved])->save();
    receive(inboundEmail(['messageId' => 'jane-2@customer.test', 'inReplyTo' => 'sam-1@support.test']));

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);
});

test('quoted text below our reply marker is removed', function () {
    expect(ReplyParser::fromText("Thanks!\n\n".ReplyParser::MARKER."\nOld message"))->toBe('Thanks!')
        ->and(ReplyParser::fromHtml('<p>Thanks!</p><p>'.ReplyParser::MARKER.'</p><p>Old</p>'))->toBe('<p>Thanks!</p><p>');
});

test('strangers cannot join a ticket by copying its thread headers or subject token', function () {
    $ticket = receive(inboundEmail())->ticket;
    $unsigned = "ticket-{$ticket->id}@".MessageIds::host();

    $guessed = receive(inboundEmail(['messageId' => 's-1@evil.test', 'fromEmail' => 'eve@evil.test', 'inReplyTo' => $unsigned]));
    $copied = receive(inboundEmail(['messageId' => 's-2@evil.test', 'fromEmail' => 'eve@evil.test', 'references' => [MessageIds::forTicket($ticket)], 'subject' => "Re: [{$ticket->reference()}] Printer"]));

    expect(MessageIds::ticketIdFrom($unsigned))->toBeNull()
        ->and(MessageIds::ticketIdFrom(MessageIds::forTicket($ticket)))->toBe($ticket->id)
        ->and($guessed->ticket_id)->not->toBe($ticket->id)
        ->and($copied->ticket_id)->not->toBe($ticket->id)
        ->and($copied->ticket->linkedTickets()->pluck('tickets.id')->all())->toBe([$ticket->id])
        ->and($ticket->collaborators()->count())->toBe(0);
});

test('mail whose From address fails authentication never reaches an existing ticket', function () {
    $ticket = receive(inboundEmail())->ticket;

    $forged = receive(inboundEmail([
        'messageId' => 'forged@evil.test',
        'inReplyTo' => 'abc123@customer.test',
        'cc' => [['email' => 'victim@else.test', 'name' => '']],
        'headers' => ['authentication-results' => 'mx.support.test; spf=fail smtp.mailfrom=evil.test; dmarc=fail header.from=customer.test'],
    ]));

    expect($forged->ticket_id)->not->toBe($ticket->id)
        ->and($forged->ticket->tags->pluck('name')->all())->toBe(['unverified_sender'])
        ->and($forged->ticket->collaborators()->count())->toBe(0)
        ->and($forged->metadata['sender_verification'])->toBe('failed');
});

test('staff mail without a confirmed sender is kept as an internal note', function () {
    User::factory()->agent()->create(['email' => 'sam@support.test']);
    $ticket = receive(inboundEmail())->ticket;

    $reply = receive(inboundEmail(['messageId' => 'sam-9@support.test', 'fromEmail' => 'sam@support.test', 'subject' => "Re: [{$ticket->reference()}] Printer"]));

    expect($reply->ticket_id)->toBe($ticket->id)
        ->and($reply->is_internal)->toBeTrue();
});

test('sender verification reads aligned DKIM, SPF and DMARC results', function (array $headers, string $from, string $expected) {
    expect(inboundEmail(['fromEmail' => $from, 'headers' => $headers])->senderVerification()->value)->toBe($expected);
})->with([
    'dmarc pass' => [['authentication-results' => 'mx.google.com; dkim=pass header.i=@acme.test; dmarc=pass (p=NONE) header.from=acme.test'], 'a@acme.test', 'passed'],
    'dmarc fail' => [['authentication-results' => 'mx.google.com; dkim=pass header.d=evil.test; dmarc=fail header.from=acme.test'], 'a@acme.test', 'failed'],
    'aligned dkim' => [['authentication-results' => 'mx.test; dkim=pass header.d=mail.acme.test'], 'a@acme.test', 'passed'],
    'aligned spf' => [['authentication-results' => 'mx.test; spf=pass smtp.mailfrom=bounce@acme.test'], 'a@acme.test', 'passed'],
    'unaligned spf' => [['authentication-results' => 'mx.test; spf=pass smtp.mailfrom=evil.test'], 'a@acme.test', 'failed'],
    'mailgun dkim' => [['x-mailgun-dkim-check-result' => 'Pass', 'dkim-signature' => 'v=1; a=rsa-sha256; d=acme.test; s=k1'], 'a@acme.test', 'passed'],
    'received-spf' => [['received-spf' => 'Pass (sender SPF authorized) identity=mailfrom; envelope-from="x@acme.test"'], 'a@acme.test', 'passed'],
    'no results' => [[], 'a@acme.test', 'unknown'],
]);

test('results from an untrusted server are ignored when trusted servers are configured', function () {
    config(['kitedesk.mail.authserv_ids' => ['mx.google.com']]);

    expect(inboundEmail(['headers' => ['authentication-results' => 'evil.test; dmarc=pass header.from=customer.test']])->senderVerification()->value)->toBe('unknown');
});

test('only a limited number of copied addresses join a new ticket', function () {
    config(['kitedesk.mail.max_copied_people' => 2]);

    $message = receive(inboundEmail(['cc' => collect(range(1, 5))->map(fn (int $i): array => ['email' => "cc{$i}@customer.test", 'name' => ''])->all()]));

    expect($message->ticket->collaborators()->count())->toBe(2);
});

test('attachment files are removed once the email is processed', function () {
    $attachment = InboundAttachment::fromContents('notes.txt', 'text/plain', 'hello');

    (new ProcessInboundEmail($this->mailbox, inboundEmail(['attachments' => [$attachment]])))->handle(app(ReceiveEmail::class));

    expect(Storage::disk('local')->exists($attachment->path))->toBeFalse()
        ->and(TicketMessage::query()->sole()->getMedia('attachments')->sole()->file_name)->toBe('notes.txt');
});

test('remote images in incoming email are dropped', function () {
    $message = receive(inboundEmail(['html' => '<p>See below</p><img src="https://tracker.example/pixel.gif?id=42" alt=""><p><a href="https://example.com">a link</a></p>']));

    expect($message->body)->not->toContain('tracker.example')
        ->and($message->body)->toContain('See below')
        ->and($message->body)->toContain('https://example.com');
});
