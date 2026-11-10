<script setup lang="ts">
/**
 * Ajustes › Notificaciones: per category, show it in the notification center
 * (app + web) and/or send it as push to the phone. Same preferences the app
 * edits (GET|PUT /api/v1/me/notifications/preferences), enforced on the
 * server. Browser (Web Push) notifications are not offered.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Smartphone } from '@lucide/vue';
import SettingsGroup from '@/components/account/SettingsGroup.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import ToggleSwitch from '@/components/notifications/ToggleSwitch.vue';
import { update } from '@/routes/notification-preferences';
import { index as notificationsIndex } from '@/routes/notifications';

type Category = {
    key: string;
    label: string;
    description: string;
    in_app: boolean;
    push: boolean;
};

const props = defineProps<{
    categories: Category[];
    pushAvailable: boolean;
    devicesCount: number;
}>();

const form = useForm({
    preferences: props.categories.map((c) => ({
        key: c.key,
        in_app: c.in_app,
        push: c.push,
    })),
});

function save() {
    form.put(update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Notificaciones')" />

    <h1 class="sr-only">{{ $t('Notificaciones') }}</h1>

    <form class="space-y-6" @submit.prevent="save">
        <div
            v-if="devicesCount === 0"
            class="flex gap-3 rounded-2xl border border-fl-gold/40 bg-fl-cream px-4 py-3 text-sm"
        >
            <Smartphone class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
            <p>
                {{
                    $t(
                        'Descarga la app e inicia sesión para recibir avisos en tu teléfono.',
                    )
                }}
            </p>
        </div>

        <SettingsGroup
            :title="$t('Qué te avisamos')"
            :note="
                $t(
                    '«En la app y la web» guarda el aviso en tu centro de notificaciones. «Push» además lo envía a tu teléfono.',
                )
            "
        >
            <div
                v-for="(category, index) in categories"
                :key="category.key"
                class="px-4 py-3.5"
            >
                <p class="text-[15px] font-medium text-foreground">
                    {{ category.label }}
                </p>
                <p class="mt-0.5 text-[13px] text-muted-foreground">
                    {{ category.description }}
                </p>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1">
                    <label class="flex items-center gap-1 text-sm">
                        <ToggleSwitch
                            v-model="form.preferences[index].in_app"
                            :label="
                                $t('{label}: en la app y la web', {
                                    label: category.label,
                                })
                            "
                        />
                        {{ $t('En la app y la web') }}
                    </label>
                    <label
                        class="flex items-center gap-1 text-sm"
                        :class="pushAvailable ? '' : 'text-muted-foreground'"
                    >
                        <ToggleSwitch
                            v-model="form.preferences[index].push"
                            :label="
                                $t('{label}: push al teléfono', {
                                    label: category.label,
                                })
                            "
                        />
                        {{ $t('Push al teléfono') }}
                    </label>
                </div>
            </div>

            <template #footer>
                <Link
                    :href="notificationsIndex()"
                    class="mr-auto text-sm font-medium text-fl-gold-ink hover:underline"
                >
                    {{ $t('Ver mis notificaciones') }}
                </Link>
                <div class="w-full sm:w-auto">
                    <SubmitButton
                        :processing="form.processing"
                        :label="$t('Guardar')"
                        :processing-label="$t('Guardando…')"
                        :arrow="false"
                    />
                </div>
            </template>
        </SettingsGroup>
    </form>
</template>
