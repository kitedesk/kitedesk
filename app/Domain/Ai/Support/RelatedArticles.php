<?php

namespace App\Domain\Ai\Support;

use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Published help center articles that may answer a ticket, found by keyword search on its
 * subject and the customer's latest message.
 */
final class RelatedArticles
{
    /**
     * Each article's text is trimmed to this many characters in a prompt.
     */
    public const int MAX_ARTICLE_CHARACTERS = 4_000;

    /**
     * @return Collection<int, Article>
     */
    public static function for(Ticket $ticket, int $limit = 5): Collection
    {
        $articles = new Collection;

        foreach (self::queries($ticket) as $query) {
            $articles = $articles->merge(
                Article::search($query)->query(fn ($query) => $query->published())->take($limit)->get(),
            )->unique('id');

            if ($articles->count() >= $limit) {
                break;
            }
        }

        return $articles->take($limit)->values();
    }

    /**
     * The chosen articles, published only, in the order given.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Article>
     */
    public static function find(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $articles = Article::query()->published()->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id): ?Article => $articles->get($id))->filter()->values();
    }

    /**
     * The articles as prompt text.
     *
     * @param  Collection<int, Article>  $articles
     */
    public static function asPrompt(Collection $articles): string
    {
        return $articles->map(fn (Article $article): string => 'Article: '.$article->title."\n"
            .'URL: '.route('help.articles.show', $article)."\n"
            .Str::limit(RichText::toPlainText($article->body), self::MAX_ARTICLE_CHARACTERS))
            ->join("\n\n---\n\n");
    }

    /**
     * Search terms, most specific first: the subject, then the latest customer message (the
     * database engine matches whole phrases, so a long message rarely matches as a whole).
     *
     * @return list<string>
     */
    private static function queries(Ticket $ticket): array
    {
        $message = TicketContext::latestCustomerMessage($ticket) ?? '';
        $keywords = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($ticket->subject.' '.$message)) ?: [])
            ->filter(fn (string $word): bool => mb_strlen($word) >= 4)
            ->countBy()->sortDesc()->keys()->take(6)->all();

        return array_values(array_filter([trim($ticket->subject), ...$keywords]));
    }
}
