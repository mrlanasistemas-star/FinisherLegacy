{{--
    "Pedido entregado" (App\Mail\Commerce\OrderDeliveredMail): confirms the
    delivery and points to activation for physical Finisher Legacy pieces.
    Var: $order (OrderSummary array).
--}}
@extends('emails.layouts.brand')

@section('content')
@include('emails.partials.heading', [
    'eyebrow' => 'Pedido entregado',
    'headline' => $order['needs_activation'] ? 'Tu Finisher Legacy ya está contigo' : 'Tu pedido fue entregado',
    'intro' => 'Marcamos como entregado tu pedido '.$order['number'].'.',
])
@include('emails.partials.next-steps', ['order' => $order])
@include('emails.partials.order-items', ['order' => $order])
<div style="height: 20px; line-height: 20px; font-size: 0;">&nbsp;</div>
@if ($order['needs_activation'])
@include('emails.partials.button', ['url' => $order['activation_url'], 'label' => 'Activar mi Finisher Legacy'])
@else
@include('emails.partials.button', ['url' => $order['order_url'], 'label' => 'Ver mi pedido'])
@endif
@endsection
