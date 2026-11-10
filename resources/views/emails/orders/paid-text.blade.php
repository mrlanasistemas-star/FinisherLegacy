{{-- Plain-text alternative of emails.orders.paid. --}}
@extends('emails.layouts.brand-text')

@section('content')
PAGO CONFIRMADO

{!! $order['first_name'] ? '¡Gracias, '.$order['first_name'].'!' : '¡Gracias por tu compra!' !!}

Confirmamos el pago de tu pedido {!! $order['number'] !!}. Este es el resumen de tu compra.

@include('emails.orders.partials.summary-text', ['order' => $order])
@if ($order['needs_activation'])
PRÓXIMOS PASOS
Cuando recibas tu Finisher Legacy, actívalo desde la app o en {!! $order['activation_label'] !!}

@endif
Ver mi pedido: {!! $order['order_url'] !!}
@endsection
