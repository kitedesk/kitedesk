<?php

namespace App\Domain\Support\Extensions;

use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Support\Models\ModuleState;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The modules KiteDesk packages registered (`KiteDesk::module()`) and which of them admins have
 * switched on. A module's code is always loaded and its migrations always run; being enabled
 * only decides whether its routes, navigation, permissions, listeners and jobs are live.
 * Disabling keeps all the module's data.
 */
class ModuleRegistry
{
    private const string CACHE_KEY = 'modules:enabled';

    /**
     * @var array<string, ModuleDefinition>
     */
    private array $modules = [];

    /**
     * @var list<string>|null
     */
    private ?array $enabledIds = null;

    public function __construct(private readonly ModuleAvailability $availability) {}

    public function register(ModuleDefinition $module): void
    {
        $this->modules[$module->id] = $module;

        $permissions = $module->permissions;

        if ($permissions !== []) {
            app(PermissionRegistry::class)->register(...array_map(
                fn ($definition) => $definition->forModule($module->id),
                $permissions,
            ));
        }
    }

    /**
     * @return array<string, ModuleDefinition>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function find(string $id): ?ModuleDefinition
    {
        return $this->modules[$id] ?? null;
    }

    public function isAvailable(string $id): bool
    {
        $module = $this->find($id);

        return $module !== null && $this->availability->isAvailable($module);
    }

    /**
     * Whether the module is installed, available to this installation and switched on.
     */
    public function isEnabled(string $id): bool
    {
        return $this->isAvailable($id) && in_array($id, $this->storedEnabledIds(), true);
    }

    /**
     * @return list<string>
     */
    public function enabledIds(): array
    {
        return array_values(array_filter(array_keys($this->modules), $this->isEnabled(...)));
    }

    /**
     * Switch a module on. Fails, with a message for the admin, when it isn't available or
     * needs another module that is not enabled.
     *
     * @throws ModuleException
     */
    public function enable(string $id, ?User $actor = null): void
    {
        $module = $this->find($id) ?? throw new ModuleException(__('That module is not installed.'));

        if (! $this->isAvailable($id)) {
            throw new ModuleException(__('Your plan does not include this module.'));
        }

        if ($this->isEnabled($id)) {
            return;
        }

        $missing = $this->missingRequirements($module);

        if ($missing !== []) {
            throw new ModuleException(__('Enable :modules first.', ['modules' => $this->names($missing)]));
        }

        $this->store($module, true, $actor);
        $this->record('module_enabled', $module, $actor);

        if ($module->onEnable !== null) {
            ($module->onEnable)();
        }
    }

    /**
     * Switch a module off, keeping its data. Fails when another enabled module needs it.
     *
     * @throws ModuleException
     */
    public function disable(string $id, ?User $actor = null): void
    {
        $module = $this->find($id) ?? throw new ModuleException(__('That module is not installed.'));

        if (! in_array($id, $this->storedEnabledIds(), true)) {
            return;
        }

        $dependents = array_values(array_filter(
            array_keys($this->modules),
            fn (string $other): bool => in_array($id, $this->modules[$other]->requires, true) && $this->isEnabled($other),
        ));

        if ($dependents !== []) {
            throw new ModuleException(__('Disable :modules first.', ['modules' => $this->names($dependents)]));
        }

        $this->store($module, false, $actor);
        $this->record('module_disabled', $module, $actor);

        if ($module->onDisable !== null) {
            ($module->onDisable)();
        }
    }

    /**
     * The modules this one needs that are not enabled yet.
     *
     * @return list<string>
     */
    public function missingRequirements(ModuleDefinition $module): array
    {
        return array_values(array_filter($module->requires, fn (string $required): bool => ! $this->isEnabled($required)));
    }

    /**
     * Forget what was read from the database, e.g. when a worker picks up its next job or the
     * hosted edition switches workspace.
     */
    public function flush(): void
    {
        $this->enabledIds = null;
    }

    /**
     * @return list<string>
     */
    private function storedEnabledIds(): array
    {
        return $this->enabledIds ??= array_values(Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => ModuleState::query()->where('enabled', true)->pluck('id')->all(),
        ));
    }

    private function store(ModuleDefinition $module, bool $enabled, ?User $actor): void
    {
        $state = ModuleState::query()->firstOrNew(['id' => $module->id]);

        $state->fill([
            'enabled' => $enabled,
            'installed_version' => $module->version,
            ...($enabled ? ['enabled_at' => now(), 'enabled_by' => $actor?->id] : []),
        ])->save();

        Cache::forget(self::CACHE_KEY);
        $this->flush();
    }

    private function record(string $event, ModuleDefinition $module, ?User $actor): void
    {
        activity()->useLog('admin')->causedBy($actor)
            ->withProperties(['module' => $module->id, 'name' => $module->name, 'version' => $module->version])
            ->event($event)
            ->log($event);
    }

    /**
     * A module's name for messages, translated; the id when it isn't installed.
     */
    public function name(string $id): string
    {
        $module = $this->find($id);

        return $module === null ? $id : __($module->name);
    }

    /**
     * @param  list<string>  $ids
     */
    private function names(array $ids): string
    {
        return implode(', ', array_map($this->name(...), $ids));
    }
}
