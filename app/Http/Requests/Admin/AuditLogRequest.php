<?php

namespace App\Http\Requests\Admin;

use App\Domain\Audit\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class AuditLogRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', Rule::in(AuditLog::CATEGORIES)],
            'event' => ['nullable', 'string', 'max:50'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{category: string|null, event: string|null, user: int|null, from: string|null, to: string|null}
     */
    public function filters(): array
    {
        return [
            'category' => $this->validated('category'),
            'event' => $this->validated('event'),
            'user' => $this->filled('user') ? $this->integer('user') : null,
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
        ];
    }

    /**
     * Matching activity, newest first. "User" matches what they did and what was done to them.
     *
     * @return Builder<Activity>
     */
    public function activities(): Builder
    {
        $filters = $this->filters();

        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['category'], fn (Builder $query, string $category) => $query->where('log_name', $category))
            ->when($filters['event'], fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['user'], fn (Builder $query, int $user) => $query->where(fn (Builder $inner) => $inner
                ->where(fn (Builder $causer) => $causer->where('causer_type', User::class)->where('causer_id', $user))
                ->orWhere(fn (Builder $subject) => $subject->where('subject_type', User::class)->where('subject_id', $user))))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('created_at', '>=', $this->date('from')?->startOfDay()))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('created_at', '<=', $this->date('to')?->endOfDay()))
            ->orderByDesc('id');
    }
}
