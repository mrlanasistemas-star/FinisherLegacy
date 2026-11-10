{{--
    Order lines with thumbnails (product primary image or tag render; brand
    monogram for items without one) and line totals. Var: $order (array
    from App\Mail\Support\OrderSummary::for).
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0 0 8px 0;">
<tr>
<td class="fl-muted fl-line" style="padding: 0 0 10px 0; border-bottom: 1px solid #ECE7DC; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #6B675F;">{{ __('Pedido :number', ['number' => $order['number']]) }}</td>
</tr>
@foreach ($order['items'] as $item)
<tr>
<td class="fl-line" style="padding: 16px 0; border-bottom: 1px solid #ECE7DC;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td width="72" valign="top" style="width: 72px; padding-right: 16px;">
<img src="{{ $item['image'] }}" width="72" height="72" alt="{{ $item['alt'] }}" style="display: block; width: 72px; height: 72px; object-fit: cover; border: 1px solid #ECE7DC; border-radius: 4px; background-color: #F8F7F3;" />
</td>
<td valign="top" class="fl-text" style="font-size: 14px; line-height: 20px; color: #0B0B0C;">
<strong style="font-weight: 600;">{{ $item['name'] }}</strong>
@if (filled($item['variant']))
<br /><span class="fl-muted" style="font-size: 13px; color: #6B675F;">{{ $item['variant'] }}</span>
@endif
<br /><span class="fl-muted" style="font-size: 13px; color: #6B675F;">{{ __('Cantidad: :quantity', ['quantity' => $item['quantity']]) }}</span>
</td>
<td valign="top" align="right" class="fl-text" style="padding-left: 12px; font-size: 14px; line-height: 20px; white-space: nowrap; color: #0B0B0C;">{{ $item['total'] }}</td>
</tr>
</table>
</td>
</tr>
@endforeach
</table>
