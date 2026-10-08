{{--
    Replaces Laravel's error layout, so every Blade error page (used when the Inertia error
    page can't render, see App\Domain\Support\ErrorPages) looks like the app. Self-contained on
    purpose: no Vite assets, database or settings, since any of them may be what failed.
--}}
@php
    $code = trim($__env->yieldContent('code'));
    $content = \App\Domain\Support\ErrorPages::content((int) $code, $exception ?? null);
    $heading = $content['title'] ?? trim($__env->yieldContent('title'));
    $description = $content['description'] ?? trim($__env->yieldContent('message'));
    $action = match ((int) $code) {
        403, 404 => ['label' => __('Go back'), 'onclick' => 'history.length > 1 ? history.back() : location.assign(\'/\')'],
        419, 429, 500, 503 => ['label' => __('Try again'), 'onclick' => 'location.reload()'],
        default => null,
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $heading }}</title>
        <style>
            :root {
                color-scheme: light dark;
                --background: oklch(1 0 0);
                --foreground: oklch(0.145 0 0);
                --muted-foreground: oklch(0.556 0 0);
                --primary: oklch(0.51 0.2 274);
                --primary-foreground: oklch(0.985 0 0);
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --background: oklch(0.145 0 0);
                    --foreground: oklch(0.985 0 0);
                    --muted-foreground: oklch(0.708 0 0);
                    --primary: oklch(0.7 0.15 274);
                    --primary-foreground: oklch(0.16 0.03 274);
                }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px 16px 96px;
                background: var(--background);
                color: var(--foreground);
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                -webkit-font-smoothing: antialiased;
                text-align: center;
            }

            main { max-width: 24rem; }

            .code {
                margin: 0;
                font-size: clamp(4.5rem, 18vw, 8rem);
                font-weight: 700;
                line-height: 1;
                letter-spacing: -0.05em;
            }

            h1 {
                margin: 24px 0 8px;
                font-size: 1.5rem;
                font-weight: 600;
                letter-spacing: -0.025em;
            }

            p { margin: 0; color: var(--muted-foreground); font-size: 0.875rem; line-height: 1.6; }

            button {
                margin-top: 32px;
                height: 36px;
                padding: 0 16px;
                border: 0;
                border-radius: calc(0.625rem - 2px);
                background: var(--primary);
                color: var(--primary-foreground);
                font: inherit;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
            }

            button:hover { opacity: 0.9; }
        </style>
    </head>
    <body>
        <main>
            @if ($code !== '')
                <p class="code" aria-hidden="true" style="color: var(--foreground)">{{ $code }}</p>
            @endif
            <h1>{{ $heading }}</h1>
            <p>{{ $description }}</p>
            @if ($action)
                <button type="button" onclick="{{ $action['onclick'] }}">{{ $action['label'] }}</button>
            @endif
        </main>
    </body>
</html>
