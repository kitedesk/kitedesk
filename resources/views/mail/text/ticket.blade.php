{{-- Plain-text version: never shown as HTML, so nothing is escaped. --}}
@php($branding = \App\Domain\Branding\Branding::current())
@if ($marker)
{!! $marker !!}

@endif
{!! $reference !!}@if (! empty($subject)) · {!! $subject !!}@endif


{!! \App\Domain\Support\RichText::toPlainText($intro) !!}
@if ($reply)

{!! $reply->author?->name ?? $branding->name() !!} · {!! $reply->created_at?->timezone($timezone)->isoFormat('LLL') !!}
{!! \App\Domain\Support\RichText::toPlainText($reply->body) !!}
@foreach ($reply->secrets as $secret)

{!! $secret->label !!}: {!! $secret->url() !!}
@endforeach
@endif
@if ($url)

{!! $buttonLabel !!}: {!! $url !!}
@endif
@if ($history->isNotEmpty())

{!! __('Earlier in this conversation') !!}
@foreach ($history as $previous)

{!! $previous->author?->name ?? $branding->name() !!} · {!! $previous->created_at?->timezone($timezone)->isoFormat('LLL') !!}
{!! \App\Domain\Support\RichText::toPlainText($previous->body) !!}
@endforeach
@endif

--
{!! __('Sent by :name', ['name' => $branding->name()]) !!}
@if ($branding->emailFooter)
{!! $branding->emailFooter !!}
@endif
