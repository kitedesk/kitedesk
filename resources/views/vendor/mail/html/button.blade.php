@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php($branding = \App\Domain\Branding\Branding::current())
@php($brandStyle = $color === 'primary' && $branding->primaryColor !== null
    ? "background-color: {$branding->emailColor()}; border-color: {$branding->emailColor()}; color: {$branding->emailTextColor()};"
    : null)
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" @if ($brandStyle) style="{{ $brandStyle }}" @endif>{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
