{{--
    Finisher Legacy override: the brand footer lives in the layout
    (emails.partials.close); an explicit <x-mail::footer> renders as a small
    muted note inside the card.
--}}
<p class="fl-muted" style="margin: 24px 0 0 0; font-size: 12px; line-height: 20px; color: #6B675F; text-align: center;">{{ Illuminate\Mail\Markdown::parse($slot) }}</p>
