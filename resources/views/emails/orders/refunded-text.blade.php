{{-- Plain-text alternative of emails.orders.refunded. --}}
@extends('emails.layouts.brand-text')

@section('content')
REEMBOLSO PROCESADO

{!! $full ? 'Reembolsamos el total de tu pedido '.$order['number'].'.' : 'Reembolsamos una parte de tu pedido '.$order['number'].'.' !!}

{!! $full ? 'Monto reembolsado' : 'Monto reembolsado a la fecha' !!}: {!! $refund !!}
@if ($order['payment_method'])
A tu método de pago original: {!! $order['payment_method'] !!}
@endif

El dinero vuelve al mismo método con el que pagaste. El tiempo en que se refleja en tu estado de cuenta depende de tu banco.

@include('emails.orders.partials.summary-text', ['order' => $order])
Ver mi pedido: {!! $order['order_url'] !!}
@endsection
