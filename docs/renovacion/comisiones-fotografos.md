<!--
  Comisiones de fotógrafos: cómo se calcula, guarda, revierte y reporta lo
  que Finisher Legacy retiene de cada foto vendida por un fotógrafo.
-->

# Comisiones de fotógrafos

Los fotógrafos suben fotos gratis y ponen su precio. Cuando un atleta paga,
Finisher Legacy retiene una **comisión** y se descuenta la **comisión de la
pasarela de pago** (OpenPay), según quién la absorba. Lo que queda es la
**ganancia del fotógrafo** (neto), que es lo único que se le transfiere.

## Auditoría previa (lo que ya existía)

- `PhotoSale` ya congelaba el reparto al pagarse el pedido
  (`App\Actions\Photos\RecordPhotoSales`, llamado por `MarkOrderPaid`):
  `commission_percent`, `platform_fee_minor`, `processor_fee_minor`,
  `photographer_net_minor`, la pasarela (`payment_provider`) y si la comisión
  de pasarela es estimada.
- La comisión era **un solo valor de `.env`** (`PHOTOS_PLATFORM_COMMISSION_PERCENT`,
  20 % por defecto) sin historial, sin vigencias y sin excepciones por fotógrafo.
- La pasarela usaba una estimación de `.env` (3.6 % + $3.00 + IVA por defecto)
  y siempre la absorbía el fotógrafo.
- Los pagos (`PhotographerPayout`) sumaban el neto de las ventas pendientes.
  Los reembolsos **no** afectaban las ventas de fotos.

## Qué se agregó

### 1. Términos editables con historial

Tabla `photographer_commission_settings` (solo se agregan filas; la tabla es
el historial). Se administra en **Administración → Contenido → Comisiones de
fotógrafos** (`/admin/photographer-commissions`), con el permiso
`photographers.commissions.manage` (solo admin). Cada cambio queda en el
registro de actividad (`log_name = commissions`).

- **Global**: comisión %, comisión de pasarela OpenPay (% y cargo fijo por
  pago) y quién la absorbe (`photographer` o `finisher`).
  - Si los campos de pasarela se dejan vacíos y no hay
    `PHOTOS_OPENPAY_FEE_PERCENT` en `.env`, el sistema muestra
    **«Comisión de pasarela no configurada»** y usa la estimación provisional
    de `config('finisher.photos.processor_fees')`.
  - Quién absorbe por defecto: `PHOTOS_GATEWAY_FEE_ABSORBED_BY`
    (`photographer`, el comportamiento histórico).
- **Por fotógrafo** (override): una comisión % personalizada. Guardar «Volver
  a la comisión global» crea una fila con `%` vacío desde su fecha.
- **Vigencia**: hoy (inmediato) o un día futuro (desde las 00:00). Nunca en el
  pasado. Un cambio programado que aún no entra en vigor se puede cancelar;
  uno vigente solo se reemplaza con otro cambio.

Precedencia en el instante T (`App\Services\Commissions\CommissionResolver`):

1. override del fotógrafo vigente en T (con %),
2. global vigente en T,
3. valores de `.env` (`config('finisher.photos')`).

### 2. Snapshot en la venta

Al pagarse el pedido, cada `PhotoSale` guarda los términos vigentes en ese
momento. Las columnas existentes se conservan; el modelo expone alias:

| Concepto | Columna | Alias en `PhotoSale` |
|---|---|---|
| Tasa de comisión | `commission_percent` | `commission_rate` |
| Comisión FL | `platform_fee_minor` | `commission_minor` |
| Comisión de pasarela | `processor_fee_minor` | `gateway_fee_minor` |
| Neto del fotógrafo | `photographer_net_minor` | — |

Nuevas: `commission_source` (`config|global|override`),
`commission_setting_id`, `gateway_fee_absorbed_by`.

Fórmula (centavos, `round()` = redondeo half-up; espejo en
`resources/js/lib/photoFees.ts`):

```
pasarela   = round((bruto × pasarela% + fijo ÷ fotos del pedido) × (1 + IVA%))  ≤ bruto
comisión   = round(bruto × comisión%)
absorbe fotógrafo: comisión ≤ bruto − pasarela ; neto = bruto − pasarela − comisión
absorbe Finisher:  comisión ≤ bruto           ; neto = bruto − comisión
```

Un cambio de tasa **nunca recalcula** ventas pasadas.

### 3. Reembolsos

- **Reembolso total** (`App\Events\Commerce\OrderRefunded` con `full = true`):
  el listener `App\Listeners\PhotoSaleRefundReversal` revierte todas las ventas
  de foto del pedido (`refunded_at`, `reversed_net_minor`,
  `reversed_commission_minor`). El snapshot original no se toca.
  - Venta aún no pagada al fotógrafo → `payout_status = reversed` (nunca se le debe).
  - Venta ya pagada → queda `paid` y se vuelve un **descuento** (clawback) en el
    siguiente pago (`reversal_payout_id`).
- **Reembolso parcial**: la pasarela no dice qué fotos se reembolsaron, así que
  no se revierte nada automáticamente. Un admin usa «Revertir por reembolso» en
  la pestaña Ventas del fotógrafo (`POST /admin/photographer-commissions/ventas/{uuid}/revertir`).

### 4. Pagos

`App\Actions\Photos\RegisterPhotographerPayout`:
`monto = Σ neto de ventas por pagar − Σ neto revertido de ventas ya pagadas`.
Si los descuentos superan lo pendiente, no se registra nada (saldo negativo).
El pago guarda `reversals_minor` y `reversals_count`.

### 5. Simulador del fotógrafo

En **Mis fotos** (subir y editar precio) y en el panel del fotógrafo:

- línea en vivo antes de guardar: «Precio al cliente → Comisión Finisher Legacy
  (Y %) → Comisión de pasarela → Tú recibes»;
- tabla de precios comunes ($50, $99, $149, $199, $299);
- aviso de la tasa vigente, desde cuándo, y el próximo cambio programado.

Endpoint JSON: `GET /fotografo/simulador?price_minor=…` (mismo cálculo que
congelaría una venta en ese momento).

### 6. Avisos

`App\Notifications\PhotographerCommissionChanged` (base de datos + correo, en
cola) con la nueva tasa, la anterior y la fecha de vigencia:

- cambio global de tasa o de quién absorbe la pasarela → fotógrafos aprobados
  sin override vigente en esa fecha;
- cambio de override → ese fotógrafo.

### 7. Reportes

`/admin/photographer-commissions/reporte` (`PhotographerEarningsReport`): por
fotógrafo y moneda, bruto, comisión, pasarela, neto, reembolsos, pagado y
**por pagar**, con filtro de fechas. Se calcula sobre los snapshots.

## Pendiente / decisiones abiertas

- Confirmar la tarifa real contratada con OpenPay y capturarla en el panel.
- La descarga de una foto reembolsada no se bloquea aquí (vive en
  `PhotosController`, fuera de este módulo).
- La comisión de pasarela por pago con Stripe sigue saliendo de `.env`.
