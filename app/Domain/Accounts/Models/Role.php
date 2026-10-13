<?php

namespace App\Domain\Accounts\Models;

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Audit\Concerns\Auditable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A staff role: a named set of permissions plus which tickets its members can see.
 * System roles are created by `RoleCatalog`; their names are stored in English and
 * translated when shown.
 *
 * @property string|null $description
 * @property TicketAccess $ticket_access
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    use Auditable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ticket_access' => 'all',
        'is_system' => false,
    ];

    /**
     * Roles have no fillable list (they come from spatie/laravel-permission): what admins set.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly(['name', 'description', 'ticket_access'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function displayName(): string
    {
        return $this->is_system ? __($this->name) : $this->name;
    }

    public function displayDescription(): ?string
    {
        if ($this->description === null || ! $this->is_system) {
            return $this->description;
        }

        return __($this->description);
    }

    /**
     * The administrator role always holds every permission and cannot be changed.
     */
    public function isAdministrator(): bool
    {
        return $this->is_system && $this->name === RoleCatalog::ADMINISTRATOR;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_access' => TicketAccess::class,
            'is_system' => 'boolean',
        ];
    }
}
