{{-- Plain-text notification emails, with the same footer as the HTML version. --}}
@php($branding = \App\Domain\Branding\Branding::current())
{{ $slot }}

@isset($subcopy)
{{ $subcopy }}

@endisset
--
{{ __('Sent by :name', ['name' => $branding->name()]) }}
@if ($branding->emailFooter)
{{ $branding->emailFooter }}
@endif
