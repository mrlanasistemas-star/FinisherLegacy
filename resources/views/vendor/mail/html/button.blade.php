{{--
    Finisher Legacy override of the markdown button: same bulletproof
    Negro Carbono button as our own emails (emails.partials.button),
    whatever color Laravel asks for (primary/success/error).
--}}
@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 28px 0;">
<tr>
<td align="{{ $align }}">
@include('emails.partials.button', ['url' => $url, 'label' => trim(strip_tags((string) $slot))])
</td>
</tr>
</table>
