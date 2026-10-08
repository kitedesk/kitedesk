<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Mail\Contracts\MailboxClient;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Jobs\ProcessInboundEmail;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundEmail;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Transport\InMemoryTransport;

/**
 * A mailbox client that hands out prepared emails instead of talking to a server.
 */
class FakeMailboxClient implements MailboxClient
{
    /**
     * @param  list<InboundEmail>  $emails
     */
    public function __construct(public array $emails = [], public ?string $failure = null) {}

    public function fetch(Mailbox $mailbox, Closure $handle, int $limit = 25): int
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }

        foreach ($this->emails as $email) {
            $handle($email);
        }

        return count($this->emails);
    }

    public function test(Mailbox $mailbox): void
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }
    }
}

function sampleInbound(): InboundEmail
{
    return new InboundEmail('jane@customer.test', 'Jane', [], [], 'Help', 'Body', '', 'id-1@customer.test', null, []);
}

test('the poller queues every unread email and records problems on the mailbox', function () {
    Queue::fake([ProcessInboundEmail::class]);
    $mailbox = Mailbox::factory()->create();
    Mailbox::factory()->driver(MailboxDriver::Postmark)->create();
    $this->app->instance(MailboxClient::class, new FakeMailboxClient([sampleInbound(), sampleInbound()]));

    $this->artisan('mail:poll')->assertSuccessful();

    Queue::assertPushed(ProcessInboundEmail::class, 2);
    expect($mailbox->refresh()->last_polled_at)->not->toBeNull()->and($mailbox->last_error)->toBeNull();

    $this->app->instance(MailboxClient::class, new FakeMailboxClient(failure: 'Authentication failed'));
    $this->artisan('mail:poll')->assertSuccessful();

    expect($mailbox->refresh()->last_error)->toBe('Authentication failed');
});

test('postmark webhooks need the mailbox password', function () {
    Queue::fake();
    $mailbox = Mailbox::factory()->driver(MailboxDriver::Postmark)->create(['inbound_secret' => 'pm-secret']);
    $payload = [
        'FromFull' => ['Email' => 'Jane@Customer.test', 'Name' => 'Jane'],
        'ToFull' => [['Email' => $mailbox->address, 'Name' => '']],
        'Subject' => 'Help',
        'TextBody' => 'Body',
        'Headers' => [['Name' => 'Message-ID', 'Value' => '<pm-1@customer.test>']],
        'Attachments' => [['Name' => 'a.txt', 'ContentType' => 'text/plain', 'Content' => base64_encode('hi')]],
    ];

    $this->postJson(route('inbound.postmark', $mailbox), $payload)->assertUnauthorized();
    $this->postJson(route('inbound.postmark', [$mailbox, 'token' => 'pm-secret']), $payload)->assertUnauthorized();
    $this->withBasicAuth('inbound', 'pm-secret')->postJson(route('inbound.postmark', $mailbox), $payload)->assertOk();

    Queue::assertPushed(ProcessInboundEmail::class, fn (ProcessInboundEmail $job): bool => $job->email->fromEmail === 'jane@customer.test'
        && $job->email->messageId === 'pm-1@customer.test'
        && $job->email->attachments[0]->contents() === 'hi');
});

test('mailgun webhooks must be signed with the mailbox signing key', function () {
    Queue::fake();
    $mailbox = Mailbox::factory()->driver(MailboxDriver::Mailgun)->create(['inbound_secret' => 'mg-key']);
    $timestamp = (string) time();
    $fields = [
        'timestamp' => $timestamp,
        'token' => 'tok',
        'from' => 'Jane <jane@customer.test>',
        'To' => $mailbox->address,
        'subject' => 'Help',
        'body-plain' => 'Body',
        'Message-Id' => '<mg-1@customer.test>',
    ];

    $this->post(route('inbound.mailgun', $mailbox), [...$fields, 'signature' => 'wrong'])->assertUnauthorized();
    $this->post(route('inbound.mailgun', $mailbox), [...$fields, 'signature' => hash_hmac('sha256', $timestamp.'tok', 'mg-key')])->assertOk();
    $this->post(route('inbound.mailgun', $mailbox), [...$fields, 'signature' => hash_hmac('sha256', $timestamp.'tok', 'mg-key')])->assertJson(['status' => 'duplicate']);

    Queue::assertPushed(ProcessInboundEmail::class, 1);
    Queue::assertPushed(ProcessInboundEmail::class, fn (ProcessInboundEmail $job): bool => $job->email->fromName === 'Jane'
        && $job->email->messageId === 'mg-1@customer.test');
});

test('public replies email the requester from the ticket mailbox with threading headers', function () {
    $mailbox = Mailbox::factory()->create(['address' => 'help@support.test', 'name' => 'Acme Help']);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['mailbox_id' => $mailbox->id, 'channel' => TicketChannel::Email]);
    $ticket->messages()->create(['author_id' => $requester->id, 'body' => '<p>Help</p>', 'channel' => TicketChannel::Email])
        ->forceFill(['email_message_id' => 'orig@customer.test'])->save();

    $reply = app(AddMessage::class)->handle($ticket, User::factory()->agent()->create(['name' => 'Sam']), '<p>On it!</p>');
    app(AddMessage::class)->handle($ticket, User::factory()->agent()->create(), '<p>Secret</p>', isInternal: true);

    /** @var InMemoryTransport|ArrayTransport $transport */
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $sent = collect($transport->messages())
        ->map(fn ($message) => $message->getOriginalMessage())
        ->filter(fn ($email) => $email->getTo()[0]->getAddress() === $requester->email);

    expect($sent)->toHaveCount(1);

    $email = $sent->first();
    $host = MessageIds::host();

    expect($email->getFrom()[0]->getAddress())->toBe('help@support.test')
        ->and($email->getFrom()[0]->getName())->toBe('Acme Help')
        ->and($email->getReplyTo()[0]->getAddress())->toBe('help@support.test')
        ->and($email->getHeaders()->get('Message-ID')->getBodyAsString())->toBe('<'.MessageIds::forMessage($reply).'>')->toMatch("/^<ticket-{$ticket->id}-[0-9a-f]{16}\\.message-{$reply->id}@{$host}>$/")
        ->and($email->getHeaders()->get('In-Reply-To')->getBodyAsString())->toBe('<orig@customer.test>')
        ->and($email->getHeaders()->get('References')->getBodyAsString())->toContain('<orig@customer.test>')
        ->and($email->getSubject())->toBe("[#{$ticket->id}] Re: {$ticket->subject}")
        ->and($email->getHtmlBody())->toContain('On it!')->toContain('Sam replied to your request:')->toContain('Please type your reply above this line')
        ->and($email->getHtmlBody())->not->toContain('Secret');
});

test('new tickets get an auto-reply and alert the group, and solved tickets send a notice', function () {
    Mail::fake();
    $group = Group::factory()->create();
    $agent = User::factory()->agent()->create();
    $group->agents()->attach($agent);
    $requester = User::factory()->create();

    $ticket = app(CreateTicket::class)->handle($requester, ['subject' => 'Help', 'body' => '<p>Hi</p>', 'group_id' => $group->id], TicketChannel::Portal);

    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::TicketReceived && $mail->hasTo($requester->email));
    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::AgentNewTicketAlert && $mail->hasTo($agent->email));

    app(UpdateTicket::class)->handle($ticket, ['status' => TicketStatus::Solved->value], $agent);

    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::TicketSolved && $mail->hasTo($requester->email));
});

test('switched-off templates are not sent and repeat senders get one auto-reply', function () {
    Mail::fake();
    EmailTemplate::query()->create(['event' => EmailTemplateEvent::AgentNewTicketAlert, 'subject' => 'x', 'body' => 'y', 'is_active' => false]);
    $requester = User::factory()->create();

    foreach (range(1, 2) as $attempt) {
        app(CreateTicket::class)->handle($requester, ['subject' => "Help {$attempt}", 'body' => '<p>Hi</p>'], TicketChannel::Email);
    }

    Mail::assertQueued(TicketEmail::class, 1);
    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::TicketReceived);
});

test('templates fill their placeholders', function () {
    EmailTemplate::query()->create(['event' => EmailTemplateEvent::TicketReceived, 'subject' => 'Got #{{ticket.id}} from {{requester.first_name}}', 'body' => "Hello <b>{{requester.name}}</b>\n\nSee {{ticket.url}}", 'is_active' => true]);
    $requester = User::factory()->create(['name' => 'Ana <Script> Lima']);
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $mail = new TicketEmail($ticket, EmailTemplateEvent::TicketReceived, $requester);

    expect($mail->envelope()->subject)->toBe("Got #{$ticket->id} from Ana")
        ->and($mail->render())->toContain('Hello &lt;b&gt;Ana &lt;Script&gt; Lima&lt;/b&gt;')
        ->and($mail->headers()->text['Auto-Submitted'])->toBe('auto-replied');
});
