{{--
    "Pago confirmado" (App\Mail\Commerce\OrderPaidMail): order number, items
    with thumbnails, totals, delivery address, payment method, next steps
    and a button to the order. Var: $order (OrderSummary array).
--}}
@extends('emails.layouts.brand')

@section('content')
@include('emails.partials.heading', [
    'eyebrow' => 'Pago confirmado',
    'headline' => $order['first_name'] ? '¡Gracias, '.$order['first_name'].'!' : '¡Gracias por tu compra!',
    'intro' => 'Confirmamos el pago de tu pedido '.$order['number'].'. Este es el resumen de tu compra.',
])
@include('emails.partials.order-items', ['order' => $order])
@include('emails.partials.order-totals', ['order' => $order])
@include('emails.partials.order-details', ['order' => $order])
@include('emails.partials.next-steps', ['order' => $order])
@include('emails.partials.button', ['url' => $order['order_url'], 'label' => 'Ver mi pedido'])
@endsection
