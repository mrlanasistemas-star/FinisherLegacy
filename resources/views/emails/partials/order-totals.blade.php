{{--
    Subtotal / descuento / impuestos / total, in the order currency.
    Var: $order (OrderSummary array).
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 4px 0 28px 0;">
<tr>
<td class="fl-muted" style="padding: 6px 0; font-size: 14px; line-height: 20px; color: #6B675F;">Subtotal</td>
<td align="right" class="fl-text" style="padding: 6px 0; font-size: 14px; line-height: 20px; color: #0B0B0C;">{{ $order['subtotal'] }}</td>
</tr>
@if ($order['discount'])
<tr>
<td class="fl-muted" style="padding: 6px 0; font-size: 14px; line-height: 20px; color: #6B675F;">Descuento @if ($order['coupon'])({{ $order['coupon'] }})@endif</td>
<td align="right" class="fl-text" style="padding: 6px 0; font-size: 14px; line-height: 20px; color: #0B0B0C;">{{ $order['discount'] }}</td>
</tr>
@endif
@if ($order['tax'])
<tr>
<td class="fl-muted" style="padding: 6px 0; font-size: 14px; line-height: 20px; color: #6B675F;">Impuestos</td>
<td align="right" class="fl-text" style="padding: 6px 0; font-size: 14px; line-height: 20px; color: #0B0B0C;">{{ $order['tax'] }}</td>
</tr>
@endif
<tr>
<td class="fl-text fl-line" style="padding: 14px 0 0 0; border-top: 2px solid #C9AE8B; font-size: 16px; line-height: 22px; font-weight: 700; color: #0B0B0C;">Total</td>
<td align="right" class="fl-text fl-line" style="padding: 14px 0 0 0; border-top: 2px solid #C9AE8B; font-size: 16px; line-height: 22px; font-weight: 700; color: #0B0B0C;">{{ $order['total'] }}</td>
</tr>
</table>
