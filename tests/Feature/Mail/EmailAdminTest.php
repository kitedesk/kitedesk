<?php

use App\Domain\Mail\Contracts\MailboxClient;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Support\PublicNetwork;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins add IMAP mailboxes, keep the saved password and keep one default', function () {
    $old = Mailbox::factory()->create(['is_default' => true]);

    $this->actingAs($this->admin)->post(route('admin.mailboxes.store'), [
        'name' => 'Acme Help',
        'address' => 'Help@Acme.test',
        'driver' => 'imap',
        'imap_host' => 'imap.acme.test',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'help',
        'imap_password' => 'app-password',
        'is_default' => true,
    ])->assertSessionHasNoErrors();

    $mailbox = Mailbox::query()->where('address', 'help@acme.test')->sole();
    expect($mailbox->imap_password)->toBe('app-password')
        ->and($mailbox->inbound_secret)->not->toBeEmpty()
        ->and($old->refresh()->is_default)->toBeFalse();

    $this->actingAs($this->admin)->put(route('admin.mailboxes.update', $mailbox), [
        'name' => 'Acme Help', 'address' => 'help@acme.test', 'driver' => 'imap',
        'imap_host' => 'imap.acme.test', 'imap_port' => 993, 'imap_username' => 'help', 'imap_password' => '',
    ])->assertSessionHasNoErrors();

    expect($mailbox->refresh()->imap_password)->toBe('app-password');

    $this->actingAs($this->admin)
        ->get(route('admin.mailboxes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/mailboxes/index')
            ->missing('mailboxes.0.imap_password')
            ->where('mailboxes.0.has_imap_password', true));
});

test('testing a connection reports the server error', function () {
    $mailbox = Mailbox::factory()->create();
    $this->app->instance(MailboxClient::class, new class implements MailboxClient
    {
        public function fetch(Mailbox $mailbox, Closure $handle, int $limit = 50): int
        {
            return 0;
        }

        public function test(Mailbox $mailbox): void
        {
            throw new RuntimeException('Login failed');
        }
    });

    $this->actingAs($this->admin)->post(route('admin.mailboxes.test', $mailbox))->assertRedirect();

    expect($mailbox->refresh()->last_error)->toBe('Login failed');
});

test('admins reword, switch off and reset email templates', function () {
    $this->actingAs($this->admin)->get(route('admin.email-templates.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.email-templates.edit', 'ticket_received'))->assertOk();

    $this->actingAs($this->admin)->put(route('admin.email-templates.update', 'ticket_received'), [
        'subject' => 'Thanks {{requester.first_name}}',
        'body' => 'We got it.',
        'is_active' => false,
    ])->assertRedirect(route('admin.email-templates.index'));

    $template = EmailTemplate::for(EmailTemplateEvent::TicketReceived);
    expect($template->exists)->toBeTrue()->and($template->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.email-templates.destroy', 'ticket_received'));

    expect(EmailTemplate::for(EmailTemplateEvent::TicketReceived)->exists)->toBeFalse()
        ->and(EmailTemplate::for(EmailTemplateEvent::TicketReceived)->subject)->toBe(EmailTemplateEvent::TicketReceived->defaultSubject());
});

test('only admins manage email settings', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('admin.mailboxes.index'))->assertForbidden();
    $this->actingAs($agent)->get(route('admin.email-templates.index'))->assertForbidden();
});

test('IMAP hosts must be public internet addresses', function () {
    PublicNetwork::resolveUsing(fn (string $host): array => $host === 'mail.internal' ? ['192.168.0.20'] : []);

    foreach (['mail.internal', '127.0.0.1', '169.254.169.254'] as $host) {
        $this->actingAs($this->admin)->post(route('admin.mailboxes.store'), [
            'name' => 'Internal', 'address' => 'help@acme.test', 'driver' => 'imap',
            'imap_host' => $host, 'imap_port' => 993, 'imap_username' => 'help', 'imap_password' => 'secret',
        ])->assertSessionHasErrors('imap_host');
    }

    expect(Mailbox::query()->count())->toBe(0);
});
