@php($branding = \App\Domain\Branding\Branding::current())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto 16px;text-align:center;">
        @if ($branding->emailLogoUrl())
            <img src="{{ $branding->emailLogoUrl() }}" alt="{{ $branding->name() }}" style="max-height:40px;max-width:200px;border:0;">
        @else
            <span style="font-size:16px;font-weight:600;color:#1f2937;">{{ $branding->name() }}</span>
        @endif
    </div>

    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;border:1px solid #e5e7eb;text-align:center;">
        <p style="margin:0 0 8px;font-size:15px;line-height:1.6;text-align:left;">{{ __('Hi :name,', ['name' => $name]) }}</p>
        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;text-align:left;">
            {{ __('Your request ":subject" was solved. How would you rate the support you received?', ['subject' => $subject]) }}
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;border-collapse:separate;border-spacing:6px 0;">
            <tr>
                @foreach ($stars as $star)
                    <td>
                        <a href="{{ $star['url'] }}" title="{{ trans_choice(':count star|:count stars', $star['score']) }}" style="display:inline-block;min-width:44px;padding:8px 6px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:{{ $branding->emailColor() }};font-size:13px;font-weight:600;line-height:1.3;">
                            <span style="display:block;font-size:22px;color:#f59e0b;">&#9733;</span>{{ $star['score'] }}
                        </a>
                    </td>
                @endforeach
            </tr>
        </table>

        <p style="margin:12px auto 0;max-width:320px;color:#6b7280;font-size:12px;">
            <span style="float:left;">{{ __('Very unsatisfied') }}</span>
            <span style="float:right;">{{ __('Very satisfied') }}</span>
            <span style="display:block;clear:both;"></span>
        </p>

        <p style="margin:20px 0 0;color:#6b7280;font-size:13px;line-height:1.5;text-align:left;">
            {{ __('It takes one click, and you can add a comment on the next page.') }}
        </p>
    </div>

    <p style="max-width:640px;margin:16px auto 0;color:#9ca3af;font-size:12px;text-align:center;">
        {{ $reference }} · {{ $branding->name() }}
        @if ($branding->emailFooter)
            <br>{!! nl2br(e($branding->emailFooter)) !!}
        @endif
    </p>
</body>
</html>
