<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quick search used by the command palette, the requester picker and the editor's @ and # suggestions.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $number = ltrim($term, '#');

        // The @mention picker lists agents from the first keystroke, so it skips the length check and tickets.
        if ($request->boolean('staff_only')) {
            return response()->json(['tickets' => [], 'users' => $this->users($request, $term)]);
        }

        if (mb_strlen($term) < 2 && ! ctype_digit($number)) {
            return response()->json(['tickets' => [], 'users' => []]);
        }

        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->where(fn (Builder $query) => $query
                ->where('subject', 'like', "%{$term}%")
                ->orWhere('number', 'like', "{$number}%")
                ->when(ctype_digit($number), fn (Builder $byId) => $byId->orWhere('id', (int) $number)))
            ->latest('updated_at')
            ->limit(6)
            ->get(['id', 'number', 'subject', 'status', 'ticket_status_id', 'requester_id']);

        return response()->json([
            'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'number' => $ticket->reference(),
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'custom_status' => CustomStatuses::find($ticket->ticket_status_id)?->toSummary(),
                'requester_id' => $ticket->requester_id,
            ]),
            'users' => $this->users($request, $term),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, type: string}>
     */
    private function users(Request $request, string $term): array
    {
        return User::query()
            ->when($request->boolean('customers_only'), fn (Builder $query) => $query->customers())
            ->when($request->boolean('staff_only'), fn (Builder $query) => $query->staff())
            ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'email', 'type'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
            ])
            ->all();
    }
}
