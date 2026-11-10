{{-- Plain-text alternative of emails.orders.delivered. --}}
@extends('emails.layouts.brand-text')

@section('content')
PEDIDO ENTREGADO

Marcamos como entregado tu pedido {!! $order['number'] !!}.

@if ($order['needs_activation'])
PRÓXIMOS PASOS
Cuando recibas tu Finisher Legacy, actívalo desde la app o en {!! $order['activation_label'] !!}

@endif
@include('emails.orders.partials.summary-text', ['order' => $order])
Ver mi pedido: {!! $order['order_url'] !!}
@endsection
