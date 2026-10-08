<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Base for the REST API v1 and webhook payloads. These shapes are a public contract, kept
 * separate from the resources the app's own pages use: snake_case keys, timestamps in UTC
 * (ISO 8601) and related records as small `{id, ...}` objects.
 */
abstract class ApiResource extends JsonResource
{
    public static function time(?DateTimeInterface $value): ?string
    {
        return $value === null ? null : Carbon::instance($value)->toIso8601ZuluString();
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    public static function person(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public static function named(?Model $model): ?array
    {
        return $model === null ? null : ['id' => (int) $model->getKey(), 'name' => (string) $model->getAttribute('name')];
    }
}
