{{--
    Bulletproof call-to-action button (table-based, Outlook VML fallback):
    Negro Carbono fill, white label; inverts to Oro Legacy in dark mode.
    Vars: $url, $label.
--}}
<table role="presentation" class="fl-btn" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto;">
<tr>
<td align="center" bgcolor="#0B0B0C" style="background-color: #0B0B0C; border-radius: 4px;">
<!--[if mso]>
<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="8%" stroke="f" fillcolor="#0B0B0C">
<w:anchorlock/>
<center style="color:#FFFFFF;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;letter-spacing:1px;">{{ $label }}</center>
</v:roundrect>
<![endif]-->
<!--[if !mso]><!-->
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; padding: 15px 32px; font-family: {!! \App\Mail\Support\BrandMail::FONT_STACK !!}; font-size: 14px; font-weight: 700; line-height: 18px; letter-spacing: 1px; text-transform: uppercase; color: #FFFFFF; text-decoration: none; background-color: #0B0B0C; border: 1px solid #0B0B0C; border-radius: 4px;">{{ $label }}</a>
<!--<![endif]-->
</td>
</tr>
</table>
