{{-- For mail that uses Laravel's layout directly: the KiteDesk layout instead. --}}
<x-mail.layout>
{!! Illuminate\Mail\Markdown::parse($slot) !!}
{!! $subcopy ?? '' !!}
</x-mail.layout>
