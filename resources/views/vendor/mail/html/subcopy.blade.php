{{-- Finisher Legacy override: small print under the body (e.g. the raw action URL). --}}
<table class="subcopy fl-line" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin-top: 28px; border-top: 1px solid #ECE7DC;">
<tr>
<td class="fl-muted" style="padding-top: 20px; font-size: 12px; line-height: 19px; color: #6B675F; word-break: break-all;">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
