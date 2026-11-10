{{-- Plain-text order summary (items, totals, delivery, payment) for the text alternatives. --}}
Pedido {!! $order['number'] !!}
@foreach ($order['items'] as $item)
- {!! $item['name'] !!}{!! $item['variant'] ? ' ('.$item['variant'].')' : '' !!} x {{ $item['quantity'] }} · {!! $item['total'] !!}
@endforeach

Subtotal: {!! $order['subtotal'] !!}
@if ($order['discount'])
Descuento{!! $order['coupon'] ? ' ('.$order['coupon'].')' : '' !!}: {!! $order['discount'] !!}
@endif
@if ($order['tax'])
Impuestos: {!! $order['tax'] !!}
@endif
Total: {!! $order['total'] !!}

@if ($order['delivery_method'] || $order['address'] !== [])
Entrega: {!! $order['delivery_method'] ?? '' !!}
@foreach ($order['address'] as $line)
{!! $line !!}
@endforeach

@endif
@if ($order['payment_method'])
Método de pago: {!! $order['payment_method'] !!}

@endif
