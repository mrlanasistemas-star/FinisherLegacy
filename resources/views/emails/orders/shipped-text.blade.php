{{-- Plain-text alternative of emails.orders.shipped. --}}
@extends('emails.layouts.brand-text')

@section('content')
{!! mb_strtoupper(__('Pedido enviado')) !!}

{!! __('Tu pedido va en camino') !!}. {!! __('Enviamos tu pedido :number. Te avisaremos cuando quede entregado.', ['number' => $order['number']]) !!}

@if ($order['carrier'])
{!! __('Paquetería') !!}: {!! $order['carrier'] !!}
@endif
@if ($order['tracking_number'])
{!! __('Número de guía') !!}: {!! $order['tracking_number'] !!}
@endif

@include('emails.orders.partials.summary-text', ['order' => $order])
@if ($order['needs_activation'])
{!! mb_strtoupper(__('Próximos pasos')) !!}
{!! __('Cuando recibas tu Finisher Legacy, actívalo desde la app o en :link', ['link' => $order['activation_label']]) !!}

@endif
{!! __('Ver mi pedido') !!}: {!! $order['order_url'] !!}
@endsection
