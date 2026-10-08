<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Models\Group;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Mail\Contracts\MailboxClient;
use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMailboxRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MailboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/mailboxes/index', [
            'mailboxes' => Mailbox::query()->orderByDesc('is_default')->orderBy('address')->get()->map(fn (Mailbox $mailbox): array => [
                'id' => $mailbox->id,
                'name' => $mailbox->name,
                'address' => $mailbox->address,
                'is_default' => $mailbox->is_default,
                'is_active' => $mailbox->is_active,
                'default_group_id' => $mailbox->default_group_id,
                'default_category_id' => $mailbox->default_category_id,
                'driver' => $mailbox->driver->value,
                'imap_host' => $mailbox->imap_host,
                'imap_port' => $mailbox->imap_port,
                'imap_encryption' => $mailbox->imap_encryption,
                'imap_username' => $mailbox->imap_username,
                'has_imap_password' => $mailbox->imap_password !== null,
                'imap_folder' => $mailbox->imap_folder,
                'delete_after_import' => $mailbox->delete_after_import,
                'inbound_secret' => $mailbox->inbound_secret,
                'webhook_url' => match ($mailbox->driver) {
                    MailboxDriver::Postmark => route('inbound.postmark', $mailbox),
                    MailboxDriver::Mailgun => route('inbound.mailgun', $mailbox),
                    MailboxDriver::Imap => null,
                },
                'last_polled_at' => $mailbox->last_polled_at?->toIso8601String(),
                'last_error' => $mailbox->last_error,
            ]),
            'drivers' => EnumOptions::for(MailboxDriver::class),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'categories' => TicketCatalog::categories(),
        ]);
    }

    public function store(SaveMailboxRequest $request): RedirectResponse
    {
        PlanLimits::ensureRoomFor(Limit::Mailboxes, 'address');

        DB::transaction(function () use ($request): void {
            $mailbox = Mailbox::query()->create([
                'inbound_secret' => Str::random(40),
                ...$request->mailboxAttributes(),
            ]);
            $this->keepSingleDefault($mailbox);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox added.')]);

        return back();
    }

    public function update(SaveMailboxRequest $request, Mailbox $mailbox): RedirectResponse
    {
        DB::transaction(function () use ($request, $mailbox): void {
            $mailbox->update($request->mailboxAttributes());
            $this->keepSingleDefault($mailbox);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox saved.')]);

        return back();
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        $mailbox->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox removed. Its tickets now reply from the default mailbox.')]);

        return back();
    }

    /**
     * Log in to the IMAP server and open the folder.
     */
    public function test(Mailbox $mailbox, MailboxClient $client): RedirectResponse
    {
        if ($mailbox->driver !== MailboxDriver::Imap) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Webhook mailboxes receive mail when your provider calls the webhook URL; there is no connection to test.')]);

            return back();
        }

        try {
            $client->test($mailbox);
            $mailbox->forceFill(['last_error' => null])->save();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to :address successfully.', ['address' => $mailbox->address])]);
        } catch (RuntimeException $exception) {
            $mailbox->forceFill(['last_error' => $exception->getMessage()])->save();
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    private function keepSingleDefault(Mailbox $mailbox): void
    {
        if ($mailbox->is_default) {
            Mailbox::query()->whereKeyNot($mailbox->id)->update(['is_default' => false]);
        }
    }
}
