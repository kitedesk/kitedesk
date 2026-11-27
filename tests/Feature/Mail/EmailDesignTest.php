<?php

use App\Domain\Branding\Branding;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketAssigned;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    (new Branding(name: 'Acme Support', primaryColor: '#0f766e', emailFooter: 'Acme Inc. · 1 Main Street'))->save();
    app()->forgetScopedInstances();

    $this->requester = User::factory()->create(['name' => 'Jane Cooper']);
    $this->agent = User::factory()->agent()->create(['name' => 'Sam Agent']);
    $this->ticket = Ticket::factory()->for($this->requester, 'requester')->create(['subject' => 'Export fails']);
});

test('ticket replies use the branded layout, with the reply, the earlier messages and a text version', function () {
    $this->ticket->messages()->create(['author_id' => $this->requester->id, 'body' => '<p>Exports time out.</p>', 'is_internal' => false, 'channel' => 'portal']);
    $reply = app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>A fix is live.</p>');

    $mail = new TicketEmail($this->ticket, EmailTemplateEvent::AgentReply, $this->requester, $reply);
    $html = $mail->render();

    expect($html)
        ->toContain('border-top:4px solid #0f766e')
        ->toContain('Acme Support')
        ->toContain('Acme Inc. · 1 Main Street')
        ->toContain('Please type your reply above this line')
        ->toContain('Export fails')
        ->toContain('Earlier in this conversation')
        ->and(strpos($html, 'A fix is live.'))->toBeLessThan(strpos($html, 'Exports time out.'));

    $mail->assertSeeInText('A fix is live.');
    $mail->assertSeeInText('Exports time out.');
    $mail->assertSeeInText(route('portal.tickets.show', $this->ticket));
    $mail->assertDontSeeInText('<p>');
});

test('staff copies have no reply marker', function () {
    $mail = new TicketEmail($this->ticket, EmailTemplateEvent::AgentNewTicketAlert, $this->agent);

    expect($mail->render())->not->toContain('Please type your reply above this line')
        ->toContain('member of the support team');
});

test('notifications, including account emails, use the same layout and brand', function () {
    $assigned = (string) (new TicketAssigned($this->ticket, $this->agent))->toMail($this->agent)->render();
    $reset = (string) (new ResetPassword('token'))->toMail($this->requester)->render();

    foreach ([$assigned, $reset] as $html) {
        expect($html)
            ->toContain('border-top:4px solid #0f766e')
            ->toContain('background:#0f766e')
            ->toContain('Acme Inc. · 1 Main Street')
            ->toContain('Sent by Acme Support');
    }

    expect($assigned)->toContain('<h1')->toContain('Export fails')
        ->and($reset)->toContain('Regards,<br>')->toContain('Acme Support');
});

test('subjects and names are escaped in both layouts', function () {
    $this->ticket->update(['subject' => '<script>alert(1)</script>']);

    $ticketEmail = (new TicketEmail($this->ticket, EmailTemplateEvent::TicketReceived, $this->requester))->render();
    $assigned = (string) (new TicketAssigned($this->ticket, $this->agent))->toMail($this->agent)->render();

    expect($ticketEmail)->not->toContain('<script>')
        ->and($assigned)->not->toContain('<script>');
});

test('the preview shows the real email with unsaved values, framed by this site only', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.email-preview', ['type' => 'notification', 'color' => '#ff5500', 'footer' => 'Draft footer']))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src * data:; style-src 'unsafe-inline'; frame-ancestors 'self'")
        ->assertSee('border-top:4px solid #ff5500', false)
        ->assertSee('Draft footer');

    $this->actingAs($admin)
        ->get(route('admin.email-preview', ['type' => 'ticket', 'event' => 'agent_reply', 'body' => 'Hi {{ requester.first_name }}, <b>update</b>']))
        ->assertOk()
        ->assertSee('Hi Jane, &lt;b&gt;update&lt;/b&gt;', false)
        ->assertSee('Earlier in this conversation');

    // Nothing was saved.
    expect(Branding::load()->primaryColor)->toBe('#0f766e');
});

test('the preview needs the branding or email permission', function () {
    $this->actingAs($this->agent)
        ->get(route('admin.email-preview'))
        ->assertForbidden();
});
