<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, Inbox, Mail, MailOpen } from '@lucide/vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { CONTENT_AREA_NAV } from '@/config/areaNav';
import { shortDate } from '@/lib/datetime';

type Message = {
    id: number;
    name: string;
    company: string | null;
    email: string;
    type: string;
    type_label: string;
    message: string;
    status: 'new' | 'read' | 'archived';
    status_label: string;
    created_at: string;
};

const props = defineProps<{
    messages: {
        data: Message[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: string | null; type: string | null };
    types: { value: string; label: string }[];
    counts: { new: number };
}>();

const statusTabs = [
    { value: null, label: 'Bandeja' },
    { value: 'new', label: 'Nuevos' },
    { value: 'read', label: 'Leídos' },
    { value: 'archived', label: 'Archivados' },
];

function filter(
    status: string | null,
    type: string | null = props.filters.type,
) {
    router.get(
        '/admin/messages',
        { status: status ?? undefined, type: type ?? undefined },
        { preserveState: true, preserveScroll: true },
    );
}

function setStatus(message: Message, status: Message['status']) {
    router.patch(
        `/admin/messages/${message.id}`,
        { status },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Mensajes de contacto" />

    <div class="mx-auto w-full max-w-5xl px-4 py-4 sm:px-6 md:py-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <div class="mb-6">
            <h1 class="flex items-center gap-2 text-xl font-semibold">
                <Inbox class="size-5 text-fl-gold-ink" />
                Mensajes de contacto
                <span
                    v-if="counts.new"
                    class="rounded-full bg-fl-gold px-2 py-0.5 text-xs font-semibold text-fl-black"
                    >{{ counts.new }} nuevos</span
                >
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Lo que llega desde el formulario público de /contact.
            </p>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <button
                v-for="tab in statusTabs"
                :key="tab.label"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm transition-colors"
                :class="
                    filters.status === tab.value
                        ? 'bg-foreground text-background'
                        : 'border border-border text-muted-foreground hover:text-foreground'
                "
                @click="filter(tab.value)"
            >
                {{ tab.label }}
            </button>
            <FancySelect
                :model-value="filters.type"
                :options="[{ value: null, label: 'Todos los tipos' }, ...types]"
                aria-label="Tipo de contacto"
                class="ml-auto h-9 w-56 rounded-full"
                @update:model-value="
                    (value) => filter(filters.status, value as string | null)
                "
            />
        </div>

        <div class="space-y-3">
            <article
                v-for="message in messages.data"
                :key="message.id"
                class="fl-card p-5"
                :class="message.status === 'new' ? 'border-fl-gold/60' : ''"
            >
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{
                                message.name
                            }}</span>
                            <span
                                v-if="message.company"
                                class="text-sm text-muted-foreground"
                                >· {{ message.company }}</span
                            >
                            <span
                                class="rounded-full bg-fl-cream px-2 py-0.5 text-[11px] font-medium text-fl-gold-ink"
                                >{{ message.type_label }}</span
                            >
                        </p>
                        <a
                            :href="`mailto:${message.email}`"
                            class="text-sm text-muted-foreground hover:text-foreground"
                            >{{ message.email }}</a
                        >
                    </div>
                    <span class="text-xs text-muted-foreground">{{
                        shortDate(message.created_at)
                    }}</span>
                </div>
                <p class="mt-3 text-sm leading-relaxed whitespace-pre-line">
                    {{ message.message }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button as-child size="sm" variant="outline">
                        <a :href="`mailto:${message.email}`">
                            <Mail class="size-3.5" />
                            Responder
                        </a>
                    </Button>
                    <Button
                        v-if="message.status === 'new'"
                        size="sm"
                        variant="ghost"
                        @click="setStatus(message, 'read')"
                    >
                        <MailOpen class="size-3.5" />
                        Marcar como leído
                    </Button>
                    <Button
                        v-if="message.status !== 'archived'"
                        size="sm"
                        variant="ghost"
                        @click="setStatus(message, 'archived')"
                    >
                        <Archive class="size-3.5" />
                        Archivar
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="ghost"
                        @click="setStatus(message, 'read')"
                    >
                        Restaurar
                    </Button>
                </div>
            </article>

            <p
                v-if="!messages.data.length"
                class="fl-card p-10 text-center text-sm text-muted-foreground"
            >
                No hay mensajes aquí.
                <Link
                    href="/contact"
                    class="font-medium text-foreground underline underline-offset-4"
                    >Ver formulario público</Link
                >
            </p>
        </div>

        <div class="mt-8">
            <Pagination :links="messages.links" />
        </div>
    </div>
</template>
