<script setup lang="ts">
/**
 * Aviso de privacidad (web + app). The App Store / Play listings point here
 * (docs/store in the mobile repo), so every data flow the app declares in
 * its privacy labels / Data safety form must be described below — change
 * them together. Legal review pending before launch: identity and address
 * of the "responsable" (LFPDPPP), see docs/store/pendientes-externos.md.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import I18nT from '@/components/i18n/I18nT.vue';
import LegalDocument from '@/components/marketing/LegalDocument.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { useI18n } from '@/i18n';
import { contact } from '@/routes';
import { show as accountDeletion } from '@/routes/account-deletion';

const { t } = useI18n();
const page = usePage();
const email = computed(
    () =>
        (page.props.company as { channels?: { email?: string } } | undefined)
            ?.channels?.email ?? 'hola@finisherlegacy.com',
);

type Section = {
    id: string;
    title: string;
    paragraphs?: string[];
    items?: string[];
};

const sections = computed<Section[]>(() => [
    {
        id: 'responsable',
        title: t('1. Quién es responsable de tus datos'),
        paragraphs: [
            t(
                'Finisher Legacy (en adelante, «Finisher Legacy», «nosotros») opera el sitio finisherlegacy.com y la app móvil Finisher Legacy para iOS y Android, desde México, y es responsable del tratamiento de los datos personales que se describen en este aviso, conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.',
            ),
        ],
    },
    {
        id: 'datos',
        title: t('2. Qué datos recopilamos'),
        items: [
            t(
                'Cuenta: nombre, apellido, correo electrónico y contraseña (guardada cifrada). Teléfono, solo si lo agregas.',
            ),
            t(
                'Perfil: nombre de usuario, biografía, ciudad, estado, país, foto de perfil y portada, y la visibilidad que elijas (público o privado).',
            ),
            t(
                'Historial deportivo: eventos, distancias, tiempos, posiciones y número de corredor que publican los organizadores, y las medallas, historias y recuerdos que tú agregas.',
            ),
            t(
                'Fotos y videos: solo los que eliges subir desde tu galería o tomar con la cámara (medallas, momentos, recuerdos y perfil). La app no accede a tu galería sin que tú elijas el archivo.',
            ),
            t(
                'Contenido de la comunidad: momentos, comentarios, reacciones, seguidores, bloqueos, reportes y mensajes de ánimo (texto o audio) que envías o recibes.',
            ),
            t(
                'Ubicación precisa: solo cuando inicias el seguimiento en vivo de una carrera, y solo mientras está activo. Se comparte con quien tenga tu enlace de seguimiento.',
            ),
            t(
                'Finisher Legacy Tag (NFC): el identificador del tag y su vínculo con tu cuenta cuando lo activas. La app usa NFC solo cuando tú lo pides.',
            ),
            t(
                'Compras: productos, dirección de envío, teléfono de contacto e historial de pedidos. Los datos de tu tarjeta los procesa directamente el procesador de pagos; Finisher Legacy no los recibe ni los guarda.',
            ),
            t(
                'Dispositivo: el token para enviarte notificaciones push, la plataforma (iOS o Android) y el nombre del dispositivo, si activas las notificaciones.',
            ),
            t(
                'Soporte: lo que nos escribes por el formulario de contacto o por correo.',
            ),
        ],
    },
    {
        id: 'uso',
        title: t('3. Para qué los usamos'),
        items: [
            t('Crear y operar tu cuenta, tu Legacy ID y tu perfil.'),
            t(
                'Mostrar tu historial deportivo, tus medallas, tus recuerdos y tus momentos según la visibilidad que elijas.',
            ),
            t('Activar y abrir tu Finisher Legacy Tag.'),
            t(
                'Mostrar tu avance en vivo a las personas con las que compartes tu enlace.',
            ),
            t('Procesar, enviar y dar seguimiento a tus pedidos.'),
            t(
                'Enviarte notificaciones sobre tu actividad, tus pedidos y tu comunidad (puedes desactivarlas en Configuración o en tu teléfono).',
            ),
            t(
                'Moderar la comunidad, atender reportes y prevenir fraude y abuso.',
            ),
            t('Responder tus solicitudes de soporte.'),
        ],
        paragraphs: [
            t(
                'No vendemos tus datos, no mostramos publicidad, no usamos tus datos para publicidad dirigida ni te rastreamos en apps o sitios de terceros.',
            ),
        ],
    },
    {
        id: 'compartir',
        title: t('4. Con quién los compartimos'),
        items: [
            t(
                'Otras personas usuarias: lo que publicas con visibilidad pública o para tus seguidores, y tu ubicación en vivo solo con quien tenga tu enlace.',
            ),
            t(
                'Organizadores de eventos: los datos de tu participación que ellos mismos nos comparten.',
            ),
            t(
                'Proveedores que nos ayudan a operar el servicio, solo para ese fin: alojamiento y almacenamiento, procesadores de pago, servicios de notificaciones push (Expo, Apple y Google), correo electrónico y paquetería.',
            ),
            t('Autoridades, cuando una ley u orden válida lo exija.'),
        ],
    },
    {
        id: 'conservacion',
        title: t('5. Cuánto tiempo los conservamos'),
        paragraphs: [
            t(
                'Conservamos tus datos mientras tu cuenta esté activa. Los puntos GPS del seguimiento en vivo se eliminan automáticamente a los 90 días. Al eliminar tu cuenta borramos o anonimizamos tus datos como se explica en la siguiente sección; los registros de pedidos y pagos se conservan, sin datos que te identifiquen, durante el plazo que exigen las leyes fiscales y contables.',
            ),
        ],
    },
    {
        id: 'eliminacion',
        title: t('6. Eliminar tu cuenta'),
        paragraphs: [
            t(
                'Puedes eliminar tu cuenta en cualquier momento desde la app (Configuración › Contraseña y cuenta › Eliminar mi cuenta) o desde la web (Ajustes › Perfil). Se eliminan tu perfil, fotos, momentos, comentarios, reacciones, seguidores, bloqueos, dispositivos y sesiones, y se anonimizan tu nombre, correo y teléfono. Se conservan sin datos que te identifiquen tus pedidos y pagos y los resultados oficiales de eventos, y tus medallas personales pasan a ser privadas.',
            ),
            t(
                'Si ya no tienes la app, solicita la eliminación en la página «Eliminar tu cuenta».',
            ),
        ],
    },
    {
        id: 'derechos',
        title: t('7. Tus derechos (ARCO)'),
        paragraphs: [
            t(
                'Puedes acceder a tus datos, rectificarlos, cancelarlos u oponerte a su uso, así como revocar tu consentimiento. Gran parte puedes hacerlo tú desde la app (perfil, privacidad, notificaciones, permisos del teléfono). Para lo demás, escríbenos desde la página de contacto o al correo indicado abajo; respondemos en un máximo de 20 días hábiles.',
            ),
        ],
    },
    {
        id: 'permisos',
        title: t('8. Permisos del teléfono'),
        items: [
            t(
                'Cámara y fotos: para que tomes o elijas las imágenes que quieres subir.',
            ),
            t('Ubicación: solo para el seguimiento en vivo que tú inicias.'),
            t('NFC: para activar y leer tu Finisher Legacy Tag.'),
            t('Notificaciones: para avisarte de tu actividad.'),
        ],
        paragraphs: [
            t(
                'Puedes retirar cualquier permiso en los ajustes de tu teléfono; la función correspondiente dejará de estar disponible.',
            ),
        ],
    },
    {
        id: 'menores',
        title: t('9. Menores de edad'),
        paragraphs: [
            t(
                'Finisher Legacy está dirigido a personas mayores de 18 años. Si eres menor de edad, solo puedes usarlo con la autorización y supervisión de tu madre, padre o tutor. Si sabemos que una cuenta pertenece a un menor sin esa autorización, la eliminaremos.',
            ),
        ],
    },
    {
        id: 'seguridad',
        title: t('10. Seguridad'),
        paragraphs: [
            t(
                'Toda la comunicación viaja cifrada (HTTPS). En la app, tu sesión se guarda en el almacenamiento seguro del teléfono. Limitamos el acceso a tus datos al personal que lo necesita para operar el servicio.',
            ),
        ],
    },
    {
        id: 'cambios',
        title: t('11. Cambios a este aviso'),
        paragraphs: [
            t(
                'Si cambiamos este aviso de forma relevante te lo informaremos en la app o por correo antes de que el cambio aplique.',
            ),
        ],
    },
]);

const toc = computed(() => [
    ...sections.value.map(({ id, title }) => ({ id, title })),
    { id: 'contacto', title: t('12. Contacto') },
]);
</script>

<template>
    <SeoHead
        :title="$t('Aviso de privacidad')"
        :description="
            $t(
                'Aviso de privacidad de Finisher Legacy: qué datos recopilamos en la web y en la app, para qué, con quién los compartimos y cómo eliminar tu cuenta.',
            )
        "
    />

    <LegalDocument
        :eyebrow="$t('Legal')"
        :title="$t('Aviso de privacidad')"
        :intro="
            $t('Aplica al sitio finisherlegacy.com y a la app Finisher Legacy.')
        "
        :toc="toc"
    >
        <section v-for="section in sections" :id="section.id" :key="section.id">
            <h2>{{ section.title }}</h2>
            <ul v-if="section.items">
                <li v-for="item in section.items" :key="item">
                    {{ item }}
                </li>
            </ul>
            <p v-for="paragraph in section.paragraphs ?? []" :key="paragraph">
                {{ paragraph }}
            </p>
            <p v-if="section.id === 'eliminacion'">
                <Link :href="accountDeletion()">{{
                    $t('Ir a «Eliminar tu cuenta»')
                }}</Link>
            </p>
        </section>

        <section id="contacto">
            <h2>{{ $t('12. Contacto') }}</h2>
            <p>
                <I18nT
                    :text="
                        $t(
                            'Para cualquier duda sobre tus datos escríbenos a {email} o desde la {link}.',
                        )
                    "
                >
                    <template #email
                        ><a :href="`mailto:${email}`">{{ email }}</a></template
                    >
                    <template #link
                        ><Link :href="contact()">{{
                            $t('página de contacto')
                        }}</Link></template
                    >
                </I18nT>
            </p>
        </section>
    </LegalDocument>
</template>
