<script setup lang="ts">
/**
 * Términos y condiciones (web + app). Includes the community rules with
 * zero tolerance for objectionable content and abusive users that App
 * Store guideline 1.2 and Google Play's UGC policy require; the app's
 * sign-up asks to accept them explicitly. Legal review pending before
 * launch (docs/store/pendientes-externos.md in the mobile repo).
 */
import { Link } from '@inertiajs/vue3';
import LegalDocument from '@/components/marketing/LegalDocument.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { contact, privacy } from '@/routes';
import { show as accountDeletion } from '@/routes/account-deletion';

type Section = {
    id: string;
    title: string;
    paragraphs?: string[];
    items?: string[];
};

const sections: Section[] = [
    {
        id: 'aceptacion',
        title: '1. Aceptación',
        paragraphs: [
            'Estos términos regulan el uso del sitio finisherlegacy.com y de la app Finisher Legacy. Al crear una cuenta aceptas estos términos, incluidas las reglas de la comunidad, y el Aviso de privacidad. Si no estás de acuerdo, no uses el servicio.',
        ],
    },
    {
        id: 'cuenta',
        title: '2. Tu cuenta',
        items: [
            'Debes ser mayor de 18 años, o contar con la autorización y supervisión de tu madre, padre o tutor.',
            'La información que registras debe ser verdadera y tuya. Eres responsable de mantener segura tu contraseña.',
            'Puedes eliminar tu cuenta cuando quieras desde la app, desde la web o con una solicitud en la página «Eliminar tu cuenta».',
        ],
    },
    {
        id: 'servicio',
        title: '3. Qué ofrece Finisher Legacy',
        paragraphs: [
            'Finisher Legacy reúne tu historia deportiva: resultados de eventos, medallas, fotos, recuerdos y momentos que compartes con tu comunidad, seguimiento en vivo de tus carreras y el Finisher Legacy Tag, un producto físico con NFC que abre tu historia al acercarlo a un teléfono. Crear una cuenta y usar la app no tiene costo.',
        ],
    },
    {
        id: 'comunidad',
        title: '4. Reglas de la comunidad (tolerancia cero)',
        paragraphs: [
            'No toleramos contenido ofensivo ni personas usuarias abusivas. Está prohibido publicar, comentar o enviar:',
        ],
        items: [
            'Acoso, amenazas, intimidación o discurso de odio por origen, género, religión, orientación, discapacidad o cualquier otra condición.',
            'Contenido sexual o desnudos, y cualquier contenido que sexualice a menores.',
            'Violencia gráfica, autolesiones o actividades ilegales.',
            'Spam, fraudes, suplantación de identidad o datos personales de otras personas sin su permiso.',
            'Contenido que infrinja derechos de autor, de imagen o de marca de terceros.',
        ],
    },
    {
        id: 'moderacion',
        title: '5. Reportes, bloqueos y moderación',
        items: [
            'Puedes reportar cualquier perfil, momento o comentario desde el menú «…» y bloquear a cualquier persona desde su perfil. Al bloquearla, dejan de ver su actividad mutuamente.',
            'Filtramos automáticamente lenguaje ofensivo y nuestro equipo revisa los reportes en un plazo máximo de 24 horas.',
            'Podemos ocultar o eliminar contenido que incumpla estas reglas y suspender o cerrar, sin previo aviso, las cuentas de quienes las incumplan.',
        ],
    },
    {
        id: 'contenido',
        title: '6. Tu contenido',
        paragraphs: [
            'El contenido que subes sigue siendo tuyo. Nos das permiso para alojarlo, mostrarlo y adaptarlo (por ejemplo, redimensionar una foto) solo para operar el servicio y según la visibilidad que elijas. Ese permiso termina cuando eliminas el contenido o tu cuenta.',
        ],
    },
    {
        id: 'compras',
        title: '7. Compras',
        items: [
            'En la app y en la web vendemos productos físicos (como el Finisher Legacy Tag). El pago se realiza con un procesador de pagos externo; Finisher Legacy no recibe ni guarda los datos de tu tarjeta.',
            'Precios, envíos, cambios y devoluciones se informan en cada producto y en el resumen de tu pedido.',
            'La app no vende contenido ni funciones digitales.',
        ],
    },
    {
        id: 'tag',
        title: '8. Finisher Legacy Tag',
        paragraphs: [
            'Cada tag es único. Una vez activado queda vinculado a tu cuenta y abre la historia que elijas, con la visibilidad que decidas. Podemos bloquear o corregir un vínculo en caso de error o uso indebido.',
        ],
    },
    {
        id: 'seguimiento',
        title: '9. Seguimiento en vivo',
        paragraphs: [
            'El seguimiento en vivo comparte tu ubicación solo mientras lo tienes activo y solo con quien tenga tu enlace. Es una herramienta para que tu gente te acompañe: no sustituye los servicios de emergencia ni la seguridad del evento.',
        ],
    },
    {
        id: 'responsabilidad',
        title: '10. Disponibilidad y responsabilidad',
        paragraphs: [
            'Trabajamos para que el servicio esté disponible y funcione bien, pero puede tener interrupciones o errores. Los resultados oficiales son responsabilidad de cada organizador. En la medida que la ley lo permita, Finisher Legacy no responde por daños indirectos derivados del uso del servicio.',
        ],
    },
    {
        id: 'cambios',
        title: '11. Cambios y ley aplicable',
        paragraphs: [
            'Podemos actualizar estos términos; si el cambio es relevante te avisaremos en la app o por correo antes de que aplique. Estos términos se rigen por las leyes de México.',
        ],
    },
];

const toc = [
    ...sections.map(({ id, title }) => ({ id, title })),
    { id: 'contacto', title: '12. Contacto' },
];
</script>

<template>
    <SeoHead
        title="Términos y condiciones"
        description="Términos y condiciones de Finisher Legacy en la web y en la app, incluidas las reglas de la comunidad."
    />

    <LegalDocument
        eyebrow="Legal"
        title="Términos y condiciones"
        intro="Aplican al sitio finisherlegacy.com y a la app Finisher Legacy."
        :toc="toc"
    >
        <section v-for="section in sections" :id="section.id" :key="section.id">
            <h2>{{ section.title }}</h2>
            <p v-for="paragraph in section.paragraphs ?? []" :key="paragraph">
                {{ paragraph }}
            </p>
            <ul v-if="section.items">
                <li v-for="item in section.items" :key="item">
                    {{ item }}
                </li>
            </ul>
        </section>

        <section id="contacto">
            <h2>12. Contacto</h2>
            <p>
                Dudas o reportes urgentes: escríbenos desde la
                <Link :href="contact()">página de contacto</Link>. Consulta
                también el
                <Link :href="privacy()">Aviso de privacidad</Link>
                y cómo
                <Link :href="accountDeletion()">eliminar tu cuenta</Link>.
            </p>
        </section>
    </LegalDocument>
</template>
