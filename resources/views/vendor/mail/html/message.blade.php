{{--
    Notification emails (Laravel's markdown mail): staff alerts, account emails, module emails.
    They share the KiteDesk layout with ticket emails (resources/views/components/mail/layout.blade.php).
--}}
<x-mail.layout :preheader="$preheader ?? null">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
@isset($subcopy)
<div class="kd-muted" style="margin:28px 0 0;padding-top:16px;border-top:1px solid #eef0f2;font-size:12px;line-height:1.5;color:#9ca3af;">{!! Illuminate\Mail\Markdown::parse($subcopy) !!}</div>
@endisset
</x-mail.layout>
