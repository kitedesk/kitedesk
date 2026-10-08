<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Widget\WidgetSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveWidgetSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The support widget other websites embed to open tickets.
 */
class WidgetSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = WidgetSettings::current();

        return Inertia::render('admin/widget/edit', [
            'settings' => [
                'enabled' => $settings->enabled,
                'allowed_domains' => $settings->allowedDomains,
                'position' => $settings->position,
                'launcher_label' => $settings->launcherLabel,
                'greeting' => $settings->greeting,
            ],
            'scriptUrl' => route('widget.script'),
        ]);
    }

    public function update(SaveWidgetSettingsRequest $request): RedirectResponse
    {
        $label = $request->string('launcher_label')->trim()->toString();
        $greeting = $request->string('greeting')->trim()->toString();

        (new WidgetSettings(
            enabled: $request->boolean('enabled'),
            allowedDomains: $request->validated('allowed_domains'),
            position: $request->string('position')->toString(),
            launcherLabel: $label !== '' ? $label : null,
            greeting: $greeting !== '' ? $greeting : null,
        ))->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Website widget settings saved.')]);

        return back();
    }
}
