<?php

namespace App\Http\Controllers;

use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Inbound\MailgunInbound;
use App\Domain\Mail\Inbound\PostmarkInbound;
use App\Domain\Mail\Jobs\ProcessInboundEmail;
use App\Domain\Mail\Models\Mailbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Inbound email webhooks from Postmark and Mailgun. Each mailbox has its own URL and secret.
 */
class InboundEmailController extends Controller
{
    /**
     * Postmark posts JSON; authenticate with the mailbox secret as the basic-auth password
     * (https://inbound:SECRET@your-host/inbound/postmark/ID). Secrets in the query string would
     * end up in access logs, so they aren't accepted.
     */
    public function postmark(Request $request, Mailbox $mailbox): JsonResponse
    {
        $this->ensureReceives($mailbox, MailboxDriver::Postmark);

        $secret = (string) $mailbox->inbound_secret;
        $given = (string) $request->getPassword();

        abort_unless($secret !== '' && hash_equals($secret, $given), 401);

        ProcessInboundEmail::dispatch($mailbox, PostmarkInbound::toInboundEmail($request->all()));

        return response()->json(['status' => 'queued']);
    }

    /**
     * Mailgun posts the parsed message as a form; it is signed with the webhook signing key,
     * which is stored as the mailbox secret.
     */
    public function mailgun(Request $request, Mailbox $mailbox): JsonResponse
    {
        $this->ensureReceives($mailbox, MailboxDriver::Mailgun);

        abort_unless(MailgunInbound::hasValidSignature($request, (string) $mailbox->inbound_secret), 401);

        // A signature is valid for 15 minutes; each token is only accepted once in that window.
        $replayKey = 'inbound:mailgun:'.hash('sha256', $request->string('token')->toString());

        if (! Cache::add($replayKey, true, now()->addMinutes(16))) {
            return response()->json(['status' => 'duplicate']);
        }

        ProcessInboundEmail::dispatch($mailbox, MailgunInbound::toInboundEmail($request));

        return response()->json(['status' => 'queued']);
    }

    private function ensureReceives(Mailbox $mailbox, MailboxDriver $driver): void
    {
        abort_unless($mailbox->is_active && $mailbox->driver === $driver, 404);
    }
}
