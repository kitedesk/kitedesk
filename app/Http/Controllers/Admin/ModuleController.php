<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\Extensions\ModuleDefinition;
use App\Domain\Support\Extensions\ModuleException;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The modules KiteDesk packages provide, which admins install, switch on and off, and uninstall.
 */
class ModuleController extends Controller
{
    public function index(ModuleRegistry $modules): Response
    {
        return Inertia::render('admin/modules/index', [
            'modules' => collect($modules->all())
                ->map(fn (ModuleDefinition $module): array => [
                    ...$module->toArray(),
                    'requires' => array_map(fn (string $id): array => [
                        'id' => $id,
                        'name' => $modules->name($id),
                    ], $module->requires),
                    'installed' => $modules->isInstalled($module->id),
                    'needs_update' => $modules->needsUpdate($module->id),
                    'has_data' => $module->migrations !== null,
                    'enabled' => $modules->isEnabled($module->id),
                    'available' => $modules->isAvailable($module->id),
                ])
                ->values(),
        ]);
    }

    public function update(UpdateModuleRequest $request, ModuleRegistry $modules, string $module): RedirectResponse
    {
        abort_if($modules->find($module) === null, 404);

        try {
            match ($request->string('action')->toString()) {
                'install' => $modules->install($module, $request->user()),
                'enable' => $modules->enable($module, $request->user()),
                'disable' => $modules->disable($module, $request->user()),
                'uninstall' => $modules->uninstall($module, $request->user()),
                default => $modules->update($module, $request->user()),
            };
        } catch (ModuleException $exception) {
            return back()->withErrors(['module' => $exception->getMessage()]);
        }

        return back();
    }
}
