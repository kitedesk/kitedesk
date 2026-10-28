<?php

namespace App\Domain\Support\Extensions;

use App\Domain\Accounts\Support\PermissionRegistry;
use App\Domain\Support\Models\ModuleState;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The modules KiteDesk packages registered (`KiteDesk::module()`) and where each stands on this
 * installation. A module's code is always loaded, but nothing else happens until an admin
 * installs it: that runs its migrations. Enabling then switches on its routes, navigation,
 * permissions, listeners and jobs; disabling switches them off and keeps all its data.
 * Uninstalling, only possible while disabled, rolls its migrations back and so deletes its data.
 */
class ModuleRegistry
{
    private const string CACHE_KEY = 'modules:state';

    /**
     * @var array<string, ModuleDefinition>
     */
    private array $modules = [];

    /**
     * @var array{installed: list<string>, enabled: list<string>}|null
     */
    private ?array $stored = null;

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
     * Whether an admin has installed the module: its database changes are in place.
     */
    public function isInstalled(string $id): bool
    {
        return $this->find($id) !== null && in_array($id, $this->state()['installed'], true);
    }

    /**
     * Whether the module is installed, available to this installation and switched on.
     */
    public function isEnabled(string $id): bool
    {
        return $this->isAvailable($id) && $this->isInstalled($id) && in_array($id, $this->state()['enabled'], true);
    }

    /**
     * Whether the module has a newer version than the one installed, which may bring migrations.
     */
    public function needsUpdate(string $id): bool
    {
        $module = $this->find($id);

        return $module !== null && $this->isInstalled($id) && ($this->installedVersion($id) ?? '') !== $module->version;
    }

    public function installedVersion(string $id): ?string
    {
        return ModuleState::query()->whereKey($id)->value('installed_version');
    }

    /**
     * @return list<string>
     */
    public function enabledIds(): array
    {
        return array_values(array_filter(array_keys($this->modules), $this->isEnabled(...)));
    }

    /**
     * Install a module: run its migrations and any setup it needs. It stays switched off.
     *
     * @throws ModuleException
     */
    public function install(string $id, ?User $actor = null): void
    {
        $module = $this->find($id) ?? throw new ModuleException(__('That module is not installed.'));

        if (! $this->isAvailable($id)) {
            throw new ModuleException(__('Your plan does not include this module.'));
        }

        if ($this->isInstalled($id)) {
            return;
        }

        $missing = array_values(array_filter($module->requires, fn (string $required): bool => ! $this->isInstalled($required)));

        if ($missing !== []) {
            throw new ModuleException(__('Install :modules first.', ['modules' => $this->names($missing)]));
        }

        $this->withLock($id, function () use ($module): void {
            $this->migrate($module, false);
        });

        $this->store($module, ['installed' => true, 'installed_at' => now(), 'installed_version' => $module->version]);
        $this->record('module_installed', $module, $actor);

        if ($module->onInstall !== null) {
            ($module->onInstall)();
        }
    }

    /**
     * Uninstall a module: roll its migrations back, which deletes its data. It must be
     * switched off first, and no installed module may need it.
     *
     * @throws ModuleException
     */
    public function uninstall(string $id, ?User $actor = null): void
    {
        $module = $this->find($id) ?? throw new ModuleException(__('That module is not installed.'));

        if (! $this->isInstalled($id)) {
            return;
        }

        if (in_array($id, $this->state()['enabled'], true)) {
            throw new ModuleException(__('Disable :module before uninstalling it.', ['module' => $this->name($id)]));
        }

        $dependents = array_values(array_filter(
            array_keys($this->modules),
            fn (string $other): bool => in_array($id, $this->modules[$other]->requires, true) && $this->isInstalled($other),
        ));

        if ($dependents !== []) {
            throw new ModuleException(__('Uninstall :modules first.', ['modules' => $this->names($dependents)]));
        }

        $this->withLock($id, function () use ($module): void {
            $this->migrate($module, true);
        });

        $this->store($module, ['installed' => false, 'installed_at' => null, 'installed_version' => null, 'enabled' => false]);
        $this->record('module_uninstalled', $module, $actor);

        if ($module->onUninstall !== null) {
            ($module->onUninstall)();
        }
    }

    /**
     * Bring an installed module up to date: run the migrations its newer version added.
     *
     * @throws ModuleException
     */
    public function update(string $id, ?User $actor = null): void
    {
        $module = $this->find($id) ?? throw new ModuleException(__('That module is not installed.'));

        if (! $this->isInstalled($id)) {
            throw new ModuleException(__('Install :module first.', ['module' => $this->name($id)]));
        }

        $this->withLock($id, function () use ($module): void {
            $this->migrate($module, false);
        });

        $this->store($module, ['installed_version' => $module->version]);
        $this->record('module_updated', $module, $actor);
    }

    /**
     * Update every installed module that is behind, e.g. after a deploy.
     *
     * @return list<string> the ids that were updated
     *
     * @throws ModuleException
     */
    public function updateInstalled(): array
    {
        $updated = [];

        foreach (array_keys($this->modules) as $id) {
            if ($this->needsUpdate($id)) {
                $this->update($id);
                $updated[] = $id;
            }
        }

        return $updated;
    }

    /**
     * Switch a module on. It must be installed, and the modules it needs must be enabled.
     *
     * @throws ModuleException
     */
    public function enable(string $id, ?User $actor = null): void
    {
        $module = $this->find($id) ?? throw new ModuleException(__('That module is not installed.'));

        if (! $this->isAvailable($id)) {
            throw new ModuleException(__('Your plan does not include this module.'));
        }

        if (! $this->isInstalled($id)) {
            throw new ModuleException(__('Install :module first.', ['module' => $this->name($id)]));
        }

        if ($this->isEnabled($id)) {
            return;
        }

        $missing = $this->missingRequirements($module);

        if ($missing !== []) {
            throw new ModuleException(__('Enable :modules first.', ['modules' => $this->names($missing)]));
        }

        $this->store($module, ['enabled' => true, 'enabled_at' => now(), 'enabled_by' => $actor?->id]);
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

        if (! in_array($id, $this->state()['enabled'], true)) {
            return;
        }

        $dependents = array_values(array_filter(
            array_keys($this->modules),
            fn (string $other): bool => in_array($id, $this->modules[$other]->requires, true) && $this->isEnabled($other),
        ));

        if ($dependents !== []) {
            throw new ModuleException(__('Disable :modules first.', ['modules' => $this->names($dependents)]));
        }

        $this->store($module, ['enabled' => false]);
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
        $this->stored = null;
    }

    /**
     * @return array{installed: list<string>, enabled: list<string>}
     */
    private function state(): array
    {
        return $this->stored ??= Cache::rememberForever(self::CACHE_KEY, $this->loadState(...));
    }

    /**
     * @return array{installed: list<string>, enabled: list<string>}
     */
    private function loadState(): array
    {
        $ids = fn (string $column): array => array_values(ModuleState::query()->where($column, true)->pluck('id')->map(fn ($id): string => (string) $id)->all());

        return ['installed' => $ids('installed'), 'enabled' => $ids('enabled')];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function store(ModuleDefinition $module, array $changes): void
    {
        $state = ModuleState::query()->firstOrNew(['id' => $module->id]);

        $state->fill($changes)->save();

        Cache::forget(self::CACHE_KEY);
        $this->flush();
    }

    /**
     * Run the module's migrations, or roll them back. They are tracked in the `migrations` table
     * of the database in use, so each workspace keeps its own account of what was run.
     *
     * @throws ModuleException
     */
    private function migrate(ModuleDefinition $module, bool $rollBack): void
    {
        if ($module->migrations === null) {
            return;
        }

        $arguments = ['--path' => $module->migrations, '--realpath' => true, '--force' => true];

        try {
            $exitCode = $rollBack
                ? Artisan::call('migrate:rollback', [...$arguments, '--step' => 100000])
                : Artisan::call('migrate', $arguments);
        } catch (Throwable $exception) {
            report($exception);

            throw new ModuleException(__('The module’s database changes failed: :error', ['error' => $exception->getMessage()]));
        }

        if ($exitCode !== 0) {
            throw new ModuleException(__('The module’s database changes failed: :error', ['error' => trim(Artisan::output())]));
        }
    }

    /**
     * @param  callable(): void  $callback
     *
     * @throws ModuleException
     */
    private function withLock(string $id, callable $callback): void
    {
        $lock = Cache::lock("module-migrations:{$id}", 600);

        if (! $lock->get()) {
            throw new ModuleException(__('That module is being changed right now. Try again in a moment.'));
        }

        try {
            $callback();
        } finally {
            $lock->release();
        }
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
