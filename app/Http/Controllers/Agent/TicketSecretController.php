<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Secrets\Actions\CreateSecret;
use App\Domain\Secrets\Actions\RevokeSecret;
use App\Domain\Secrets\Actions\ViewSecret;
use App\Domain\Secrets\Enums\SecretKind;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreSecretRequest;
use App\Http\Resources\SecretResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Secrets agents exchange with customers on a ticket.
 */
class TicketSecretController extends Controller
{
    public function store(StoreSecretRequest $request, Ticket $ticket, CreateSecret $createSecret): JsonResponse
    {
        $secret = $createSecret->handle(
            $ticket,
            $request->user(),
            SecretKind::from($request->string('kind')->toString()),
            $request->string('label')->toString(),
            $request->integer('max_views'),
            now()->addHours($request->integer('expires_in_hours')),
            $request->content(),
        );

        return response()->json([
            'secret' => (new SecretResource($secret->load('creator')))->resolve($request),
            'url' => $secret->url(),
        ], 201);
    }

    /**
     * The customer's answer to a request, decrypted. Counts as a view.
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

    public function destroy(Request $request, Secret $secret, RevokeSecret $revokeSecret): RedirectResponse
    {
        Gate::authorize('revoke', $secret);

        $revokeSecret->handle($secret, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Secret revoked. Its link no longer works.')]);

        return back();
    }
}
