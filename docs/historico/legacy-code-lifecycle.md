# Ciclo de vida del Legacy Code

## Regla oficial: los Legacy QR no caducan

Un Legacy Code, una vez asignado a una placa producida, **es permanente**. No existe
`expires_at`, no existe expiración automática, no existe token de sesión ni QR temporal
para placas físicas. Confirmado en el esquema actual (`legacy_codes`): no hay ninguna
columna de expiración, y `App\Enums\LegacyCodeStatus` no incluye ningún estado temporal
— solo `Generated, Available, Assigned, Claimed, Blocked, Replaced, Cancelled`.

```
https://finisherlegacy.com/l/Q8K2MX7P
```

Ese enlace debe seguir resolviendo durante toda la vida útil del producto físico. El
usuario puede cambiar correo, username, teléfono, avatar, la app móvil, reinstalar,
cambiar de dispositivo: **el QR no cambia**.

## Legacy Code = fuente de verdad. QR = asset derivado

La imagen QR **no** es la identidad. La identidad es `LegacyCode.code` (ej. `FL-Q8K2MX7P`,
generado por `App\Support\CodeGenerator::unique('FL', ...)`, alfabeto sin ambigüedad
visual, no incremental, no adivinable).

La imagen (`.svg`/`.png`) es solo una representación visual, **regenerable en cualquier
momento** a partir del `code`. Esto ya está implementado así en
`App\Services\QrCodeService` + `App\Services\LegacyCodeQrService`: el SVG nunca se
guarda en disco como archivo autoritativo, se genera en cada request a partir de la URL
pública (`route('legacy-code.show', $code)`). Si mañana se pierde cualquier archivo PNG
o SVG exportado, **no se ha perdido la placa** — el backend regenera el QR desde
`LegacyCode.code` sin ninguna otra dependencia.

Esto se extiende igual a los archivos de producción (SVG/PNG/PDF de la placa completa,
ver `docs/plate-production.md`): no se persisten como archivos autoritativos en disco,
se generan on-demand desde `Plate` + `PlateTemplateVersion`, así que "perder" un archivo
exportado nunca es una pérdida de datos real.

## URL permanente

El QR apunta siempre a `/l/{code}` (ej. `https://finisherlegacy.com/l/Q8K2MX7P`), nunca a
`/users/{id}`, `/plates/{id}`, `/qr/temp/...` ni `?token=...`. El identificador es público
e impredecible, no un ID incremental.

## Preparación para cambio futuro de dominio

Hoy el dominio es `finisherlegacy.com`, pero las URLs grabadas físicamente en una placa
de metal no se pueden "actualizar" después de producidas. Regla arquitectónica:

- El código (`code`) es independiente del dominio. Un cambio de dominio, de frontend o de
  app móvil **nunca** debe invalidar un `code` existente.
- Si algún día cambia el dominio público, el dominio anterior debe mantenerse activo como
  **redirect 301 permanente** hacia el nuevo, ruta por ruta (`/l/{code}` → `/l/{code}`),
  indefinidamente. Nunca se debe dar de baja el dominio viejo mientras existan placas
  físicas en circulación grabadas con esa URL.
- Ninguna migración de infraestructura, cambio de frontend o relanzamiento de app debe
  romper una placa física existente. Esta es una restricción de producto, no solo técnica.

## Respaldo

`legacy_codes`, `plates`, `plate_templates`, `plate_template_versions` y
`production_jobs` son información crítica: son la única asociación entre un objeto físico
ya entregado y sus datos. Perder esta asociación en producción rompería productos físicos
ya en manos de corredores, sin forma de repararlo retroactivamente.

Para el piloto no se implementa infraestructura de respaldo adicional, pero en producción
real debe existir:

- Respaldo periódico (diario como mínimo) de esas cinco tablas, con retención suficiente
  para cubrir el ciclo de vida completo de un evento (antes/durante/después).
- El respaldo debe poder restaurarse de forma consistente entre esas tablas (son
  relacionales entre sí — restaurar `plates` sin `legacy_codes` o viceversa deja
  registros huérfanos).
