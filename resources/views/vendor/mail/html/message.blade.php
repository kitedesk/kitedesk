{{-- Notification emails (Laravel's mail layout) with the installation's branding. --}}
@php($branding = \App\Domain\Branding\Branding::current())
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if ($branding->emailLogoUrl())
<img src="{{ $branding->emailLogoUrl() }}" class="logo" alt="{{ $branding->name() }}" style="height: auto; max-height: 48px; width: auto; max-width: 200px;">
@else
{{ $branding->name() }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $branding->name() }}. {{ __('All rights reserved.') }}
@if ($branding->emailFooter)

{{ $branding->emailFooter }}
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
