{{--
    Ticket emails (new ticket, agent replies, solved notices, new ticket alerts for staff) and
    workflow emails. The admin's template text comes first, then the reply, a button to the
    ticket and the earlier messages.
--}}
@php($branding = \App\Domain\Branding\Branding::current())
<x-mail.layout :preheader="$preheader ?? null" :marker="$marker" :reason="$reason ?? null">
    <x-mail.meta :reference="$reference" :subject="$subject ?? null" />

    <div>{!! $intro !!}</div>

    @if ($reply)
        <div style="height:20px;line-height:20px;">&nbsp;</div>
        <x-mail.message :author="$reply->author?->name ?? $branding->name()" :date="$reply->created_at?->timezone($timezone)->isoFormat('LLL')">
            {!! $reply->body !!}
        </x-mail.message>

        @foreach ($reply->secrets as $secret)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                <tr>
                    <td class="kd-soft" style="padding:14px 16px;border:1px solid #e6e8eb;border-radius:10px;">
                        <p class="kd-text" style="margin:0 0 4px;font-weight:600;font-size:14px;color:#1f2937;">&#128274; {{ $secret->label }}</p>
                        <p class="kd-muted" style="margin:0 0 12px;color:#6b7280;font-size:13px;line-height:1.5;">
                            {{ $secret->isRequest() ? __('Sign in to send it securely. It is stored encrypted and never sent by email.') : __('Sign in to view it. It can be viewed :count time(s) until :date.', ['count' => $secret->max_views, 'date' => $secret->expires_at->timezone($timezone)->isoFormat('LLL')]) }}
                        </p>
                        <x-mail.button :url="$secret->url()" size="sm">{{ $secret->isRequest() ? __('Provide securely') : __('View secret') }}</x-mail.button>
                    </td>
                </tr>
            </table>
        @endforeach
    @endif

    @if ($url)
        <div style="height:12px;line-height:12px;">&nbsp;</div>
        <x-mail.button :url="$url">{{ $buttonLabel }}</x-mail.button>
    @endif

    @if ($history->isNotEmpty())
        <p class="kd-muted" style="margin:32px 0 12px;padding-top:20px;border-top:1px solid #eef0f2;font-size:12px;font-weight:600;letter-spacing:0.02em;text-transform:uppercase;color:#9ca3af;">{{ __('Earlier in this conversation') }}</p>
        @foreach ($history as $previous)
            <x-mail.message :author="$previous->author?->name ?? $branding->name()" :date="$previous->created_at?->timezone($timezone)->isoFormat('LLL')" muted>
                {!! $previous->body !!}
            </x-mail.message>
        @endforeach
    @endif
</x-mail.layout>
