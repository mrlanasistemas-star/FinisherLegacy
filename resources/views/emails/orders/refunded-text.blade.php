{{-- Plain-text alternative of emails.orders.refunded. --}}
@extends('emails.layouts.brand-text')

@section('content')
{!! mb_strtoupper(__('Reembolso procesado')) !!}

{!! $full ? __('Reembolsamos el total de tu pedido :number.', ['number' => $order['number']]) : __('Reembolsamos una parte de tu pedido :number.', ['number' => $order['number']]) !!}

{!! $full ? __('Monto reembolsado') : __('Monto reembolsado a la fecha') !!}: {!! $refund !!}
@if ($order['payment_method'])
{!! __('A tu método de pago original: :method', ['method' => $order['payment_method']]) !!}
@endif

{!! __('El dinero vuelve al mismo método con el que pagaste. El tiempo en que se refleja en tu estado de cuenta depende de tu banco.') !!}

@include('emails.orders.partials.summary-text', ['order' => $order])
{!! __('Ver mi pedido') !!}: {!! $order['order_url'] !!}
@endsection
