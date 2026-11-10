{{--
    Email title block: small gold eyebrow, big Negro Carbono headline and
    an optional intro paragraph. Vars: $eyebrow, $headline, $intro (opt).
--}}
<p style="margin: 0 0 10px 0; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: #A88B62;">{{ $eyebrow }}</p>
<h1 class="fl-text" style="margin: 0 0 16px 0; font-family: {!! \App\Mail\Support\BrandMail::FONT_STACK !!}; font-size: 26px; line-height: 32px; font-weight: 700; letter-spacing: -0.3px; color: #0B0B0C;">{{ $headline }}</h1>
@if (filled($intro ?? null))
<p class="fl-text" style="margin: 0 0 24px 0; font-size: 15px; line-height: 24px; color: #2B2A28;">{{ $intro }}</p>
@endif
