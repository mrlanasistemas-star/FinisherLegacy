<script lang="ts">
/**
 * `<I18nT>` — ONE translatable sentence with markup in the middle
 * (docs/renovacion/idiomas.md §Frases con enlaces). The sentence is a
 * single string with `{markers}`; each marker is filled by the slot of the
 * same name, wherever the translation puts it:
 *
 *     <I18nT :text="$t('¿Ya tienes cuenta? {link}')">
 *         <template #link>
 *             <Link :href="login()">{{ $t('Inicia sesión') }}</Link>
 *         </template>
 *     </I18nT>
 *
 * Never glue pieces (`$t('¿Ya tienes cuenta?') + <a>`): word order changes
 * between languages. `text` is already translated — pass `$t('…')` with a
 * LITERAL string so `php artisan i18n:sync` finds it; other placeholders
 * (`{count}`) are filled by `$t` as usual. Renders no wrapper element. A
 * marker without a slot is left as written.
 */
import { defineComponent } from 'vue';
import { splitMarkers } from '@/i18n';

export default defineComponent({
    name: 'I18nT',
    inheritAttrs: false,
    props: {
        text: { type: String, required: true },
    },
    setup(props, { slots }) {
        return () =>
            splitMarkers(props.text, Object.keys(slots)).map((segment) =>
                segment.type === 'text'
                    ? segment.value
                    : slots[segment.name]?.(),
            );
    },
});
</script>
