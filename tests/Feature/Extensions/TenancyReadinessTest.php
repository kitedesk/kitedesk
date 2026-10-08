<?php

use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Branding\BrandAssets;
use App\Domain\Branding\Branding;
use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Mail\Support\MessageIds;
use App\Domain\Support\Extensions\KiteDesk;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('Message-IDs use the configured host and key, so another key cannot claim the ticket', function () {
    $ticket = Ticket::factory()->create();
    config(['kitedesk.mail.message_id_host' => 'acme.kitedesk.test', 'kitedesk.mail.message_id_key' => 'acme-key']);

    $messageId = MessageIds::forTicket($ticket);

    expect($messageId)->toEndWith('@acme.kitedesk.test')
        ->and(MessageIds::ticketIdFrom($messageId))->toBe($ticket->id);

    config(['kitedesk.mail.message_id_key' => 'other-key']);

    expect(MessageIds::ticketIdFrom($messageId))->toBeNull();
});

test('attachments and brand files are stored under the media prefix', function () {
    Storage::fake('local');
    config(['media-library.prefix' => 'acme', 'filesystems.default' => 'local']);

    $brandFile = BrandAssets::replace(null, UploadedFile::fake()->image('logo.png'), false);
    $media = TicketMessage::factory()->create()
        ->addMedia(UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'))
        ->toMediaCollection('attachments');

    expect($brandFile)->toStartWith('acme/branding/')
        ->and(BrandAssets::path(basename((string) $brandFile)))->toBe($brandFile)
        ->and($media->getPathRelativeToRoot())->toStartWith('acme/'.$media->id.'/');
});

test('flushing state forgets what was loaded for the previous installation', function () {
    $branding = app(Branding::class);

    KiteDesk::flushState();

    expect(app(Branding::class))->not->toBe($branding);
});

test('the create-admin command makes a verified administrator', function () {
    $this->artisan('kitedesk:create-admin', ['--name' => 'Ana', '--email' => 'Ana@Example.com', '--password' => 'a-Long-passw0rd!'])
        ->assertSuccessful();

    $admin = User::query()->where('email', 'ana@example.com')->sole();
    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(RoleCatalog::administrator()->users()->whereKey($admin->id)->exists())->toBeTrue();

    $this->artisan('kitedesk:create-admin', ['--name' => 'Ana', '--email' => 'ana@example.com', '--password' => 'a-Long-passw0rd!'])
        ->assertFailed();
});

test('the create-admin command respects the plan seats', function () {
    User::factory()->admin()->create();
    app()->instance(Entitlements::class, new class implements Entitlements
    {
        public function allows(Feature $feature): bool
        {
            return true;
        }

        public function limit(Limit $limit): ?int
        {
            return $limit === Limit::AgentSeats ? 1 : null;
        }
    });

    $this->artisan('kitedesk:create-admin', ['--name' => 'Bo', '--email' => 'bo@example.com', '--password' => 'a-Long-passw0rd!'])
        ->expectsOutputToContain('Your plan includes 1 team member.')
        ->assertFailed();
});
