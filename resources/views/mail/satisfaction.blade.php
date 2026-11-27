{{-- The satisfaction survey: one click on a score opens the page to confirm it and comment. --}}
@php($branding = \App\Domain\Branding\Branding::current())
<x-mail.layout :preheader="__('How would you rate the support you received?')" :reason="__('You get this because your request was solved.')">
    <x-mail.meta :reference="$reference" :subject="$subject" />

    <p>{{ __('Hi :name,', ['name' => $name]) }}</p>
    <p>{{ __('Your request ":subject" was solved. How would you rate the support you received?', ['subject' => $subject]) }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px auto 8px;border-collapse:separate;border-spacing:6px 0;">
        <tr>
            @foreach ($stars as $star)
                <td align="center">
                    <a href="{{ $star['url'] }}" title="{{ trans_choice(':count star|:count stars', $star['score']) }}" class="kd-soft" style="display:inline-block;min-width:46px;padding:10px 6px 8px;border:1px solid #e6e8eb;border-radius:10px;background:#ffffff;text-decoration:none;color:{{ $branding->emailColor() }};font-size:13px;font-weight:600;line-height:1.3;">
                        <span style="display:block;font-size:22px;color:#f59e0b;">&#9733;</span>{{ $star['score'] }}
                    </a>
                </td>
            @endforeach
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;width:290px;">
        <tr>
            <td class="kd-muted" align="left" style="font-size:12px;color:#6b7280;">{{ __('Very unsatisfied') }}</td>
            <td class="kd-muted" align="right" style="font-size:12px;color:#6b7280;">{{ __('Very satisfied') }}</td>
        </tr>
    </table>

    <p class="kd-muted" style="margin:24px 0 0;color:#6b7280;font-size:13px;line-height:1.5;">{{ __('It takes one click, and you can add a comment on the next page.') }}</p>
</x-mail.layout>
