{{-- Plain-text alternative of emails.orders.shipped. --}}
@extends('emails.layouts.brand-text')

@section('content')
PEDIDO ENVIADO

Tu pedido va en camino. Enviamos tu pedido {!! $order['number'] !!}. Te avisaremos cuando quede entregado.

@if ($order['carrier'])
Paquetería: {!! $order['carrier'] !!}
@endif
@if ($order['tracking_number'])
Número de guía: {!! $order['tracking_number'] !!}
@endif

@include('emails.orders.partials.summary-text', ['order' => $order])
@if ($order['needs_activation'])
PRÓXIMOS PASOS
Cuando recibas tu Finisher Legacy, actívalo desde la app o en {!! $order['activation_label'] !!}

@endif
Ver mi pedido: {!! $order['order_url'] !!}
@endsection
