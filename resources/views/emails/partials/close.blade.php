{{--
    Branded email shell, bottom half: closes the white card and renders the
    footer (thin gold rule, "MAKE IT LAST" signature, support email, legal
    links). Opened by emails.partials.open.
--}}
@php($brand = \App\Mail\Support\BrandMail::class)
@php($support = $brand::supportEmail())
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td align="center" class="fl-pad" style="padding: 32px 48px 0 48px; font-family: {!! $brand::FONT_STACK !!};">
<table role="presentation" width="64" cellpadding="0" cellspacing="0" border="0" align="center" style="width: 64px;">
<tr><td height="1" bgcolor="{{ $brand::GOLD }}" style="height: 1px; line-height: 1px; font-size: 0; background-color: {{ $brand::GOLD }};">&nbsp;</td></tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="center" style="padding: 22px 0 18px 0;">
<img src="{{ $brand::signatureUrl() }}" width="140" height="11" alt="MAKE IT LAST" style="display: block; width: 140px; max-width: 140px; height: auto; border: 0;" />
</td>
</tr>
<tr>
<td align="center" class="fl-muted" style="font-family: {!! $brand::FONT_STACK !!}; font-size: 12px; line-height: 20px; color: #6B675F;">
¿Necesitas ayuda? Escríbenos a <a href="mailto:{{ $support }}" style="color: #8A6F4B; text-decoration: underline;">{{ $support }}</a>
</td>
</tr>
<tr>
<td align="center" class="fl-muted" style="padding-top: 10px; font-family: {!! $brand::FONT_STACK !!}; font-size: 12px; line-height: 20px; color: #6B675F;">
<a href="{{ $brand::privacyUrl() }}" target="_blank" rel="noopener" style="color: #6B675F; text-decoration: underline;">Aviso de privacidad</a>
&nbsp;&middot;&nbsp;
<a href="{{ $brand::termsUrl() }}" target="_blank" rel="noopener" style="color: #6B675F; text-decoration: underline;">Términos y condiciones</a>
&nbsp;&middot;&nbsp;
<a href="{{ $brand::homeUrl() }}" target="_blank" rel="noopener" style="color: #6B675F; text-decoration: underline;">{{ parse_url($brand::homeUrl(), PHP_URL_HOST) ?: 'finisherlegacy.com' }}</a>
</td>
</tr>
<tr>
<td align="center" class="fl-muted" style="padding-top: 10px; font-family: {!! $brand::FONT_STACK !!}; font-size: 11px; line-height: 18px; color: #8C877D;">
&copy; {{ date('Y') }} {{ config('app.name', 'Finisher Legacy') }}. Todos los derechos reservados.
</td>
</tr>
</table>
</td>
</tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>
