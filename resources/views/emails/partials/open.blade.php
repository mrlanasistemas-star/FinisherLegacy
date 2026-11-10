{{--
    Branded email shell, top half: document head (email-safe CSS, dark-mode
    rules), Blanco Hueso background, the official logo and the white card
    with its thin Oro Legacy rule. Closed by emails.partials.close. Shared by
    emails.layouts.brand (our Mailables) and vendor/mail/html/layout
    (framework notifications), so every email looks the same.
    Vars: $title (string), $preheader (string|null). The document language
    is the one the email is rendered in (the recipient's).
--}}
@php($brand = \App\Mail\Support\BrandMail::class)
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ app()->getLocale() }}" xml:lang="{{ app()->getLocale() }}">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no" />
<meta name="color-scheme" content="light dark" />
<meta name="supported-color-schemes" content="light dark" />
<title>{{ $title ?? config('app.name') }}</title>
<!--[if mso]>
<style type="text/css">body, table, td, a, p, h1, h2, h3 { font-family: Arial, sans-serif !important; }</style>
<![endif]-->
<style type="text/css">
body { margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
a { color: #8A6F4B; }
@media only screen and (max-width: 620px) {
  .fl-container { width: 100% !important; max-width: 100% !important; }
  .fl-pad { padding-left: 24px !important; padding-right: 24px !important; }
  .fl-btn a { display: block !important; }
  .fl-hide-sm { display: none !important; }
}
@media (prefers-color-scheme: dark) {
  .fl-bg { background-color: #0B0B0C !important; }
  .fl-card { background-color: #161618 !important; border-color: #2A2A2D !important; }
  .fl-text, .fl-text p, .fl-text h1, .fl-text h2, .fl-text td { color: #F8F7F3 !important; }
  .fl-muted { color: #B9B4A9 !important; }
  .fl-line { border-color: #2A2A2D !important; }
  .fl-btn td { background-color: #C9AE8B !important; }
  .fl-btn a { color: #0B0B0C !important; background-color: #C9AE8B !important; border-color: #C9AE8B !important; }
  .fl-panel { background-color: #1E1E21 !important; }
}
[data-ogsc] .fl-text, [data-ogsc] .fl-text p, [data-ogsc] .fl-text td { color: #F8F7F3 !important; }
[data-ogsc] .fl-muted { color: #B9B4A9 !important; }
</style>
{!! $head ?? '' !!}
</head>
<body class="fl-bg" style="margin: 0; padding: 0; width: 100%; background-color: {{ $brand::BONE }};">
@if (filled($preheader ?? null))
<div style="display: none; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all;">{{ $preheader }}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
@endif
<table role="presentation" class="fl-bg" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="{{ $brand::BONE }}" style="width: 100%; background-color: {{ $brand::BONE }};">
<tr>
<td align="center" style="padding: 32px 12px 40px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" class="fl-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 600px; max-width: 600px;">
<tr>
<td align="center" style="padding: 8px 0 28px 0;">
<a href="{{ $brand::homeUrl() }}" target="_blank" rel="noopener" style="text-decoration: none;"><img src="{{ $brand::logoUrl() }}" width="200" height="47" alt="Finisher Legacy · Make it last" style="display: block; width: 200px; max-width: 200px; height: auto; border: 0;" /></a>
</td>
</tr>
<tr>
<td class="fl-card" bgcolor="#FFFFFF" style="background-color: #FFFFFF; border: 1px solid #ECE7DC; border-radius: 6px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td height="3" bgcolor="{{ $brand::GOLD }}" style="height: 3px; line-height: 3px; font-size: 0; background-color: {{ $brand::GOLD }}; border-radius: 6px 6px 0 0;">&nbsp;</td>
</tr>
<tr>
<td class="fl-pad fl-text" style="padding: 40px 48px 44px 48px; font-family: {!! $brand::FONT_STACK !!}; font-size: 15px; line-height: 24px; color: {{ $brand::CARBON }};">
