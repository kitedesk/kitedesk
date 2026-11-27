<?php

namespace App\Http\Controllers;

use App\Domain\Jira\Jobs\ProcessJiraWebhook;
use App\Domain\Jira\Support\JiraSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Jira's webhook. Deliveries must carry an `X-Hub-Signature` made with the webhook
 * secret shown on the admin page. Jira delivers at least once, so each delivery identifier is
 * only processed the first time.
 */
class JiraWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = JiraSettings::current()->webhookSecret;
        $signature = (string) $request->header('X-Hub-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        if ($secret === null || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $identifier = (string) $request->header('X-Atlassian-Webhook-Identifier', '');

        if ($identifier !== '' && ! Cache::add('jira:webhook:'.$identifier, true, now()->addDay())) {
            return response()->json(['status' => 'duplicate']);
        }

        ProcessJiraWebhook::dispatch($payload);

        return response()->json(['status' => 'queued'], 202);
    }
}
