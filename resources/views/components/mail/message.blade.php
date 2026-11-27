{{-- One message of a ticket's conversation: who wrote it and when, then what they wrote. --}}
@props(['author', 'date' => null, 'muted' => false])
@php($accent = \App\Domain\Branding\Branding::current()->emailColor())
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
    <tr>
        <td class="kd-soft" style="padding:14px 16px;border-radius:10px;border:1px solid #eef0f2;border-left:3px solid {{ $muted ? '#d1d5db' : $accent }};background:{{ $muted ? '#ffffff' : '#f8f9fb' }};">
            <p class="kd-muted" style="margin:0 0 8px;font-size:12px;line-height:1.4;color:#6b7280;">
                <strong class="kd-text" style="color:#374151;">{{ $author }}</strong>@if ($date) · {{ $date }}@endif
            </p>
            <div class="{{ $muted ? 'kd-muted' : 'kd-text' }}" style="font-size:{{ $muted ? '14px' : '15px' }};line-height:1.6;color:{{ $muted ? '#6b7280' : '#1f2937' }};">{{ $slot }}</div>
        </td>
    </tr>
</table>
