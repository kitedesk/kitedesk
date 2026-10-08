<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('read notifications older than 90 days are pruned, unread ones are kept', function () {
    $user = User::factory()->create();
    $notification = fn (?string $readAt, string $createdAt): string => tap((string) Str::uuid(), fn (string $id) => DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => '{}',
        'read_at' => $readAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]));

    $oldRead = $notification(now()->subDays(91)->toDateTimeString(), now()->subDays(100)->toDateTimeString());
    $oldUnread = $notification(null, now()->subDays(100)->toDateTimeString());
    $recentRead = $notification(now()->subDays(10)->toDateTimeString(), now()->subDays(10)->toDateTimeString());

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DB::table('notifications')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$oldUnread, $recentRead])->sort()->values()->all())
        ->and(DB::table('notifications')->where('id', $oldRead)->exists())->toBeFalse();
});
