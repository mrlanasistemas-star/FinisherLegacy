{{--
    Finisher Legacy override: the brand header is part of the layout; if a
    template still uses <x-mail::header> it renders the official logo.
--}}
@props(['url' => null])
<tr>
<td align="center" style="padding: 8px 0 28px 0;">
<a href="{{ $url ?? \App\Mail\Support\BrandMail::homeUrl() }}" style="display: inline-block; text-decoration: none;"><img src="{{ \App\Mail\Support\BrandMail::logoUrl() }}" width="200" alt="Finisher Legacy · Make it last" style="display: block; width: 200px; max-width: 200px; height: auto; border: 0;" /></a>
</td>
</tr>
