<?php

namespace App\Domain\Support\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a module is on this installation: installed (its database changes are in place) and
 * enabled (switched on). One row per module, created when an admin first installs it; a
 * module without a row is not installed.
 *
 * @property string $id
 * @property bool $installed
 * @property CarbonImmutable|null $installed_at
 * @property bool $enabled
 * @property CarbonImmutable|null $enabled_at
 * @property int|null $enabled_by
 * @property string|null $installed_version
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['id', 'installed', 'installed_at', 'enabled', 'enabled_at', 'enabled_by', 'installed_version', 'settings'])]
class ModuleState extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<User, $this>
     */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installed' => 'boolean',
            'installed_at' => 'immutable_datetime',
            'enabled' => 'boolean',
            'enabled_at' => 'immutable_datetime',
            'settings' => 'array',
        ];
    }
}
