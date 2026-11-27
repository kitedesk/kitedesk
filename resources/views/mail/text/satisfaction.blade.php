{{-- Plain-text version: never shown as HTML, so nothing is escaped. --}}
@php($branding = \App\Domain\Branding\Branding::current())
{!! __('Hi :name,', ['name' => $name]) !!}

{!! __('Your request ":subject" was solved. How would you rate the support you received?', ['subject' => $subject]) !!}

@foreach ($stars as $star)
{!! trans_choice(':count star|:count stars', $star['score']) !!}: {!! $star['url'] !!}
@endforeach

{!! __('It takes one click, and you can add a comment on the next page.') !!}

--
{!! $reference !!} · {!! __('Sent by :name', ['name' => $branding->name()]) !!}
@if ($branding->emailFooter)
{!! $branding->emailFooter !!}
@endif
