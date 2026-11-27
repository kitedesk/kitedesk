{{-- The ticket an email is about: its reference and subject, as a small line. --}}
@props(['reference', 'subject' => null])
<p class="kd-muted" style="margin:0 0 18px;font-size:12px;line-height:1.5;color:#6b7280;">
    <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f1f3f5;color:#4b5563;font-weight:600;" class="kd-soft">{{ $reference }}</span>
    @if ($subject)
        <span style="margin-left:6px;">{{ $subject }}</span>
    @endif
</p>
