/**
 * What the site guide says on each page. Pure data + one resolver, so the
 * copy can be tuned without touching SiteGuide.vue.
 *
 * Matching: the Inertia page component name first (exact, then by prefix),
 * then the URL path as a fallback, then a generic topic with quick links.
 * Every topic has a guest and a signed-in variant where it matters.
 */

export interface GuideAction {
    label: string;
    /** Inertia link target. */
    href?: string;
    /** CSS selectors tried in order; the first one found is scrolled to. */
    scrollTo?: string[];
    primary?: boolean;
}

export interface GuideTopic {
    key: string;
    /** Small eyebrow above the message, e.g. "Tienda". */
    context: string;
    message: string;
    steps?: string[];
    actions: GuideAction[];
}

export interface GuideContext {
    component: string;
    path: string;
    signedIn: boolean;
    firstName: string | null;
    username: string | null;
    /** /entrar?volver=<here> (login) or with modo=registro. */
    loginUrl: (mode?: 'login' | 'registro') => string;
    /**
     * Translator of the active language (useI18n().t). Builders destructure
     * it (`const { t } = ctx`) and call `t('…')` with a literal, so the
     * string extractor finds every sentence.
     */
    t: (source: string, params?: Record<string, string | number>) => string;
}

type TopicBuilder = (ctx: GuideContext) => GuideTopic;

const joinAction = (ctx: GuideContext): GuideAction[] => {
    const { t } = ctx;

    return ctx.signedIn
        ? []
        : [{ label: t('Crea tu cuenta'), href: ctx.loginUrl('registro') }];
};

const home: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'home',
        context: t('Bienvenida'),
        message: ctx.firstName
            ? t(
                  '¡Hola, {name}! Soy tu guía. Te explico en 3 pasos qué es el Finisher Legacy Tag:',
                  { name: ctx.firstName },
              )
            : t(
                  '¡Hola! Soy tu guía. Te explico en 3 pasos qué es el Finisher Legacy Tag:',
              ),
        steps: [
            t('Elige en la tienda la colección de tu carrera.'),
            t('Te llega listo, con su correa. Nada que personalizar.'),
            t(
                'Acerca tu celular al tag y activa tu historia con fotos, tiempo y dedicatorias.',
            ),
        ],
        actions: [
            {
                label: t('Ver cómo funciona'),
                scrollTo: ['#como-funciona', '[data-guide="steps"]'],
            },
            { label: t('Ir a la tienda'), href: '/tienda', primary: true },
            { label: t('Activar mi tag'), href: '/activar' },
            ...joinAction(ctx),
        ],
    };
};

const store: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'store',
        context: t('Tienda'),
        message: t(
            'Cada colección es de una carrera o destino. Elige la tuya por lugar, disciplina o año; todas llevan el mismo tag premium.',
        ),
        steps: [
            t('Abre una colección para ver sus colores y detalles.'),
            t('Agrégala al carrito y paga seguro con tarjeta.'),
            t(
                'Cuando llegue, acércale tu celular y se activa en segundos con NFC.',
            ),
        ],
        actions: [
            {
                label: t('Ver colecciones'),
                href: '/colecciones',
                primary: true,
            },
            { label: t('Cómo se activa'), href: '/activar' },
            ...joinAction(ctx),
        ],
    };
};

const product: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'product',
        context: t('Producto'),
        message: t(
            'Aquí ves el tag de esta colección. Revisa las fotos, elige tu opción y agrégalo al carrito.',
        ),
        steps: [
            t('Te llega terminado de fábrica, con su correa.'),
            t('El costo de envío se define al confirmar tu pedido.'),
            t('Se activa acercando el celular: sin apps ni códigos.'),
        ],
        actions: [
            { label: t('Ver mi carrito'), href: '/carrito', primary: true },
            { label: t('Seguir viendo'), href: '/tienda' },
        ],
    };
};

const cart: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'cart',
        context: t('Tu carrito'),
        message: t(
            'Revisa tus piezas y cantidades. Cuando estés listo, pasa a pagar: el pago con tarjeta es seguro y tus datos van cifrados.',
        ),
        actions: ctx.signedIn
            ? [
                  { label: t('Ir a pagar'), href: '/checkout', primary: true },
                  { label: t('Seguir comprando'), href: '/tienda' },
              ]
            : [
                  {
                      label: t('Entrar para pagar'),
                      href: ctx.loginUrl(),
                      primary: true,
                  },
                  { label: t('Seguir comprando'), href: '/tienda' },
              ],
    };
};

const checkout: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'checkout',
        context: t('Pago seguro'),
        message: t(
            'Ya casi. Llena tus datos de envío y paga con tarjeta de crédito o débito; el cobro es seguro. Te llega la confirmación por correo.',
        ),
        actions: [
            { label: t('Volver al carrito'), href: '/carrito' },
            { label: t('¿Dudas? Escríbenos'), href: '/contact' },
        ],
    };
};

const orderDone: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'order-done',
        context: t('¡Pedido listo!'),
        message: t(
            '¡Gracias por tu compra! Te avisamos por correo cuando tu pedido salga. Cuando llegue, acerca tu celular al tag para activarlo.',
        ),
        actions: [
            {
                label: t('Ver mis pedidos'),
                href: '/mis-pedidos',
                primary: true,
            },
            { label: t('Cómo activarlo'), href: '/activar' },
        ],
    };
};

const orders: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'orders',
        context: t('Mis pedidos'),
        message: t(
            'Aquí ves el estado de cada pedido y su seguimiento. Si algo no cuadra, escríbenos y lo resolvemos.',
        ),
        actions: [
            { label: t('Ir a la tienda'), href: '/tienda' },
            { label: t('Contacto'), href: '/contact' },
        ],
    };
};

const activation: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'activation',
        context: t('Activa tu tag'),
        message: t('Activar tu Finisher Legacy Tag toma menos de un minuto:'),
        steps: [
            t(
                'Desbloquea tu celular y activa el NFC (en iPhone ya viene encendido).',
            ),
            t('Acerca la parte de arriba del celular al centro del tag.'),
            t(
                'Toca la notificación que aparece y sigue los pasos para crear tu historia.',
            ),
        ],
        actions: ctx.signedIn
            ? [{ label: t('Ver Mi Legacy'), href: '/mi-legacy', primary: true }]
            : [
                  {
                      label: t('Entrar para activarlo'),
                      href: ctx.loginUrl(),
                      primary: true,
                  },
                  { label: t('Crear cuenta'), href: ctx.loginUrl('registro') },
              ],
    };
};

const myLegacy: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'my-legacy',
        context: t('Mi Legacy'),
        message: t(
            'Aquí viven tus historias. Edita cada una con fotos, tu tiempo y dedicatorias; quien acerque su celular a tu tag la verá.',
        ),
        actions: [
            { label: t('Activar otro tag'), href: '/activar', primary: true },
            { label: t('Comunidad'), href: '/comunidad' },
        ],
    };
};

const community: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'community',
        context: t('Comunidad'),
        message: ctx.signedIn
            ? t(
                  'Comparte tus carreras: publica fotos de tu medalla, tu tag o tu meta, y celebra las de tus amigos.',
              )
            : t(
                  'Mira lo que comparten otros finishers. Para publicar, seguir y celebrar necesitas una cuenta.',
              ),
        steps: ctx.signedIn
            ? [
                  t('Toca «Publicar» y elige tus fotos.'),
                  t('Agrega un texto corto y tu carrera.'),
                  t('Encuentra amigos en Personas y síguelos.'),
              ]
            : undefined,
        actions: ctx.signedIn
            ? [
                  {
                      label: t('Encontrar amigos'),
                      href: '/comunidad/personas',
                      primary: true,
                  },
              ]
            : [
                  {
                      label: t('Crea tu cuenta'),
                      href: ctx.loginUrl('registro'),
                      primary: true,
                  },
                  { label: t('Ver personas'), href: '/comunidad/personas' },
              ],
    };
};

const people: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'people',
        context: t('Personas'),
        message: t(
            'Busca por nombre o usuario y sigue a quien corre contigo. Sus publicaciones aparecerán en tu comunidad.',
        ),
        actions: [
            { label: t('Volver a Comunidad'), href: '/comunidad' },
            ...joinAction(ctx),
        ],
    };
};

const profile: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'profile',
        context: t('Perfil'),
        message: t(
            'Este es un perfil de finisher: sus historias, medallas y publicaciones. Síguelo para ver lo que comparte.',
        ),
        actions: [
            { label: t('Encontrar más personas'), href: '/comunidad/personas' },
            ...joinAction(ctx),
        ],
    };
};

const myProfile: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'my-profile',
        context: t('Tu perfil'),
        message: t(
            'Tu perfil es tu vitrina: agrega foto, una frase y tus carreras para que tus amigos te encuentren.',
        ),
        actions: [
            ...(ctx.username
                ? [
                      {
                          label: t('Ver mi perfil público'),
                          href: `/@${ctx.username}`,
                          primary: true,
                      },
                  ]
                : []),
            { label: t('Ajustes de cuenta'), href: '/settings/profile' },
        ],
    };
};

const settings: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'settings',
        context: t('Ajustes'),
        message: t(
            'Aquí cambias tus datos, tu contraseña y la seguridad de tu cuenta. Los cambios se guardan al momento.',
        ),
        actions: [
            { label: t('Datos personales'), href: '/settings/profile' },
            { label: t('Seguridad'), href: '/settings/security' },
        ],
    };
};

const events: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'events',
        context: t('Eventos'),
        message: t(
            'Encuentra tu próxima carrera: filtra por nombre, deporte o estado y entra para ver detalles y prerregistro.',
        ),
        actions: [{ label: t('Ir a la tienda'), href: '/tienda' }],
    };
};

const dashboard: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'dashboard',
        context: t('Tu espacio'),
        message: ctx.firstName
            ? t(
                  '¡Hola, {name}! Desde aquí llegas a tus historias, tus pedidos y tu comunidad.',
                  { name: ctx.firstName },
              )
            : t(
                  '¡Hola! Desde aquí llegas a tus historias, tus pedidos y tu comunidad.',
              ),
        actions: [
            { label: t('Mi Legacy'), href: '/mi-legacy', primary: true },
            { label: t('Mis pedidos'), href: '/mis-pedidos' },
            { label: t('Comunidad'), href: '/comunidad' },
        ],
    };
};

const contact: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'contact',
        context: t('Contacto'),
        message: t(
            'Cuéntanos qué necesitas y te respondemos por correo. Si es sobre un pedido, incluye su número para ir más rápido.',
        ),
        actions: [{ label: t('Mis pedidos'), href: '/mis-pedidos' }],
    };
};

const fallback: TopicBuilder = (ctx) => {
    const { t } = ctx;

    return {
        key: 'general',
        context: t('Tu guía'),
        message: ctx.firstName
            ? t('¡Hola, {name}! ¿A dónde te llevo?', { name: ctx.firstName })
            : t('¡Hola! ¿A dónde te llevo?'),
        actions: [
            { label: t('Tienda'), href: '/tienda', primary: true },
            { label: t('Activar mi tag'), href: '/activar' },
            { label: t('Comunidad'), href: '/comunidad' },
            ...(ctx.signedIn
                ? [{ label: t('Mi Legacy'), href: '/mi-legacy' }]
                : joinAction(ctx)),
        ],
    };
};

/** Exact component names. */
const BY_COMPONENT: Record<string, TopicBuilder> = {
    Home: home,
    'store/Index': store,
    'store/Catalog': store,
    'store/Show': product,
    'store/Cart': cart,
    'store/Checkout': checkout,
    'store/CheckoutConfirmation': orderDone,
    'store/Orders': orders,
    'store/OrderShow': orders,
    'community/People': people,
    'community/Connections': people,
    'profile/Show': profile,
    'profile/Private': profile,
    Dashboard: dashboard,
    Contact: contact,
    'nfc/Tap': activation,
};

/** Component name prefixes, checked in order. */
const BY_PREFIX: [string, TopicBuilder][] = [
    ['collections/', store],
    ['activate/', activation],
    ['my-legacy/', myLegacy],
    ['community/', community],
    ['dashboard/profile/', myProfile],
    ['settings/', settings],
    ['events/', events],
];

/** URL paths, for pages whose component name isn't mapped. */
const BY_PATH: [RegExp, TopicBuilder][] = [
    [/^\/tienda(\/|$)/, store],
    [/^\/colecciones(\/|$)/, store],
    [/^\/(activar|t|n)(\/|$)/, activation],
    [/^\/mi-legacy(\/|$)/, myLegacy],
    [/^\/comunidad\/personas/, people],
    [/^\/comunidad(\/|$)/, community],
    [/^\/@/, profile],
    [/^\/settings(\/|$)/, settings],
    [/^\/carrito/, cart],
    [/^\/checkout/, checkout],
];

export function resolveGuideTopic(ctx: GuideContext): GuideTopic {
    const exact = BY_COMPONENT[ctx.component];

    if (exact) {
        return exact(ctx);
    }

    const prefixed = BY_PREFIX.find(([prefix]) =>
        ctx.component.startsWith(prefix),
    );

    if (prefixed) {
        return prefixed[1](ctx);
    }

    const byPath = BY_PATH.find(([re]) => re.test(ctx.path));

    return (byPath ? byPath[1] : fallback)(ctx);
}

/** Pages where the guide never shows (immersive / print / focus views). */
export const GUIDE_HIDDEN_COMPONENTS = new Set([
    'legacy/Story',
    'live/Show',
    'my-legacy/Print',
]);
