<?php

namespace App\Domain\Widget;

use App\Domain\Branding\BrandColor;
use App\Domain\Branding\Branding;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Support\Models\Setting;

/**
 * The support widget other websites embed: a button that opens a form to open a ticket.
 * Its colors come from the branding.
 */
final readonly class WidgetSettings
{
    public const string SETTING = 'widget';

    public const array POSITIONS = ['right', 'left'];

    /**
     * A host (`example.com`, `*.example.com`, `localhost:3000`), optionally with its scheme.
     */
    public const string DOMAIN_PATTERN = '/^(https?:\/\/)?(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)*(:\d{1,5})?$/';

    /**
     * @param  list<string>  $allowedDomains  sites that may show the widget; empty for any
     */
    public function __construct(
        public bool $enabled = false,
        public array $allowedDomains = [],
        public string $position = 'right',
        public ?string $launcherLabel = null,
        public ?string $greeting = null,
    ) {}

    public static function current(): self
    {
        $setting = Setting::get(self::SETTING);
        $setting = is_array($setting) ? $setting : [];
        $text = fn (string $key): ?string => is_string($setting[$key] ?? null) && $setting[$key] !== '' ? $setting[$key] : null;
        $position = $setting['position'] ?? null;

        return new self(
            enabled: (bool) ($setting['enabled'] ?? false),
            allowedDomains: array_values(array_filter(
                is_array($setting['allowed_domains'] ?? null) ? $setting['allowed_domains'] : [],
                fn (mixed $domain): bool => is_string($domain) && preg_match(self::DOMAIN_PATTERN, $domain) === 1,
            )),
            position: in_array($position, self::POSITIONS, true) ? $position : 'right',
            launcherLabel: $text('launcher_label'),
            greeting: $text('greeting'),
        );
    }

    public function save(): void
    {
        Setting::put(self::SETTING, [
            'enabled' => $this->enabled,
            'allowed_domains' => $this->allowedDomains,
            'position' => $this->position,
            'launcher_label' => $this->launcherLabel,
            'greeting' => $this->greeting,
        ]);
    }

    /**
     * Whether websites can show the widget: it is on and the plan includes it.
     */
    public function isAvailable(): bool
    {
        return $this->enabled && PlanLimits::allows(Feature::Widget);
    }

    /**
     * The `frame-ancestors` sources for the widget's frame.
     */
    public function frameAncestors(): string
    {
        return $this->allowedDomains === [] ? '*' : implode(' ', $this->allowedDomains);
    }

    /**
     * What the embed script needs to draw the button.
     *
     * @return array{frameUrl: string, position: string, label: string, color: string, textColor: string}
     */
    public function loaderConfig(): array
    {
        $color = new BrandColor(Branding::current()->primaryColor ?? BrandColor::DEFAULT_EMAIL);

        return [
            'frameUrl' => route('widget.frame'),
            'position' => $this->position,
            'label' => $this->launcherLabel ?? __('Help'),
            'color' => $color->hex,
            'textColor' => $color->foregroundHex(),
        ];
    }
}
