<script setup lang="ts">
/**
 * Administración › Alertas › Preferencias: per alert category this admin can
 * receive, whether it also arrives by email and/or push. The topbar bell
 * always gets every alert. docs/renovacion/notificaciones.md §Alertas admin.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Mail, Smartphone } from '@lucide/vue';
import ToggleSwitch from '@/components/notifications/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import alerts from '@/routes/admin/alerts';

type Category = {
    key: string;
    label: string;
    description: string;
    email: boolean;
    push: boolean;
};

const props = defineProps<{
    categories: Category[];
    pushAvailable: boolean;
    devicesCount: number;
    email: string | null;
}>();

const form = useForm({
    preferences: props.categories.map((c) => ({
        key: c.key,
        email: c.email,
        push: c.push,
    })),
});

function save() {
    form.put(alerts.preferences.update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Preferencias de alertas" />

    <div class="w-full max-w-4xl px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <Link
            :href="alerts.index()"
            class="mb-4 inline-flex min-h-11 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Alertas
        </Link>

        <h1 class="text-xl font-semibold">Preferencias de alertas</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            La campana del panel recibe siempre todas las alertas de tus
            permisos. Aquí eliges cuáles te llegan también por correo
            <template v-if="email">({{ email }})</template> o como push a tu
            teléfono.
        </p>

        <div
            v-if="!pushAvailable || devicesCount === 0"
            class="mt-4 flex gap-3 rounded-2xl border border-fl-gold/40 bg-fl-cream px-4 py-3 text-sm"
        >
            <Smartphone class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
            <p v-if="!pushAvailable">
                El envío push no está activo en el servidor
                (<code>PUSH_PROVIDER=expo</code>). Las alertas seguirán llegando
                a la campana y por correo.
            </p>
            <p v-else>
                Para recibir push, inicia sesión con esta cuenta en la app de
                Finisher Legacy y permite las notificaciones.
            </p>
        </div>

        <form class="mt-6" @submit.prevent="save">
            <div
                class="overflow-hidden rounded-2xl border border-border bg-white shadow-fl-micro"
            >
                <div
                    class="hidden grid-cols-[minmax(0,1fr)_5rem_5rem] items-center gap-2 border-b border-border bg-fl-paper/60 px-4 py-2 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase sm:grid"
                >
                    <span>Categoría</span>
                    <span class="flex items-center justify-center gap-1"
                        ><Mail class="size-3.5" /> Correo</span
                    >
                    <span class="flex items-center justify-center gap-1"
                        ><Bell class="size-3.5" /> Push</span
                    >
                </div>
                <ul class="divide-y divide-border">
                    <li
                        v-for="(category, index) in categories"
                        :key="category.key"
                        class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_5rem_5rem]"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ category.label }}
                            </p>
                            <p class="text-[13px] text-muted-foreground">
                                {{ category.description }}
                            </p>
                        </div>
                        <div class="flex justify-center">
                            <ToggleSwitch
                                v-model="form.preferences[index].email"
                                :label="`${category.label}: correo`"
                            />
                        </div>
                        <div class="flex justify-center">
                            <ToggleSwitch
                                v-model="form.preferences[index].push"
                                :label="`${category.label}: push`"
                            />
                        </div>
                    </li>
                </ul>
                <p
                    v-if="!categories.length"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Tus permisos actuales no incluyen ninguna categoría de
                    alertas.
                </p>
            </div>

            <div class="mt-4 flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing || !categories.length"
                >
                    {{
                        form.processing ? 'Guardando…' : 'Guardar preferencias'
                    }}
                </Button>
            </div>
        </form>
    </div>
</template>
