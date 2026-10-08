<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Secrets\Actions\SubmitSecret;
use App\Domain\Secrets\Actions\ViewSecret;
use App\Domain\Secrets\Models\Secret;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SubmitSecretRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page a secret link opens, for the signed-in requester and people copied on the
 * ticket. Opening it reveals nothing: the secret is only decrypted (and the view counted)
 * when they ask for it, and requests are only answered when they submit.
 */
class SecretController extends Controller
{
    public function show(Request $request, Secret $secret): Response|RedirectResponse
    {
        // Agents following a link land on the ticket, where the secret's card is.
        if ($request->user()->isStaff() && $request->user()->can('view', $secret->ticket)) {
            return to_route('agent.tickets.show', $secret->ticket);
        }

        Gate::authorize('open', $secret);

        return Inertia::render('guest/secret', [
            'secret' => [
                'token' => $secret->token,
                'kind' => $secret->kind->value,
                'label' => $secret->label,
                'status' => $secret->status()->value,
                'views_left' => max(0, $secret->max_views - $secret->views),
                'expires_at' => $secret->expires_at->toIso8601String(),
                'requested_by' => $secret->isRequest() ? $secret->creator?->name : null,
            ],
            'ticket' => [
                'number' => $secret->ticket->reference(),
                'subject' => $secret->ticket->subject,
                'url' => route('portal.tickets.show', $secret->ticket),
            ],
            'maxLength' => (int) config('kitedesk.secrets.max_length'),
        ]);
    }

    public function submit(SubmitSecretRequest $request, Secret $secret, SubmitSecret $submitSecret): JsonResponse
    {
        if (! $submitSecret->handle($secret, $request->user(), $request->string('secret')->toString())) {
            return response()->json(['message' => __('This request can no longer be answered.')], 410);
        }

        return response()->json(['status' => $secret->status()->value]);
    }

    /**
     * The shared secret, decrypted. Counts as a view.
     */
    public function reveal(Request $request, Secret $secret, ViewSecret $viewSecret): JsonResponse
    {
        Gate::authorize('view', $secret);

        $payload = $viewSecret->handle($secret, $request->user());

        if ($payload === null) {
            return response()->json(['message' => __('This secret can no longer be viewed.')], 410);
        }

        return response()->json($payload);
    }
}
