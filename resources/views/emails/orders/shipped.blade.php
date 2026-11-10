{{--
    "Tu pedido va en camino" (App\Mail\Commerce\OrderShippedMail): carrier
    and tracking number when captured by the admin, items and next steps.
    Var: $order (OrderSummary array).
--}}
@extends('emails.layouts.brand')

@section('content')
@include('emails.partials.heading', [
    'eyebrow' => 'Pedido enviado',
    'headline' => 'Tu pedido va en camino',
    'intro' => 'Enviamos tu pedido '.$order['number'].'. Te avisaremos cuando quede entregado.',
])
@if ($order['carrier'] || $order['tracking_number'])
<table role="presentation" class="fl-panel" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F8F7F3" style="width: 100%; background-color: #F8F7F3; border-radius: 4px; margin: 0 0 28px 0;">
<tr>
<td class="fl-text" style="padding: 20px 22px; font-size: 14px; line-height: 22px; color: #0B0B0C;">
<span style="display: block; margin-bottom: 4px; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #A88B62;">Seguimiento</span>
@if ($order['carrier'])
Paquetería: <strong style="font-weight: 600;">{{ $order['carrier'] }}</strong><br />
@endif
@if ($order['tracking_number'])
Número de guía: <strong style="font-weight: 600; letter-spacing: 0.5px;">{{ $order['tracking_number'] }}</strong>
@endif
</td>
</tr>
</table>
@endif
@include('emails.partials.order-items', ['order' => $order])
<div style="height: 20px; line-height: 20px; font-size: 0;">&nbsp;</div>
@include('emails.partials.order-details', ['order' => $order])
@include('emails.partials.next-steps', ['order' => $order])
@include('emails.partials.button', ['url' => $order['order_url'], 'label' => 'Ver mi pedido'])
@endsection
