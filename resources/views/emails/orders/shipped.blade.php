{{--
    "Tu pedido va en camino" (App\Mail\Commerce\OrderShippedMail): carrier
    and tracking number when captured by the admin, items and next steps.
    Var: $order (OrderSummary array).
--}}
@extends('emails.layouts.brand')

@section('content')
@include('emails.partials.heading', [
    'eyebrow' => __('Pedido enviado'),
    'headline' => __('Tu pedido va en camino'),
    'intro' => __('Enviamos tu pedido :number. Te avisaremos cuando quede entregado.', ['number' => $order['number']]),
])
@if ($order['carrier'] || $order['tracking_number'])
<table role="presentation" class="fl-panel" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F8F7F3" style="width: 100%; background-color: #F8F7F3; border-radius: 4px; margin: 0 0 28px 0;">
<tr>
<td class="fl-text" style="padding: 20px 22px; font-size: 14px; line-height: 22px; color: #0B0B0C;">
<span style="display: block; margin-bottom: 4px; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #A88B62;">{{ __('Seguimiento') }}</span>
@if ($order['carrier'])
{{ __('Paquetería') }}: <strong style="font-weight: 600;">{{ $order['carrier'] }}</strong><br />
@endif
@if ($order['tracking_number'])
{{ __('Número de guía') }}: <strong style="font-weight: 600; letter-spacing: 0.5px;">{{ $order['tracking_number'] }}</strong>
@endif
</td>
</tr>
</table>
@endif
@include('emails.partials.order-items', ['order' => $order])
<div style="height: 20px; line-height: 20px; font-size: 0;">&nbsp;</div>
@include('emails.partials.order-details', ['order' => $order])
@include('emails.partials.next-steps', ['order' => $order])
@include('emails.partials.button', ['url' => $order['order_url'], 'label' => __('Ver mi pedido')])
@endsection
