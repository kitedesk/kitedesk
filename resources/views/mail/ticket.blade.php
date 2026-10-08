@php($branding = \App\Domain\Branding\Branding::current())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    @if ($marker)
        <p style="margin:0 0 16px;color:#9ca3af;font-size:12px;">{{ $marker }}</p>
    @endif

    <div style="max-width:640px;margin:0 auto 16px;text-align:center;">
        @if ($branding->emailLogoUrl())
            <img src="{{ $branding->emailLogoUrl() }}" alt="{{ $branding->name() }}" style="max-height:40px;max-width:200px;border:0;">
        @else
            <span style="font-size:16px;font-weight:600;color:#1f2937;">{{ $branding->name() }}</span>
        @endif
    </div>

    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;border:1px solid #e5e7eb;">
        <div style="font-size:15px;line-height:1.6;">{!! $intro !!}</div>

        @if ($reply)
            <div style="margin:20px 0;padding:16px 18px;border-left:3px solid {{ $branding->emailColor() }};background:#f9fafb;border-radius:6px;font-size:15px;line-height:1.6;">
                {!! $reply->body !!}
            </div>

            @foreach ($reply->secrets as $secret)
                <div style="margin:0 0 16px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:8px;">
                    <p style="margin:0 0 4px;font-weight:600;font-size:14px;">🔒 {{ $secret->label }}</p>
                    <p style="margin:0 0 10px;color:#6b7280;font-size:13px;">
                        {{ $secret->isRequest() ? __('Sign in to send it securely. It is stored encrypted and never sent by email.') : __('Sign in to view it. It can be viewed :count time(s) until :date.', ['count' => $secret->max_views, 'date' => $secret->expires_at->timezone($timezone)->isoFormat('LLL')]) }}
                    </p>
                    <a href="{{ $secret->url() }}" style="display:inline-block;background:{{ $branding->emailColor() }};color:{{ $branding->emailTextColor() }};text-decoration:none;padding:8px 14px;border-radius:8px;font-weight:600;font-size:13px;">{{ $secret->isRequest() ? __('Provide securely') : __('View secret') }}</a>
                </div>
            @endforeach
        @endif

        @if ($url)
            <p style="margin:24px 0 8px;">
                <a href="{{ $url }}" style="display:inline-block;background:{{ $branding->emailColor() }};color:{{ $branding->emailTextColor() }};text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;font-size:14px;">{{ $buttonLabel }}</a>
            </p>
        @endif

        @foreach ($history as $previous)
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;line-height:1.5;">
                <p style="margin:0 0 6px;font-weight:600;">
                    {{ $previous->author?->name ?? __('System') }} · {{ $previous->created_at?->timezone($timezone)->isoFormat('LLL') }}
                </p>
                <div>{!! $previous->body !!}</div>
            </div>
        @endforeach
    </div>

    <p style="max-width:640px;margin:16px auto 0;color:#9ca3af;font-size:12px;text-align:center;">
        {{ $reference }} · {{ $branding->name() }}
        @if ($branding->emailFooter)
            <br>{!! nl2br(e($branding->emailFooter)) !!}
        @endif
    </p>
</body>
</html>
