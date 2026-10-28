<?php

namespace App\Domain\Support\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether an installed module is switched on. One row per module, created the first time an
 * admin enables it; a module without a row is disabled.
 *
 * @property string $id
 * @property bool $enabled
 * @property CarbonImmutable|null $enabled_at
 * @property int|null $enabled_by
 * @property string|null $installed_version
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['id', 'enabled', 'enabled_at', 'enabled_by', 'installed_version', 'settings'])]
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
            'enabled' => 'boolean',
            'enabled_at' => 'immutable_datetime',
            'settings' => 'array',
        ];
    }
}
