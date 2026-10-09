<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branding\BrandAssets;
use App\Domain\Branding\Branding;
use App\Domain\Support\DefaultLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandingRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The installation's name, language, logo, colors, email look and help center text.
 */
class BrandingController extends Controller
{
    public function edit(): Response
    {
        $branding = Branding::current();

        return Inertia::render('admin/branding/edit', [
            'settings' => [
                'name' => $branding->name(),
                'primary_color' => $branding->primaryColor,
                'email_from_name' => $branding->emailFromName,
                'email_footer' => $branding->emailFooter,
                'help_title' => $branding->helpTitle,
                'help_subtitle' => $branding->helpSubtitle,
                'header_links' => $branding->headerLinks,
                'portal_footer' => $branding->portalFooter,
                'show_powered_by' => $branding->showPoweredBy,
                'custom_css' => $branding->customCss,
                'locale' => DefaultLanguage::current(),
            ],
            'locales' => config('kitedesk.locales'),
            'assets' => [
                'logo' => $branding->logoUrl(),
                'logo_dark' => $branding->logoDarkUrl(),
                'favicon' => $branding->faviconUrl(),
            ],
            'defaultSenderName' => (string) config('mail.from.name'),
        ]);
    }

    public function update(SaveBrandingRequest $request): RedirectResponse
    {
        $current = Branding::current();
        $text = fn (string $key): ?string => $request->filled($key) ? trim($request->string($key)->toString()) : null;

        /** @var list<array{label: string, url: string}> $links */
        $links = array_values(array_map(
            fn (array $link): array => ['label' => trim((string) $link['label']), 'url' => trim((string) $link['url'])],
            (array) $request->input('header_links', []),
        ));

        (new Branding(
            name: $text('name') === config('app.name') ? null : $text('name'),
            primaryColor: $text('primary_color'),
            logo: BrandAssets::replace($current->logo, $request->file('logo'), $request->boolean('remove_logo')),
            logoDark: BrandAssets::replace($current->logoDark, $request->file('logo_dark'), $request->boolean('remove_logo_dark')),
            favicon: BrandAssets::replace($current->favicon, $request->file('favicon'), $request->boolean('remove_favicon')),
            emailFromName: $text('email_from_name'),
            emailFooter: $text('email_footer'),
            helpTitle: $text('help_title'),
            helpSubtitle: $text('help_subtitle'),
            headerLinks: $links,
            portalFooter: $text('portal_footer'),
            showPoweredBy: $request->boolean('show_powered_by', true),
            customCss: Branding::cleanCss($text('custom_css')),
        ))->save();

        if ($request->filled('locale')) {
            DefaultLanguage::save($request->string('locale')->toString());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding updated.')]);

        return back();
    }
}
