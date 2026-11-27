{{-- A button in the brand colour, built as a table so Outlook draws it too. --}}
@props(['url', 'size' => 'md'])
@php
    $branding = \App\Domain\Branding\Branding::current();
    $padding = $size === 'sm' ? '8px 14px' : '11px 20px';
    $fontSize = $size === 'sm' ? '13px' : '14px';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
    <tr>
        <td style="border-radius:8px;background:{{ $branding->emailColor() }};">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block;padding:{{ $padding }};font-size:{{ $fontSize }};font-weight:600;line-height:1.2;text-decoration:none;border-radius:8px;background:{{ $branding->emailColor() }};color:{{ $branding->emailTextColor() }};">{{ $slot }}</a>
        </td>
    </tr>
</table>
