# Athlete Assets — Digital Closet, QR de producto, media de evento

## AthleteOwnedProduct

Una unidad física concreta de un producto QR-capable (Trisuit, FAST T1
Socks, Chill Band, Racepack — brief §84-§93/§103/§109-§111) propiedad de
un `Athlete`. Legacy Plate **no** usa este modelo — mantiene su propio
ciclo de vida vía `LegacyCode` (brief §93); ambos son conceptualmente
"algo físico que le pertenece a un atleta", pero con identificadores e
historiales distintos por diseño.

Nace vía `App\Actions\Commerce\CreateAthleteOwnedProduct`, llamada por
`App\Actions\Commerce\FulfillOrderItem` una vez por unidad — una Order de
2 Trisuits con Athlete conocido produce 2 `AthleteOwnedProduct` distintos,
cada uno con su propio `asset_code`.

### AssetCode

`App\Services\Commerce\AssetCodeService` — genera el identificador
impredecible (`CodeGenerator`, alfabeto sin ambigüedad visual, 12
caracteres) que resuelve `GET /api/v1/gear/{code}` (brief §86-§88/§106-
§107/§210). **Nunca PII** — el QR apunta a una URL con un código
impredecible, no contiene nombre/email/teléfono. `PublicGearResource`
(brief §176) es deliberadamente mínimo: nombre de producto, variante,
estado — nada del dueño.

### Claim

Si un `AthleteOwnedProduct` se creó sin Athlete conocido (venta sin
comprador identificado — no implementado como flujo de venta todavía,
pero el modelo lo soporta con `status = unclaimed`), cualquier Athlete
autenticado puede reclamarlo vía `App\Actions\Commerce\ClaimAthleteOwnedProduct`.
`Cache::lock` por `asset_code` + `lockForUpdate()` dentro de la
transacción garantizan que dos claims simultáneos del mismo código
resuelven a un solo dueño (brief §150/§180).

## Legacy Plate Entitlement

Ver `docs/architecture/legacy-plate-v2.md` y `docs/architecture/commerce.md`
— el equivalente de "AthleteOwnedProduct" para Legacy Plate, pero con su
propio ciclo de vida comercial (`LegacyPlateEntitlementStatus`) separado
del estado físico de producción (`ProductionJobStatus`).

## Athlete history

`App\Queries\Athletes\GetAthleteHistory` (ya existente, sin cambios de
forma) sigue siendo la única Query que arma "1 Athlete, N eventos" —
`participations`, `plates`, `medals`. `GET /api/v1/me/events` la reutiliza
literalmente (brief §116/§173: nunca un segundo read model).

## Event Media

`AthleteEventMedia` pertenece a `Athlete` **+** `EventParticipant`, nunca
genéricamente a `User` (brief §41/§94-§95) — una foto vive dentro de una
participación concreta, no en un álbum global del usuario.

- Límites (brief §42/§96-§97): `App\Services\Media\ResolveMediaEntitlement`
  lee `config('finisher.event_media')` — 5 fotos / 1 video gratis por
  participación, nunca hardcodeado. Preparado para una futura suscripción
  de almacenamiento (el shape de `remaining()` no cambia si eso llega),
  sin construir billing todavía (brief §46/§143).
- Validación real (brief §98): `App\Actions\Media\UploadAthleteEventMedia`
  usa `UploadedFile::getMimeType()` (contenido real, `fileinfo`), nunca la
  extensión del cliente.
- Storage: Laravel Filesystem (`config('finisher.event_media.disk')`,
  `public` por defecto), nunca BLOB en MySQL. Checksum SHA-256 real vía
  `hash_file()`.
- Autorización: `App\Policies\AthleteEventMediaPolicy` — solo el Athlete
  dueño o `media.manage` puede modificar/borrar (brief §102/§144).

### Deuda conocida

- Sin thumbnail/redimensionado automático para imágenes de evento todavía
  — `App\Services\ImageProcessingService` existe para medallas/perfil con
  su propio `config('finisher.image')`, pero event media usa un config
  distinto (`finisher.event_media`) y no se conectó a ese pipeline en este
  pase; se sirve el archivo original. Brief §145 lo pide — queda como
  siguiente iteración.
- Sin transcodificación de video (igual que el video de medallas, ya
  documentado como deshabilitado por falta de infraestructura ffmpeg) —
  el archivo se almacena tal cual se sube.
- Claim de `AthleteOwnedProduct` "unclaimed" no tiene todavía un flujo de
  venta real que produzca esa condición (hoy `FulfillOrderItem` siempre
  conoce al Athlete comprador) — el mecanismo de claim existe y está
  probado, a la espera de un caso de uso real (regalo, venta en evento sin
  cuenta) que lo dispare.
