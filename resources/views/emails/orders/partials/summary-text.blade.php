{{-- Plain-text order summary (items, totals, delivery, payment) for the text alternatives. --}}
{!! __('Pedido :number', ['number' => $order['number']]) !!}
@foreach ($order['items'] as $item)
- {!! $item['name'] !!}{!! $item['variant'] ? ' ('.$item['variant'].')' : '' !!} x {{ $item['quantity'] }} · {!! $item['total'] !!}
@endforeach

{!! __('Subtotal') !!}: {!! $order['subtotal'] !!}
@if ($order['discount'])
{!! $order['coupon'] ? __('Descuento (:coupon)', ['coupon' => $order['coupon']]) : __('Descuento') !!}: {!! $order['discount'] !!}
@endif
@if ($order['tax'])
{!! __('Impuestos') !!}: {!! $order['tax'] !!}
@endif
{!! __('Total') !!}: {!! $order['total'] !!}

@if ($order['delivery_method'] || $order['address'] !== [])
{!! __('Entrega') !!}: {!! $order['delivery_method'] ?? '' !!}
@foreach ($order['address'] as $line)
{!! $line !!}
@endforeach

@endif
@if ($order['payment_method'])
{!! __('Método de pago') !!}: {!! $order['payment_method'] !!}

@endif
