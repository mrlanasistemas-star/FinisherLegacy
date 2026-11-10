{{--
    Delivery (method + address) and payment method side panels. Only what
    the order actually has is shown. Var: $order (OrderSummary array).
--}}
@if ($order['delivery_method'] || $order['address'] !== [] || $order['payment_method'])
<table role="presentation" class="fl-panel" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F8F7F3" style="width: 100%; background-color: #F8F7F3; border-radius: 4px; margin: 0 0 28px 0;">
<tr>
<td style="padding: 20px 22px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@if ($order['delivery_method'] || $order['address'] !== [])
<tr>
<td class="fl-text" style="padding: 0 0 14px 0; font-size: 14px; line-height: 21px; color: #0B0B0C;">
<span style="display: block; margin-bottom: 4px; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #A88B62;">Entrega</span>
@if ($order['delivery_method']){{ $order['delivery_method'] }}<br />@endif
@foreach ($order['address'] as $line){{ $line }}<br />@endforeach
</td>
</tr>
@endif
@if ($order['payment_method'])
<tr>
<td class="fl-text" style="font-size: 14px; line-height: 21px; color: #0B0B0C;">
<span style="display: block; margin-bottom: 4px; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #A88B62;">Método de pago</span>
{{ $order['payment_method'] }}
</td>
</tr>
@endif
</table>
</td>
</tr>
</table>
@endif
