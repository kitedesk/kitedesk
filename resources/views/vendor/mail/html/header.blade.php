{{-- The KiteDesk layout draws the logo itself; kept for mail that uses this component directly. --}}
@props(['url'])
<p style="margin:0 0 16px;font-weight:700;"><a href="{{ $url }}" style="text-decoration:none;">{!! $slot !!}</a></p>
