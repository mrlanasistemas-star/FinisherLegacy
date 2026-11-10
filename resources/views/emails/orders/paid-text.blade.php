{{-- Plain-text alternative of emails.orders.paid. --}}
@extends('emails.layouts.brand-text')

@section('content')
{!! mb_strtoupper(__('Pago confirmado')) !!}

{!! $order['first_name'] ? __('¡Gracias, :name!', ['name' => $order['first_name']]) : __('¡Gracias por tu compra!') !!}

{!! __('Confirmamos el pago de tu pedido :number. Este es el resumen de tu compra.', ['number' => $order['number']]) !!}

@include('emails.orders.partials.summary-text', ['order' => $order])
@if ($order['needs_activation'])
{!! mb_strtoupper(__('Próximos pasos')) !!}
{!! __('Cuando recibas tu Finisher Legacy, actívalo desde la app o en :link', ['link' => $order['activation_label']]) !!}

@endif
{!! __('Ver mi pedido') !!}: {!! $order['order_url'] !!}
@endsection
