<?php

namespace App\Domain\Branding;

use App\Domain\Support\Models\Setting;
use Illuminate\Support\Str;

/**
 * How the installation presents itself: name, logo, colors, emails and the help center.
 *
 * Unset values fall back to the stock KiteDesk look. Read it with Branding::current(),
 * which is resolved once per request (or queued job).
 */
final readonly class Branding
{
    public const string SETTING = 'branding';

    public const int MAX_HEADER_LINKS = 5;

    /**
     * @param  list<array{label: string, url: string}>  $headerLinks
     */
    public function __construct(
        public ?string $name = null,
        public ?string $primaryColor = null,
        public ?string $logo = null,
        public ?string $logoDark = null,
        public ?string $favicon = null,
        public ?string $emailFromName = null,
        public ?string $emailFooter = null,
        public ?string $helpTitle = null,
        public ?string $helpSubtitle = null,
        public array $headerLinks = [],
        public ?string $portalFooter = null,
        public bool $showPoweredBy = true,
        public ?string $customCss = null,
        public string $version = '0',
    ) {}

    public static function current(): self
    {
        return app(self::class);
    }

    /**
     * Read the saved settings (use current() instead, which is cached per request).
     */
    public static function load(): self
    {
        $setting = Setting::get(self::SETTING);
        $setting = is_array($setting) ? $setting : [];
        $text = fn (string $key): ?string => is_string($setting[$key] ?? null) && $setting[$key] !== '' ? $setting[$key] : null;

        $links = array_values(array_filter(
            is_array($setting['header_links'] ?? null) ? $setting['header_links'] : [],
            fn (mixed $link): bool => is_array($link) && is_string($link['label'] ?? null) && is_string($link['url'] ?? null),
        ));

        return new self(
            name: $text('name'),
            primaryColor: BrandColor::isValid($text('primary_color')) ? $text('primary_color') : null,
            logo: $text('logo'),
            logoDark: $text('logo_dark'),
            favicon: $text('favicon'),
            emailFromName: $text('email_from_name'),
            emailFooter: $text('email_footer'),
            helpTitle: $text('help_title'),
            helpSubtitle: $text('help_subtitle'),
            headerLinks: array_map(fn (array $link): array => ['label' => (string) $link['label'], 'url' => (string) $link['url']], $links),
            portalFooter: $text('portal_footer'),
            showPoweredBy: ($setting['show_powered_by'] ?? true) !== false,
            customCss: $text('custom_css'),
            version: $text('version') ?? '0',
        );
    }

    /**
     * Store the settings with a new version, so browsers and Inertia pick up the change.
     */
    public function save(): void
    {
        Setting::put(self::SETTING, [
            'name' => $this->name,
            'primary_color' => $this->primaryColor !== null ? strtolower($this->primaryColor) : null,
            'logo' => $this->logo,
            'logo_dark' => $this->logoDark,
            'favicon' => $this->favicon,
            'email_from_name' => $this->emailFromName,
            'email_footer' => $this->emailFooter,
            'help_title' => $this->helpTitle,
            'help_subtitle' => $this->helpSubtitle,
            'header_links' => $this->headerLinks,
            'portal_footer' => $this->portalFooter,
            'show_powered_by' => $this->showPoweredBy,
            'custom_css' => $this->customCss,
            'version' => Str::random(8),
        ]);

        app()->forgetInstance(self::class);
    }

    /**
     * Admin-written CSS for the help center, kept from closing its <style> element or
     * pulling in other stylesheets.
     */
    public static function cleanCss(?string $css): ?string
    {
        if ($css === null) {
            return null;
        }

        $css = (string) preg_replace(['~</?\s*style~i', '~@import[^;]*;?~i'], '', $css);

        return trim($css) === '' ? null : $css;
    }

    public function name(): string
    {
        return $this->name ?? (string) config('app.name');
    }

    /**
     * Sender name for emails sent from a mailbox without its own name.
     */
    public function senderName(): string
    {
        return $this->emailFromName ?? $this->name ?? (string) config('mail.from.name');
    }

    public function color(): ?BrandColor
    {
        return $this->primaryColor !== null ? new BrandColor($this->primaryColor) : null;
    }

    /**
     * Theme overrides for the page head; empty for the stock colors.
     */
    public function stylesheet(): string
    {
        return $this->color()?->stylesheet() ?? '';
    }

    public function logoUrl(): ?string
    {
        return $this->assetUrl($this->logo);
    }

    public function logoDarkUrl(): ?string
    {
        return $this->assetUrl($this->logoDark);
    }

    public function faviconUrl(): ?string
    {
        return $this->assetUrl($this->favicon);
    }

    /**
     * The logo for emails. SVG is left out because Gmail and Outlook don't display it.
     */
    public function emailLogoUrl(): ?string
    {
        return $this->logo !== null && ! str_ends_with(strtolower($this->logo), '.svg') ? $this->logoUrl() : null;
    }

    /**
     * Button and accent color for emails.
     */
    public function emailColor(): string
    {
        return $this->primaryColor ?? BrandColor::DEFAULT_EMAIL;
    }

    public function emailTextColor(): string
    {
        return ($this->color() ?? new BrandColor(BrandColor::DEFAULT_EMAIL))->foregroundHex();
    }

    /**
     * What every page needs to draw the brand (the name is shared separately).
     *
     * @return array<string, mixed>
     */
    public function sharedProps(): array
    {
        return [
            'logo' => $this->logoUrl(),
            'logoDark' => $this->logoDarkUrl(),
            'stylesheet' => $this->stylesheet(),
            'helpTitle' => $this->helpTitle,
            'helpSubtitle' => $this->helpSubtitle,
            'headerLinks' => $this->headerLinks,
            'portalFooter' => $this->portalFooter,
            'showPoweredBy' => $this->showPoweredBy,
            'customCss' => $this->customCss,
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        return $path !== null ? route('branding.asset', ['file' => basename($path), 'v' => $this->version]) : null;
    }
}
