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
}

type TopicBuilder = (ctx: GuideContext) => GuideTopic;

const hello = (ctx: GuideContext) =>
    ctx.firstName ? `¡Hola, ${ctx.firstName}!` : '¡Hola!';

const joinAction = (ctx: GuideContext): GuideAction[] =>
    ctx.signedIn
        ? []
        : [{ label: 'Crea tu cuenta', href: ctx.loginUrl('registro') }];

const home: TopicBuilder = (ctx) => ({
    key: 'home',
    context: 'Bienvenida',
    message: `${hello(ctx)} Soy tu guía. Te explico en 3 pasos qué es el Finisher Legacy Tag:`,
    steps: [
        'Elige en la tienda la colección de tu carrera.',
        'Te llega listo, con su correa. Nada que personalizar.',
        'Acerca tu celular al tag y activa tu historia con fotos, tiempo y dedicatorias.',
    ],
    actions: [
        {
            label: 'Ver cómo funciona',
            scrollTo: ['#como-funciona', '[data-guide="steps"]'],
        },
        { label: 'Ir a la tienda', href: '/tienda', primary: true },
        { label: 'Activar mi tag', href: '/activar' },
        ...joinAction(ctx),
    ],
});

const store: TopicBuilder = (ctx) => ({
    key: 'store',
    context: 'Tienda',
    message:
        'Cada colección es de una carrera o destino. Elige la tuya por lugar, disciplina o año; todas llevan el mismo tag premium.',
    steps: [
        'Abre una colección para ver sus colores y detalles.',
        'Agrégala al carrito y paga seguro con tarjeta.',
        'Cuando llegue, acércale tu celular y se activa en segundos con NFC.',
    ],
    actions: [
        { label: 'Ver colecciones', href: '/colecciones', primary: true },
        { label: 'Cómo se activa', href: '/activar' },
        ...joinAction(ctx),
    ],
});

const product: TopicBuilder = () => ({
    key: 'product',
    context: 'Producto',
    message:
        'Aquí ves el tag de esta colección. Revisa las fotos, elige tu opción y agrégalo al carrito.',
    steps: [
        'Te llega terminado de fábrica, con su correa.',
        'El costo de envío se define al confirmar tu pedido.',
        'Se activa acercando el celular: sin apps ni códigos.',
    ],
    actions: [
        { label: 'Ver mi carrito', href: '/carrito', primary: true },
        { label: 'Seguir viendo', href: '/tienda' },
    ],
});

const cart: TopicBuilder = (ctx) => ({
    key: 'cart',
    context: 'Tu carrito',
    message:
        'Revisa tus piezas y cantidades. Cuando estés listo, pasa a pagar: el pago es seguro con tarjeta vía OpenPay y tus datos van cifrados.',
    actions: ctx.signedIn
        ? [
              { label: 'Ir a pagar', href: '/checkout', primary: true },
              { label: 'Seguir comprando', href: '/tienda' },
          ]
        : [
              {
                  label: 'Entrar para pagar',
                  href: ctx.loginUrl(),
                  primary: true,
              },
              { label: 'Seguir comprando', href: '/tienda' },
          ],
});

const checkout: TopicBuilder = () => ({
    key: 'checkout',
    context: 'Pago seguro',
    message:
        'Ya casi. Llena tus datos de envío y paga con tarjeta de crédito o débito; el cobro lo procesa OpenPay de forma segura. Te llega la confirmación por correo.',
    actions: [
        { label: 'Volver al carrito', href: '/carrito' },
        { label: '¿Dudas? Escríbenos', href: '/contact' },
    ],
});

const orderDone: TopicBuilder = () => ({
    key: 'order-done',
    context: '¡Pedido listo!',
    message:
        '¡Gracias por tu compra! Te avisamos por correo cuando tu pedido salga. Cuando llegue, acerca tu celular al tag para activarlo.',
    actions: [
        { label: 'Ver mis pedidos', href: '/mis-pedidos', primary: true },
        { label: 'Cómo activarlo', href: '/activar' },
    ],
});

const orders: TopicBuilder = () => ({
    key: 'orders',
    context: 'Mis pedidos',
    message:
        'Aquí ves el estado de cada pedido y su seguimiento. Si algo no cuadra, escríbenos y lo resolvemos.',
    actions: [
        { label: 'Ir a la tienda', href: '/tienda' },
        { label: 'Contacto', href: '/contact' },
    ],
});

const activation: TopicBuilder = (ctx) => ({
    key: 'activation',
    context: 'Activa tu tag',
    message: 'Activar tu Finisher Legacy Tag toma menos de un minuto:',
    steps: [
        'Desbloquea tu celular y activa el NFC (en iPhone ya viene encendido).',
        'Acerca la parte de arriba del celular al centro del tag.',
        'Toca la notificación que aparece y sigue los pasos para crear tu historia.',
    ],
    actions: ctx.signedIn
        ? [{ label: 'Ver Mi Legacy', href: '/mi-legacy', primary: true }]
        : [
              {
                  label: 'Entrar para activarlo',
                  href: ctx.loginUrl(),
                  primary: true,
              },
              { label: 'Crear cuenta', href: ctx.loginUrl('registro') },
          ],
});

const myLegacy: TopicBuilder = () => ({
    key: 'my-legacy',
    context: 'Mi Legacy',
    message:
        'Aquí viven tus historias. Edita cada una con fotos, tu tiempo y dedicatorias; quien acerque su celular a tu tag la verá.',
    actions: [
        { label: 'Activar otro tag', href: '/activar', primary: true },
        { label: 'Comunidad', href: '/comunidad' },
    ],
});

const community: TopicBuilder = (ctx) => ({
    key: 'community',
    context: 'Comunidad',
    message: ctx.signedIn
        ? 'Comparte tus carreras: publica fotos de tu medalla, tu tag o tu meta, y celebra las de tus amigos.'
        : 'Mira lo que comparten otros finishers. Para publicar, seguir y celebrar necesitas una cuenta.',
    steps: ctx.signedIn
        ? [
              'Toca «Publicar» y elige tus fotos.',
              'Agrega un texto corto y tu carrera.',
              'Encuentra amigos en Personas y síguelos.',
          ]
        : undefined,
    actions: ctx.signedIn
        ? [
              {
                  label: 'Encontrar amigos',
                  href: '/comunidad/personas',
                  primary: true,
              },
          ]
        : [
              {
                  label: 'Crea tu cuenta',
                  href: ctx.loginUrl('registro'),
                  primary: true,
              },
              { label: 'Ver personas', href: '/comunidad/personas' },
          ],
});

const people: TopicBuilder = (ctx) => ({
    key: 'people',
    context: 'Personas',
    message:
        'Busca por nombre o usuario y sigue a quien corre contigo. Sus publicaciones aparecerán en tu comunidad.',
    actions: [
        { label: 'Volver a Comunidad', href: '/comunidad' },
        ...joinAction(ctx),
    ],
});

const profile: TopicBuilder = (ctx) => ({
    key: 'profile',
    context: 'Perfil',
    message:
        'Este es un perfil de finisher: sus historias, medallas y publicaciones. Síguelo para ver lo que comparte.',
    actions: [
        { label: 'Encontrar más personas', href: '/comunidad/personas' },
        ...joinAction(ctx),
    ],
});

const myProfile: TopicBuilder = (ctx) => ({
    key: 'my-profile',
    context: 'Tu perfil',
    message:
        'Tu perfil es tu vitrina: agrega foto, una frase y tus carreras para que tus amigos te encuentren.',
    actions: [
        ...(ctx.username
            ? [
                  {
                      label: 'Ver mi perfil público',
                      href: `/@${ctx.username}`,
                      primary: true,
                  },
              ]
            : []),
        { label: 'Ajustes de cuenta', href: '/settings/profile' },
    ],
});

const settings: TopicBuilder = () => ({
    key: 'settings',
    context: 'Ajustes',
    message:
        'Aquí cambias tus datos, tu contraseña y la seguridad de tu cuenta. Los cambios se guardan al momento.',
    actions: [
        { label: 'Datos personales', href: '/settings/profile' },
        { label: 'Seguridad', href: '/settings/security' },
    ],
});

const events: TopicBuilder = () => ({
    key: 'events',
    context: 'Eventos',
    message:
        'Encuentra tu próxima carrera: filtra por nombre, deporte o estado y entra para ver detalles y prerregistro.',
    actions: [{ label: 'Ir a la tienda', href: '/tienda' }],
});

const dashboard: TopicBuilder = (ctx) => ({
    key: 'dashboard',
    context: 'Tu espacio',
    message: `${hello(ctx)} Desde aquí llegas a tus historias, tus pedidos y tu comunidad.`,
    actions: [
        { label: 'Mi Legacy', href: '/mi-legacy', primary: true },
        { label: 'Mis pedidos', href: '/mis-pedidos' },
        { label: 'Comunidad', href: '/comunidad' },
    ],
});

const contact: TopicBuilder = () => ({
    key: 'contact',
    context: 'Contacto',
    message:
        'Cuéntanos qué necesitas y te respondemos por correo. Si es sobre un pedido, incluye su número para ir más rápido.',
    actions: [{ label: 'Mis pedidos', href: '/mis-pedidos' }],
});

const fallback: TopicBuilder = (ctx) => ({
    key: 'general',
    context: 'Tu guía',
    message: `${hello(ctx)} ¿A dónde te llevo?`,
    actions: [
        { label: 'Tienda', href: '/tienda', primary: true },
        { label: 'Activar mi tag', href: '/activar' },
        { label: 'Comunidad', href: '/comunidad' },
        ...(ctx.signedIn
            ? [{ label: 'Mi Legacy', href: '/mi-legacy' }]
            : joinAction(ctx)),
    ],
});

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
