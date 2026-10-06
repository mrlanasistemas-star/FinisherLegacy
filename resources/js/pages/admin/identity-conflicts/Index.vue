<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, ShieldQuestion, UserPlus, X } from '@lucide/vue';
import { ref } from 'vue';
import HelpPopover from '@/components/HelpPopover.vue';
import Pagination from '@/components/public/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type IncomingData = {
    first_name?: string;
    last_name?: string;
    full_name?: string;
    email?: string | null;
    phone?: string | null;
    birth_date?: string | null;
};

type Candidate = {
    id: number;
    full_name: string;
    email: string | null;
    birth_date: string | null;
};

type Conflict = {
    id: number;
    source_type: string;
    reason: string;
    confidence: number | null;
    confidence_band: string | null;
    incoming: IncomingData;
    event: string | null;
    candidate: Candidate | null;
    candidates_count: number;
    created_at: string;
};

defineProps<{
    conflicts: {
        data: Conflict[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

const pendingIds = ref(new Set<number>());

function resolve(
    conflict: Conflict,
    resolution: 'link_existing' | 'create_new' | 'ignore',
) {
    pendingIds.value.add(conflict.id);
    router.post(
        `/admin/identity-conflicts/${conflict.id}/resolve`,
        { resolution, athlete_id: conflict.candidate?.id ?? null },
        {
            preserveScroll: true,
            onFinish: () => pendingIds.value.delete(conflict.id),
        },
    );
}

const bandClass: Record<string, string> = {
    Alta: 'border-emerald-500/30 text-emerald-700',
    Media: 'border-amber-500/30 text-amber-700',
    Baja: 'border-foreground/15 text-muted-foreground',
};
</script>

<template>
    <Head title="Conflictos de identidad" />

    <div class="p-4 md:p-8">
        <h1
            class="mb-1 flex items-center gap-1.5 text-xl font-bold text-foreground"
        >
            <ShieldQuestion class="size-5 text-fl-gold-ink" />
            Conflictos de identidad
        </h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Coincidencias que el sistema no pudo vincular automáticamente —
            nunca se fusiona por nombre solamente. La confianza es una ayuda, no
            una certeza: revísalo antes de decidir.
            <HelpPopover
                title="¿Por qué existe esto?"
                text="Un nombre solo nunca es suficiente para fusionar dos identidades — dos personas distintas pueden llamarse igual. Cuando la señal es fuerte pero no absoluta (por ejemplo nombre + fecha de nacimiento, o varios candidatos posibles), el sistema pausa aquí en vez de adivinar."
            />
        </p>

        <div class="space-y-4">
            <div
                v-for="conflict in conflicts.data"
                :key="conflict.id"
                class="rounded-xl border border-border bg-card/30 p-4"
                :class="{ 'opacity-50': pendingIds.has(conflict.id) }"
            >
                <div class="mb-3 flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground/80"
                    >
                        <span>{{
                            conflict.event ?? conflict.source_type
                        }}</span>
                        <span>·</span>
                        <span>{{ conflict.created_at }}</span>
                    </div>
                    <Badge
                        v-if="conflict.confidence_band"
                        variant="outline"
                        :class="bandClass[conflict.confidence_band]"
                    >
                        Confianza {{ conflict.confidence_band.toLowerCase() }}
                    </Badge>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        class="rounded-lg border border-border bg-background/40 p-3"
                    >
                        <p
                            class="mb-1 text-xs text-muted-foreground/80 uppercase"
                        >
                            Datos importados
                        </p>
                        <p class="text-foreground">
                            {{
                                conflict.incoming.full_name ??
                                `${conflict.incoming.first_name ?? ''} ${conflict.incoming.last_name ?? ''}`
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ conflict.incoming.email ?? 'Sin email' }} ·
                            {{
                                conflict.incoming.birth_date ??
                                'Sin fecha de nacimiento'
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-border bg-background/40 p-3"
                    >
                        <p
                            class="mb-1 text-xs text-muted-foreground/80 uppercase"
                        >
                            Posible atleta
                            <span v-if="conflict.candidates_count > 1">
                                (+{{ conflict.candidates_count - 1 }} más)
                            </span>
                        </p>
                        <template v-if="conflict.candidate">
                            <p class="text-foreground">
                                {{ conflict.candidate.full_name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ conflict.candidate.email ?? 'Sin email' }} ·
                                {{
                                    conflict.candidate.birth_date ??
                                    'Sin fecha de nacimiento'
                                }}
                            </p>
                        </template>
                        <p v-else class="text-sm text-muted-foreground/80">
                            Sin candidato específico.
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="
                            !conflict.candidate || pendingIds.has(conflict.id)
                        "
                        @click="resolve(conflict, 'link_existing')"
                    >
                        <Check class="size-3.5" />
                        Es la misma persona
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-border text-foreground hover:bg-foreground/5"
                        :disabled="pendingIds.has(conflict.id)"
                        @click="resolve(conflict, 'create_new')"
                    >
                        <UserPlus class="size-3.5" />
                        Crear nuevo atleta
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-border text-muted-foreground hover:bg-foreground/5"
                        :disabled="pendingIds.has(conflict.id)"
                        @click="resolve(conflict, 'ignore')"
                    >
                        <X class="size-3.5" />
                        Ignorar por ahora
                    </Button>
                </div>
            </div>

            <div
                v-if="!conflicts.data.length"
                class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground/80"
            >
                Sin conflictos pendientes.
            </div>
        </div>

        <Pagination :links="conflicts.links" class="mt-6" />
    </div>
</template>
