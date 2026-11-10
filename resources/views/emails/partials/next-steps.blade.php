{{--
    "Próximos pasos" panel: how to activate a physical Finisher Legacy.
    Var: $order (OrderSummary array); renders nothing for digital-only orders.
--}}
@if ($order['needs_activation'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0 0 32px 0;">
<tr>
<td width="3" bgcolor="#C9AE8B" style="width: 3px; background-color: #C9AE8B; font-size: 0; line-height: 0;">&nbsp;</td>
<td class="fl-text" style="padding: 4px 0 4px 18px; font-size: 14px; line-height: 22px; color: #2B2A28;">
<span style="display: block; margin-bottom: 4px; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #A88B62;">Próximos pasos</span>
Cuando recibas tu Finisher Legacy, actívalo desde la app o en <a href="{{ $order['activation_url'] }}" target="_blank" rel="noopener" style="color: #8A6F4B; font-weight: 600; text-decoration: underline;">{{ $order['activation_label'] }}</a>.
</td>
</tr>
</table>
@endif
