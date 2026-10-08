<?php

use App\Domain\Branding\Branding;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Tickets\Models\Ticket;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function brandingInput(array $overrides = []): array
{
    return [
        'name' => 'Acme Support',
        'primary_color' => '#0F766E',
        'email_footer' => 'Acme Inc. · Support hours 9–18',
        'help_title' => 'Hi, how can Acme help?',
        'header_links' => [['label' => 'Status', 'url' => 'https://status.acme.test']],
        'show_powered_by' => false,
        ...$overrides,
    ];
}

test('admins brand the installation', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.branding.update'), brandingInput(['logo' => UploadedFile::fake()->image('logo.png', 200, 50)]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $branding = Branding::current();
    expect($branding->name())->toBe('Acme Support')
        ->and($branding->primaryColor)->toBe('#0f766e')
        ->and($branding->headerLinks)->toBe([['label' => 'Status', 'url' => 'https://status.acme.test']])
        ->and($branding->showPoweredBy)->toBeFalse()
        ->and($branding->logo)->not->toBeNull();
    Storage::disk('local')->assertExists((string) $branding->logo);

    $this->get(route('help.index'))
        ->assertOk()
        ->assertSee('<title>Acme Support</title>', false)
        ->assertSee('--primary:#0f766e;', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Acme Support')
            ->where('branding.helpTitle', 'Hi, how can Acme help?')
            ->where('branding.logo', $branding->logoUrl())
            ->where('branding.showPoweredBy', false));
});

test('only admins can change branding', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('admin.branding.edit'))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.branding.update'), brandingInput())->assertForbidden();
});

test('colors must be hex and header links must be web addresses', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.branding.update'), brandingInput([
            'primary_color' => 'red',
            'header_links' => [['label' => 'Bad', 'url' => 'javascript:alert(1)']],
        ]))
        ->assertSessionHasErrors(['primary_color', 'header_links.0.url']);
});

test('replacing or removing a logo deletes the old file', function () {
    $this->actingAs($this->admin)->post(route('admin.branding.update'), brandingInput(['logo' => UploadedFile::fake()->image('first.png')]));
    $first = (string) Branding::current()->logo;

    $this->actingAs($this->admin)->post(route('admin.branding.update'), brandingInput(['logo' => UploadedFile::fake()->image('second.png')]));
    $second = (string) Branding::current()->logo;

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $this->actingAs($this->admin)->post(route('admin.branding.update'), brandingInput(['remove_logo' => true]));

    expect(Branding::current()->logo)->toBeNull();
    Storage::disk('local')->assertMissing($second);
});

test('logos are served publicly, and SVGs cannot run scripts', function () {
    $this->actingAs($this->admin)->post(route('admin.branding.update'), brandingInput([
        'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    ]))->assertSessionHasNoErrors();
    auth()->logout();

    $this->get((string) Branding::current()->logoUrl())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");

    Storage::disk('local')->put('branding/other.png', 'x');
    $this->get(route('branding.asset', ['file' => 'other.png']))->assertNotFound();
});

test('custom CSS cannot close its style element or import stylesheets', function () {
    $this->actingAs($this->admin)->post(route('admin.branding.update'), brandingInput([
        'custom_css' => "@import url(https://evil.test/x.css);\n.hero { color: red }</style><script>alert(1)</script>",
    ]));

    expect(Branding::current()->customCss)
        ->not->toContain('</style')
        ->not->toContain('@import')
        ->toContain('.hero { color: red }');
});

test('ticket emails carry the brand', function () {
    $this->actingAs($this->admin)->post(route('admin.branding.update'), brandingInput([
        'logo' => UploadedFile::fake()->image('logo.png'),
        'email_from_name' => 'Acme Help Desk',
    ]));
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $mail = new TicketEmail($ticket, EmailTemplateEvent::TicketReceived, $requester);

    expect($mail->render())
        ->toContain((string) Branding::current()->emailLogoUrl())
        ->toContain('background:#0f766e;color:#ffffff;')
        ->toContain('Acme Inc. · Support hours 9–18')
        ->and($mail->envelope()->from?->name)->toBe('Acme Help Desk');
});
