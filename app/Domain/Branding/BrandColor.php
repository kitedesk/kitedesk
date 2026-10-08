<?php

namespace App\Domain\Branding;

/**
 * The brand color an admin picked, turned into the theme tokens for light and dark mode.
 *
 * Colors are converted to OKLCH so the dark-mode shade and the focus ring keep the same hue,
 * the way the default indigo theme in resources/css/app.css is built.
 */
final readonly class BrandColor
{
    public const string DEFAULT_EMAIL = '#4f46e5';

    private const string DARK_TEXT = '#111827';

    public float $lightness;

    public float $chroma;

    public float $hue;

    /**
     * Relative luminance (WCAG), 0 for black to 1 for white.
     */
    public float $luminance;

    public function __construct(public string $hex)
    {
        [$red, $green, $blue] = array_map(
            fn (string $pair): float => self::linear(hexdec($pair) / 255),
            str_split(ltrim(strtolower($hex), '#'), 2),
        );

        $this->luminance = 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;

        $long = (0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue) ** (1 / 3);
        $medium = (0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue) ** (1 / 3);
        $short = (0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue) ** (1 / 3);

        $a = 1.9779984951 * $long - 2.4285922050 * $medium + 0.4505937099 * $short;
        $b = 0.0259040371 * $long + 0.7827717662 * $medium - 0.8086757660 * $short;

        $this->lightness = 0.2104542553 * $long + 0.7936177850 * $medium - 0.0040720468 * $short;
        $this->chroma = sqrt($a ** 2 + $b ** 2);
        $this->hue = fmod(rad2deg(atan2($b, $a)) + 360, 360);
    }

    public static function isValid(?string $hex): bool
    {
        return $hex !== null && preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
    }

    /**
     * Whether white text reads better on this color than dark text.
     */
    public function prefersWhiteText(): bool
    {
        return 1.05 / ($this->luminance + 0.05) >= ($this->luminance + 0.05) / 0.05;
    }

    /**
     * Text color for buttons in emails, where CSS variables aren't available.
     */
    public function foregroundHex(): string
    {
        return $this->prefersWhiteText() ? '#ffffff' : self::DARK_TEXT;
    }

    /**
     * CSS custom properties that override the defaults in app.css.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function cssVariables(): array
    {
        $foreground = $this->prefersWhiteText() ? 'oklch(0.985 0 0)' : $this->oklch(0.16, 0.03);
        $ring = $this->oklch(min($this->lightness + 0.19, 0.85), $this->chroma * 0.65);

        $darkLightness = max($this->lightness, 0.7);
        $darkChroma = min($this->chroma, 0.17);
        $dark = $this->oklch($darkLightness, $darkChroma);
        $darkRing = $this->oklch($darkLightness - 0.15, $darkChroma);
        $darkForeground = $this->oklch(0.16, min($this->chroma, 0.03));

        return [
            'light' => [
                '--primary' => strtolower($this->hex),
                '--primary-foreground' => $foreground,
                '--ring' => $ring,
                '--sidebar-primary' => strtolower($this->hex),
                '--sidebar-primary-foreground' => $foreground,
                '--sidebar-ring' => $ring,
            ],
            'dark' => [
                '--primary' => $dark,
                '--primary-foreground' => $darkForeground,
                '--ring' => $darkRing,
                '--sidebar-primary' => $dark,
                '--sidebar-primary-foreground' => $darkForeground,
                '--sidebar-ring' => $darkRing,
            ],
        ];
    }

    /**
     * The variables as a stylesheet for the page head.
     */
    public function stylesheet(): string
    {
        $variables = $this->cssVariables();
        $block = fn (array $tokens): string => implode('', array_map(
            fn (string $name, string $value): string => "{$name}:{$value};",
            array_keys($tokens),
            $tokens,
        ));

        return ':root{'.$block($variables['light']).'}.dark{'.$block($variables['dark']).'}';
    }

    private function oklch(float $lightness, float $chroma): string
    {
        return sprintf('oklch(%.3f %.3f %.1f)', $lightness, $chroma, $this->hue);
    }

    private static function linear(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
