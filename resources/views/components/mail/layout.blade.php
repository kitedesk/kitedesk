{{--
    The shell of every KiteDesk email: the brand's logo and accent colour, a white card on a
    soft background, and a footer. Table layout and inline styles, so it holds up in Gmail
    and Outlook; clients that support dark mode (Apple Mail, Outlook apps) get dark colours
    and the dark logo through the <style> block.

    Ticket emails use it directly; notifications reach it through the mail theme
    (resources/views/vendor/mail/html/message.blade.php).
--}}
@props([
    // The inbox's preview line, after the subject.
    'preheader' => null,
    // The "reply above this line" marker, on emails customers reply to.
    'marker' => null,
    // Why the person gets this email, under the footer.
    'reason' => null,
    // The ticket it's about, e.g. "Request #1042", in the footer.
    'reference' => null,
])
@php
    $branding = \App\Domain\Branding\Branding::current();
    $color = $branding->emailColor();
    $logo = $branding->emailLogoUrl();
    $logoDark = $branding->emailLogoDarkUrl();
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $branding->name() }}</title>
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; }
        a { color: {{ $color }}; }
        .kd-content p { margin: 0 0 14px; }
        .kd-content p:last-child { margin-bottom: 0; }
        .kd-content h1, .kd-content h2, .kd-content h3 { margin: 0 0 12px; line-height: 1.3; color: inherit; }
        .kd-content h1 { font-size: 20px; }
        .kd-content h2 { font-size: 17px; }
        .kd-content h3 { font-size: 15px; }
        .kd-content ul, .kd-content ol { margin: 0 0 14px; padding-left: 22px; }
        .kd-content blockquote { margin: 0 0 14px; padding: 2px 0 2px 14px; border-left: 3px solid #e6e8eb; color: #6b7280; }
        .kd-content pre { margin: 0 0 14px; padding: 12px 14px; background: #f5f6f8; border-radius: 8px; overflow-x: auto; font-size: 13px; }
        .kd-content code { font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
        .kd-content img { max-width: 100%; height: auto; }
        .kd-logo-dark { display: none; }
        @media only screen and (max-width: 620px) {
            .kd-pad { padding: 24px 20px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .kd-bg { background: #0f1115 !important; }
            .kd-card { background: #181b21 !important; border-color: #2a2e36 !important; }
            .kd-text { color: #e5e7eb !important; }
            .kd-muted { color: #9aa1ad !important; }
            .kd-soft { background: #20242b !important; border-color: #2a2e36 !important; }
            .kd-content blockquote { border-color: #2a2e36 !important; color: #9aa1ad !important; }
            .kd-content pre { background: #20242b !important; }
            @if ($logoDark)
            .kd-logo-light { display: none !important; }
            .kd-logo-dark { display: inline-block !important; }
            @endif
        }
    </style>
</head>
<body class="kd-bg" style="margin:0;padding:0;background:#f5f6f8;">
    @if ($preheader)
        <div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all;">{{ $preheader }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="kd-bg" style="background:#f5f6f8;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                    @if ($marker)
                        <tr>
                            <td class="kd-muted" style="padding:0 4px 16px;font-family:{{ $font }};font-size:12px;color:#9ca3af;">{{ $marker }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:0 4px 20px;">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="{{ $branding->name() }}" class="kd-logo-light" style="display:inline-block;height:auto;max-height:36px;max-width:180px;">
                                @if ($logoDark)
                                    <img src="{{ $logoDark }}" alt="{{ $branding->name() }}" class="kd-logo-dark" style="height:auto;max-height:36px;max-width:180px;mso-hide:all;">
                                @endif
                            @else
                                <span class="kd-text" style="font-family:{{ $font }};font-size:17px;font-weight:700;color:#111827;">{{ $branding->name() }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="kd-card" style="background:#ffffff;border:1px solid #e6e8eb;border-top:4px solid {{ $color }};border-radius:12px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="kd-pad kd-text kd-content" style="padding:32px 36px;font-family:{{ $font }};font-size:15px;line-height:1.6;color:#1f2937;">
                                        {{ $slot }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" class="kd-muted" style="padding:20px 4px 0;font-family:{{ $font }};font-size:12px;line-height:1.6;color:#9ca3af;">
                            @if ($reference){{ $reference }} · @endif{{ __('Sent by :name', ['name' => $branding->name()]) }}
                            @if ($branding->emailFooter)
                                <br>{!! nl2br(e($branding->emailFooter)) !!}
                            @endif
                            @if ($reason)
                                <br>{{ $reason }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
