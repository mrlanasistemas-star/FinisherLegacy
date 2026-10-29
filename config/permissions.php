<?php

/*
|--------------------------------------------------------------------------
| Catálogo central de permisos
|--------------------------------------------------------------------------
|
| Fuente única de verdad para: (1) RolePermissionSeeder, que crea estos
| permisos en base de datos, y (2) la UI de "Roles y permisos" / el menú
| administrativo, que lee este mismo catálogo para mostrar labels en
| español y agrupar la matriz por módulo. Añadir un permiso aquí es
| suficiente — no hay una segunda lista que mantener sincronizada.
|
| Los nombres técnicos (ej. "users.view") nunca cambian; solo se agregan
| labels en español para mostrarlos. Esto no altera qué permisos existen
| ni qué rol los tiene asignado — ver App\Support\PermissionCatalog.
|
*/

return [
    'users' => [
        'label' => 'Usuarios',
        'icon' => 'Users',
        'permissions' => [
            'users.view' => 'Ver usuarios',
            'users.manage' => 'Gestionar usuarios',
        ],
    ],
    'athletes' => [
        'label' => 'Atletas',
        'icon' => 'UserCircle',
        'permissions' => [
            'athletes.view' => 'Ver atletas',
            'athletes.manage' => 'Gestionar atletas',
        ],
    ],
    'organizers' => [
        'label' => 'Organizadores',
        'icon' => 'Building2',
        'permissions' => [
            'organizers.view' => 'Ver organizadores',
            'organizers.manage' => 'Gestionar organizadores',
        ],
    ],
    'events' => [
        'label' => 'Eventos',
        'icon' => 'Calendar',
        'permissions' => [
            'events.view' => 'Ver eventos',
            'events.manage' => 'Gestionar eventos',
        ],
    ],
    'editions' => [
        'label' => 'Ediciones',
        'icon' => 'CalendarDays',
        'permissions' => [
            'editions.view' => 'Ver ediciones',
            'editions.manage' => 'Gestionar ediciones',
        ],
    ],
    'races' => [
        'label' => 'Carreras',
        'icon' => 'Flag',
        'permissions' => [
            'races.view' => 'Ver carreras',
            'races.manage' => 'Gestionar carreras',
        ],
    ],
    'participants' => [
        'label' => 'Participantes',
        'icon' => 'UserCheck',
        'permissions' => [
            'participants.view' => 'Ver participantes',
            'participants.manage' => 'Gestionar participantes',
        ],
    ],
    'results' => [
        'label' => 'Resultados',
        'icon' => 'Trophy',
        'permissions' => [
            'results.view' => 'Ver resultados',
            'results.manage' => 'Gestionar resultados',
        ],
    ],
    'preregistrations' => [
        'label' => 'Prerregistros',
        'icon' => 'ClipboardList',
        'permissions' => [
            'preregistrations.view' => 'Ver prerregistros',
            'preregistrations.manage' => 'Gestionar prerregistros',
        ],
    ],
    'notifications' => [
        'label' => 'Notificaciones',
        'icon' => 'Bell',
        'permissions' => [
            'notifications.send' => 'Enviar notificaciones a atletas',
        ],
    ],
    'medals' => [
        'label' => 'Medallas',
        'icon' => 'Award',
        'permissions' => [
            'medals.view' => 'Ver medallas',
            'medals.manage' => 'Gestionar medallas',
        ],
    ],
    'plates' => [
        'label' => 'Placas',
        'icon' => 'Boxes',
        'permissions' => [
            'plates.view' => 'Ver placas',
            'plates.manage' => 'Gestionar placas',
        ],
    ],
    'legacycodes' => [
        'label' => 'Legacy Codes',
        'icon' => 'QrCode',
        'permissions' => [
            'legacycodes.view' => 'Ver Legacy Codes',
            'legacycodes.manage' => 'Gestionar Legacy Codes',
        ],
    ],
    'production' => [
        'label' => 'Producción',
        'icon' => 'Factory',
        'permissions' => [
            'production.view' => 'Ver producción',
            'production.manage' => 'Gestionar producción',
        ],
    ],
    'productiondevices' => [
        'label' => 'Estaciones',
        'icon' => 'Cpu',
        'permissions' => [
            'productiondevices.view' => 'Ver estaciones',
            'productiondevices.manage' => 'Gestionar estaciones',
        ],
    ],
    'imports' => [
        'label' => 'Importaciones',
        'icon' => 'Upload',
        'permissions' => [
            'imports.view' => 'Ver importaciones',
            'imports.manage' => 'Gestionar importaciones',
        ],
    ],
    'integrations' => [
        'label' => 'Integraciones',
        'icon' => 'Plug',
        'permissions' => [
            'integrations.view' => 'Ver integraciones',
            'integrations.manage' => 'Gestionar integraciones',
            'integrations.sync' => 'Sincronizar integraciones',
        ],
    ],
    'incidents' => [
        'label' => 'Incidencias',
        'icon' => 'AlertTriangle',
        'permissions' => [
            'incidents.view' => 'Ver incidencias',
            'incidents.manage' => 'Gestionar incidencias',
        ],
    ],
    'operators' => [
        'label' => 'Personal operativo',
        'icon' => 'UserCog',
        'permissions' => [
            'operators.view' => 'Ver personal operativo',
            'operators.manage' => 'Gestionar personal operativo',
        ],
    ],
    'platetemplates' => [
        'label' => 'Plate Studio',
        'icon' => 'Palette',
        'permissions' => [
            'platetemplates.view' => 'Ver moldes',
            'platetemplates.manage' => 'Gestionar moldes',
        ],
    ],
    'legacyplates' => [
        'label' => 'Legacy Plates',
        'icon' => 'Award',
        'permissions' => [
            'legacyplates.manage' => 'Gestionar Legacy Plates',
            'legacyplates.produce' => 'Producir Legacy Plates',
        ],
    ],
    'nfc' => [
        'label' => 'Etiquetas NFC',
        'icon' => 'Nfc',
        'permissions' => [
            'nfc.view' => 'Ver etiquetas NFC y productos conectados',
            'nfc.manage' => 'Generar, asignar y desactivar etiquetas NFC',
        ],
    ],
    'tags' => [
        'label' => 'Finisher Legacy Tags',
        'icon' => 'Tag',
        'permissions' => [
            'tags.view' => 'Ver tags, activaciones e historial por unidad',
            'tags.manage' => 'Recibir lotes, transferir, reemplazar y desactivar tags',
            'pos.sell' => 'Vender en persona (venta rápida)',
        ],
    ],
    'allies' => [
        'label' => 'Aliados y distribución',
        'icon' => 'Handshake',
        'permissions' => [
            'allies.view' => 'Ver aliados y proveedores',
            'allies.manage' => 'Gestionar aliados, proveedores y condiciones',
            'ally.portal' => 'Portal de aliado (solo su propio inventario y ventas)',
        ],
    ],
    'support' => [
        'label' => 'Soporte',
        'icon' => 'LifeBuoy',
        'permissions' => [
            'support.view' => 'Ver incidencias',
            'support.manage' => 'Abrir y resolver incidencias, reemplazos y devoluciones',
        ],
    ],
    'store' => [
        'label' => 'Tienda',
        'icon' => 'ShoppingBag',
        'permissions' => [
            'store.view' => 'Ver tienda',
            'products.manage' => 'Gestionar productos y variantes',
            'inventory.manage' => 'Gestionar inventario',
            'orders.view' => 'Ver pedidos',
            'orders.manage' => 'Gestionar pedidos',
            'payments.view' => 'Ver pagos',
            'payments.record_manual' => 'Registrar pagos manuales (terminal/efectivo)',
            'payments.configure' => 'Configurar pasarela de pago (OpenPay / Stripe)',
            'coupons.manage' => 'Gestionar cupones de descuento',
        ],
    ],
    'eventdata' => [
        'label' => 'Fuentes de datos',
        'icon' => 'Database',
        'permissions' => [
            'eventdata.manage' => 'Gestionar fuentes de datos de eventos',
        ],
    ],
    'media' => [
        'label' => 'Media de atletas',
        'icon' => 'Image',
        'permissions' => [
            'media.manage' => 'Gestionar media de atletas',
        ],
    ],
    'content' => [
        'label' => 'Contenido y comunidad',
        'icon' => 'Newspaper',
        'permissions' => [
            'content.manage' => 'Gestionar contenido corporativo (Nosotros, trayectoria, contacto)',
            'community.moderate' => 'Moderar la comunidad (reportes y publicaciones)',
        ],
    ],
    'photos' => [
        'label' => 'Fotografías de eventos',
        'icon' => 'Camera',
        'permissions' => [
            'photos.manage' => 'Revisar fotos, aprobar fotógrafos y registrar pagos',
            'photographer.portal' => 'Portal del fotógrafo (subir y vender fotos)',
        ],
    ],
    'access' => [
        'label' => 'Accesos especiales',
        'icon' => 'KeyRound',
        'permissions' => [
            'dashboard.admin.view' => 'Acceder al panel administrativo',
            'operator.access' => 'Acceder a Event OS',
            'production.access' => 'Acceder a producción',
            'audit.view' => 'Ver auditoría',
        ],
    ],
];
