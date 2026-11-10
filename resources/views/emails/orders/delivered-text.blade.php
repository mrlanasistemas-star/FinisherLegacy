{{-- Plain-text alternative of emails.orders.delivered. --}}
@extends('emails.layouts.brand-text')

@section('content')
{!! mb_strtoupper(__('Pedido entregado')) !!}

{!! __('Marcamos como entregado tu pedido :number.', ['number' => $order['number']]) !!}

@if ($order['needs_activation'])
{!! mb_strtoupper(__('Próximos pasos')) !!}
{!! __('Cuando recibas tu Finisher Legacy, actívalo desde la app o en :link', ['link' => $order['activation_label']]) !!}

@endif
@include('emails.orders.partials.summary-text', ['order' => $order])
{!! __('Ver mi pedido') !!}: {!! $order['order_url'] !!}
@endsection
