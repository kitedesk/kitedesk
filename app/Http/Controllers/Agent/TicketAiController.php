<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Ai\Agents\DraftImprover;
use App\Domain\Ai\Agents\ReplyDrafter;
use App\Domain\Ai\Agents\TicketSummarizer;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Ai\Support\AssistantStream;
use App\Domain\Ai\Support\RelatedArticles;
use App\Domain\Ai\Support\TicketContext;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\GenerateAiDraftRequest;
use App\Http\Requests\Agent\ImproveAiDraftRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The AI assistant on the agent ticket page. Nothing here changes the ticket: drafts go to the
 * composer for the agent to review and send.
 */
class TicketAiController extends Controller
{
    /**
     * A summary of the ticket. Cached per latest message and model, so a new message (or a
     * model change) gets a fresh one.
     */
    public function summary(Request $request, Ticket $ticket): JsonResponse
    {
        $settings = AiSettings::current();
        abort_unless($settings->features()['summaries'], 404);

        $latestMessageId = (int) $ticket->messages()->max('id');
        $key = 'ai.summary.'.$ticket->id.'.'.$latestMessageId.'.'.md5((string) $settings->model);

        if ($request->boolean('refresh')) {
            Cache::forget($key);
        }

        try {
            $summary = Cache::remember($key, CarbonImmutable::now()->addDays(7), fn (): array => [
                'summary' => trim((new TicketSummarizer)->prompt(TicketContext::for($ticket, $settings->contextMessages))->text),
                'generated_at' => CarbonImmutable::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI ticket summary failed.', ['ticket' => $ticket->id, 'exception' => $exception]);

            return response()->json(['message' => AssistantStream::errorMessage()], 502);
        }

        return response()->json([...$summary, 'message_id' => $latestMessageId]);
    }

    /**
     * Help center articles that may answer the ticket.
     */
    public function articles(Ticket $ticket): JsonResponse
    {
        abort_unless(AiSettings::current()->features()['drafts'], 404);

        return response()->json([
            'articles' => RelatedArticles::for($ticket)->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'url' => route('help.articles.show', $article),
            ])->all(),
        ]);
    }

    public function draft(GenerateAiDraftRequest $request, Ticket $ticket): StreamedResponse
    {
        $settings = AiSettings::current();
        abort_unless($settings->features()['drafts'], 404);

        $articles = RelatedArticles::find($request->articleIds());
        $instruction = $request->validated('instruction');

        $prompt = implode("\n\n", array_filter([
            TicketContext::for($ticket, $settings->contextMessages),
            $articles->isNotEmpty() ? "Help center articles:\n\n".RelatedArticles::asPrompt($articles) : null,
            filled($instruction) ? 'Instruction from the agent: '.$instruction : null,
        ]));

        return AssistantStream::response(
            (new ReplyDrafter($request->user()->name, internal: $request->validated('mode') === 'internal'))->stream($prompt),
        );
    }

    public function improve(ImproveAiDraftRequest $request, Ticket $ticket): StreamedResponse
    {
        $settings = AiSettings::current();
        abort_unless($settings->features()['improve'], 404);

        $prompt = "Ticket, for context only:\n\n".TicketContext::for($ticket, min($settings->contextMessages, 10))
            ."\n\nDraft to rewrite:\n\n".RichText::toPlainText($request->string('draft')->toString());

        return AssistantStream::response(
            (new DraftImprover($request->string('action')->toString(), $request->validated('instruction')))->stream($prompt),
        );
    }
}
