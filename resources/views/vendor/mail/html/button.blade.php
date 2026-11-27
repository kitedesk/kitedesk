{{--
    A button in the brand colour. No blank lines or deep indentation: this is placed inside
    markdown, where those would turn it into text.
--}}
@props(['url', 'color' => 'primary', 'align' => 'left'])
@php($branding = \App\Domain\Branding\Branding::current())
@php($background = $color === 'error' ? '#dc2626' : $branding->emailColor())
@php($foreground = $color === 'error' ? '#ffffff' : $branding->emailTextColor())
<table role="presentation" cellpadding="0" cellspacing="0" align="{{ $align }}" style="margin:8px 0 22px;">
<tr>
<td style="border-radius:8px;background:{{ $background }};">
<a href="{{ $url }}" class="button" target="_blank" rel="noopener" style="display:inline-block;padding:11px 20px;font-size:14px;font-weight:600;line-height:1.2;text-decoration:none;border-radius:8px;background:{{ $background }};color:{{ $foreground }};">{!! $slot !!}</a>
</td>
</tr>
</table>
<div style="clear:both;"></div>
