/**
 * Client copy of App\Domain\Branding\BrandColor, for the live preview on the branding page.
 * The server's version is the one applied to pages.
 */

export const isHexColor = (value: string | null | undefined): value is string =>
    typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value);

const linear = (channel: number) =>
    channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;

function analyse(hex: string) {
    const [red, green, blue] = [1, 3, 5].map((start) =>
        linear(parseInt(hex.slice(start, start + 2), 16) / 255),
    );

    const long = Math.cbrt(
        0.4122214708 * red + 0.5363325363 * green + 0.0514459929 * blue,
    );
    const medium = Math.cbrt(
        0.2119034982 * red + 0.6806995451 * green + 0.1073969566 * blue,
    );
    const short = Math.cbrt(
        0.0883024619 * red + 0.2817188376 * green + 0.6299787005 * blue,
    );
    const a = 1.9779984951 * long - 2.428592205 * medium + 0.4505937099 * short;
    const b = 0.0259040371 * long + 0.7827717662 * medium - 0.808675766 * short;

    return {
        luminance: 0.2126 * red + 0.7152 * green + 0.0722 * blue,
        lightness:
            0.2104542553 * long + 0.793617785 * medium - 0.0040720468 * short,
        chroma: Math.hypot(a, b),
        hue: ((((Math.atan2(b, a) * 180) / Math.PI) % 360) + 360) % 360,
    };
}

/**
 * Text color for a button in this color (white or near-black, whichever reads better).
 */
export function foregroundHex(hex: string): string {
    const { luminance } = analyse(hex);

    return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05
        ? '#ffffff'
        : '#111827';
}

export function contrastWithWhite(hex: string): number {
    return 1.05 / (analyse(hex).luminance + 0.05);
}

/**
 * The theme tokens for a brand color, in light and dark mode.
 */
export function brandVariables(hex: string): {
    light: Record<string, string>;
    dark: Record<string, string>;
} {
    const { luminance, lightness, chroma, hue } = analyse(hex);
    const oklch = (l: number, c: number) =>
        `oklch(${l.toFixed(3)} ${c.toFixed(3)} ${hue.toFixed(1)})`;
    const whiteText = 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05;
    const foreground = whiteText ? 'oklch(0.985 0 0)' : oklch(0.16, 0.03);
    const ring = oklch(Math.min(lightness + 0.19, 0.85), chroma * 0.65);
    const darkLightness = Math.max(lightness, 0.7);
    const darkChroma = Math.min(chroma, 0.17);
    const dark = oklch(darkLightness, darkChroma);
    const darkRing = oklch(darkLightness - 0.15, darkChroma);
    const darkForeground = oklch(0.16, Math.min(chroma, 0.03));

    return {
        light: {
            '--primary': hex.toLowerCase(),
            '--primary-foreground': foreground,
            '--ring': ring,
            '--sidebar-primary': hex.toLowerCase(),
            '--sidebar-primary-foreground': foreground,
            '--sidebar-ring': ring,
        },
        dark: {
            '--primary': dark,
            '--primary-foreground': darkForeground,
            '--ring': darkRing,
            '--sidebar-primary': dark,
            '--sidebar-primary-foreground': darkForeground,
            '--sidebar-ring': darkRing,
        },
    };
}
