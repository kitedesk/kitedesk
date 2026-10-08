<?php

use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Widget\WidgetSettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  list<string>  $allowedDomains
 */
function enableWidget(array $allowedDomains = []): void
{
    (new WidgetSettings(enabled: true, allowedDomains: $allowedDomains))->save();
}

/**
 * @param  array<string, mixed>  $data
 */
function submitWidgetRequest(array $data = []): TestResponse
{
    return test()->postJson(route('widget.tickets.store'), [
        'name' => 'Jamie Visitor',
        'email' => 'Jamie@Example.com',
        'subject' => 'Checkout fails',
        'body' => "The card is declined.\n\nEvery time.",
        ...$data,
    ]);
}

test('the widget is hidden until an admin turns it on', function () {
    $this->get(route('widget.script'))->assertNotFound();
    $this->get(route('widget.frame'))->assertNotFound();
    submitWidgetRequest()->assertNotFound();

    expect(Ticket::query()->count())->toBe(0);
});

test('the embed script carries the settings and the frame address', function () {
    enableWidget();

    $response = $this->get(route('widget.script'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    expect($response->getContent())
        ->toContain('window.KiteDeskWidgetConfig = ')
        ->toContain(route('widget.frame'));
});

test('only the allowed websites may frame the widget', function () {
    enableWidget(['example.com', '*.example.org']);

    $this->get(route('widget.frame'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('widget/frame'))
        ->assertHeader('Content-Security-Policy', 'frame-ancestors example.com *.example.org')
        ->assertHeaderMissing('X-Frame-Options');

    // Every other page still refuses to be framed.
    $this->get(route('login'))->assertHeader('X-Frame-Options', 'DENY');
});

test('visitors open tickets from the widget and follow them by email', function () {
    Mail::fake();
    enableWidget();

    $response = submitWidgetRequest(['attachments' => [UploadedFile::fake()->create('receipt.pdf', 10)]])->assertCreated();

    $ticket = Ticket::query()->sole();
    $response->assertExactJson(['reference' => $ticket->reference()]);
    expect($ticket->channel)->toBe(TicketChannel::Widget)
        ->and($ticket->requester->email)->toBe('jamie@example.com')
        ->and($ticket->requester->isCustomer())->toBeTrue()
        ->and($ticket->messages()->sole()->body)->toBe('<p>The card is declined.</p><p>Every time.</p>')
        ->and($ticket->messages()->sole()->getMedia('attachments'))->toHaveCount(1);
});

test('an existing account email is flagged and a deactivated one is quietly ignored', function () {
    enableWidget();
    $existing = User::factory()->create(['email' => 'jamie@example.com']);

    submitWidgetRequest()->assertCreated();

    $ticket = Ticket::query()->sole();
    expect($ticket->requester_id)->toBe($existing->id)
        ->and($ticket->tags->pluck('name')->all())->toBe(['unverified_sender']);

    User::factory()->deactivated()->create(['email' => 'gone@example.com']);

    submitWidgetRequest(['email' => 'gone@example.com'])->assertCreated()->assertExactJson(['reference' => null]);
    expect(Ticket::query()->count())->toBe(1);
});

test('invalid requests come back as JSON errors', function () {
    enableWidget();

    submitWidgetRequest(['email' => 'not-an-email', 'subject' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'subject']);
});

test('admins set up the widget', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.widget.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/widget/edit')
            ->where('settings.enabled', false)
            ->where('scriptUrl', route('widget.script')));

    $this->actingAs($admin)->put(route('admin.widget.update'), [
        'enabled' => true,
        'allowed_domains' => [' Example.com/ ', 'shop.example.com', ''],
        'position' => 'left',
        'launcher_label' => 'Support',
        'greeting' => '',
    ])->assertSessionHasNoErrors();

    $settings = WidgetSettings::current();
    expect($settings->enabled)->toBeTrue()
        ->and($settings->allowedDomains)->toBe(['example.com', 'shop.example.com'])
        ->and($settings->position)->toBe('left')
        ->and($settings->launcherLabel)->toBe('Support')
        ->and($settings->greeting)->toBeNull();

    $this->actingAs($admin)->put(route('admin.widget.update'), [
        'enabled' => true,
        'allowed_domains' => ["example.com; script-src 'unsafe-inline'"],
        'position' => 'right',
    ])->assertSessionHasErrors('allowed_domains.0');
});

test('agents without the integrations permission cannot change the widget', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.widget.edit'))
        ->assertForbidden();
});
