{{-- No blank lines or deep indentation: this is placed inside markdown. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
<tr>
<td class="kd-soft" style="padding:14px 16px;border-radius:10px;border:1px solid #eef0f2;border-left:3px solid {{ \App\Domain\Branding\Branding::current()->emailColor() }};background:#f8f9fb;">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
