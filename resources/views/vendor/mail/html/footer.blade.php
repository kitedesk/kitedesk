{{-- The KiteDesk layout draws the footer itself; kept for mail that uses this component directly. --}}
<div class="kd-muted" style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#9ca3af;">{{ Illuminate\Mail\Markdown::parse($slot) }}</div>
