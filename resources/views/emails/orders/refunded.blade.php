{{--
    "Reembolso procesado" (App\Mail\Commerce\OrderRefundedMail): refunded
    amount (full or partial), the order it belongs to and its payment method.
    Vars: $order (OrderSummary array), $refund (formatted amount), $full (bool).
--}}
@extends('emails.layouts.brand')

@section('content')
@include('emails.partials.heading', [
    'eyebrow' => __('Reembolso procesado'),
    'headline' => $full ? __('Reembolsamos tu pedido') : __('Procesamos un reembolso parcial'),
    'intro' => $full
        ? __('Reembolsamos el total de tu pedido :number.', ['number' => $order['number']])
        : __('Reembolsamos una parte de tu pedido :number.', ['number' => $order['number']]),
])
<table role="presentation" class="fl-panel" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F8F7F3" style="width: 100%; background-color: #F8F7F3; border-radius: 4px; margin: 0 0 28px 0;">
<tr>
<td align="center" style="padding: 24px 22px;">
<span style="display: block; margin-bottom: 6px; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #A88B62;">{{ $full ? __('Monto reembolsado') : __('Monto reembolsado a la fecha') }}</span>
<span class="fl-text" style="display: block; font-size: 28px; line-height: 34px; font-weight: 700; color: #0B0B0C;">{{ $refund }}</span>
@if ($order['payment_method'])
<span class="fl-muted" style="display: block; margin-top: 6px; font-size: 13px; line-height: 20px; color: #6B675F;">{{ __('A tu método de pago original: :method', ['method' => $order['payment_method']]) }}</span>
@endif
</td>
</tr>
</table>
<p class="fl-text" style="margin: 0 0 28px 0; font-size: 14px; line-height: 22px; color: #2B2A28;">{{ __('El dinero vuelve al mismo método con el que pagaste. El tiempo en que se refleja en tu estado de cuenta depende de tu banco.') }}</p>
@include('emails.partials.order-items', ['order' => $order])
@include('emails.partials.order-totals', ['order' => $order])
@include('emails.partials.button', ['url' => $order['order_url'], 'label' => __('Ver mi pedido')])
@endsection
