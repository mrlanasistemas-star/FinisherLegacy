<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Award,
    Boxes,
    Check,
    Compass,
    Copy,
    Package,
    QrCode,
    Receipt,
    ShoppingBag,
    Trophy,
    UserCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import StaggerGroup from '@/components/motion/StaggerGroup.vue';
import FinisherMascot from '@/components/public/FinisherMascot.vue';
import MascotEmptyState from '@/components/public/MascotEmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { dashboard } from '@/routes';
import {
    create as createMedal,
    index as medalsIndex,
} from '@/routes/dashboard/medals';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import { show as legacyCodeShow } from '@/routes/legacy-code';
import type { DashboardProfileSummary, DashboardStats } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const { legacyId, profile, stats } = defineProps<{
    legacyId: string | null;
    profile: DashboardProfileSummary | null;
    stats: DashboardStats;
}>();

const page = usePage();
const firstName = computed(() => page.props.auth.user.first_name);

const copied = ref(false);

async function copyLegacyId() {
    if (!legacyId) {
        return;
    }

    await navigator.clipboard.writeText(legacyId);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1800);
}

const legacyCodeInput = ref('');
const legacyCodeDialogOpen = ref(false);

function goToLegacyCode() {
    if (!legacyCodeInput.value.trim()) {
        return;
    }

    router.visit(
        legacyCodeShow(legacyCodeInput.value.trim().toUpperCase()).url,
    );
}

const statCards = computed(() => [
    {
        label: 'Mis medallas',
        value: stats.medals,
        icon: Award,
        href: medalsIndex(),
    },
    {
        label: 'Mis eventos',
        value: stats.events,
        icon: Trophy,
        href: '/dashboard/my-events',
    },
    {
        label: 'Mis Legacy Plates',
        value: stats.plates,
        icon: Boxes,
        href: '/dashboard/my-plates',
    },
    {
        label: 'Mi equipo',
        value: stats.ownedProducts,
        icon: Package,
        href: '/dashboard/my-gear',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Welcome + Legacy ID -->
        <Reveal
            as="div"
            class="relative overflow-hidden rounded-2xl border border-fl-gold/20 bg-gradient-to-br from-fl-graphite via-fl-black to-fl-black p-6 md:p-8"
        >
            <div
                class="absolute -top-20 -right-20 size-56 rounded-full bg-fl-gold/10 blur-3xl"
            />
            <div
                class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-4">
                    <FinisherMascot
                        variant="small"
                        class="hidden shrink-0 sm:block"
                    />
                    <div>
                        <p class="text-sm text-white/50">Hola,</p>
                        <h1 class="text-2xl font-bold text-white md:text-3xl">
                            {{ firstName }}
                        </h1>
                    </div>
                </div>

                <div
                    v-if="legacyId"
                    class="flex items-center gap-3 rounded-xl border border-fl-gold/30 bg-fl-black/60 px-4 py-3"
                >
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-widest text-white/40 uppercase"
                        >
                            Legacy ID
                        </p>
                        <p class="font-mono text-lg font-semibold text-fl-gold">
                            {{ legacyId }}
                        </p>
                    </div>
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        class="text-white/60 hover:text-fl-gold"
                        aria-label="Copiar Legacy ID"
                        @click="copyLegacyId"
                    >
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>
            </div>
        </Reveal>

        <!-- Profile prompt -->
        <div
            v-if="!profile"
            class="rounded-2xl border border-dashed border-fl-gold/30 bg-fl-graphite/40 p-6 text-center"
        >
            <p class="font-medium text-white">
                Aún no has completado tu Legacy Profile.
            </p>
            <p class="mt-1 text-sm text-white/50">
                Elige tu username y hazlo público cuando quieras compartir tu
                colección.
            </p>
            <Button
                as-child
                class="mt-4 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link :href="editProfile()">Completar mi Legacy Profile</Link>
            </Button>
        </div>

        <!-- Profile completion -->
        <div
            v-else-if="profile.completion < 100"
            class="rounded-2xl border border-white/10 bg-fl-graphite/40 p-5"
        >
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-white/70">
                    Tu Legacy Profile está
                    <span class="font-semibold text-fl-gold"
                        >{{ profile.completion }}% completo</span
                    >.
                </p>
                <Link
                    :href="editProfile()"
                    class="shrink-0 text-sm font-medium text-fl-gold hover:text-fl-gold-soft"
                >
                    Completar
                </Link>
            </div>
            <Progress :model-value="profile.completion" class="mt-3" />
        </div>

        <!-- Stat cards -->
        <StaggerGroup
            as="div"
            class="grid grid-cols-2 gap-4 lg:grid-cols-4"
            :stagger-ms="70"
        >
            <component
                :is="card.href ? Link : 'div'"
                v-for="card in statCards"
                :key="card.label"
                :href="card.href ?? undefined"
                class="fl-hover-lift rounded-xl border border-white/10 bg-fl-graphite/40 p-5 transition-colors"
                :class="card.href ? 'hover:border-fl-gold/30' : ''"
            >
                <component :is="card.icon" class="size-5 text-fl-gold" />
                <p class="mt-3 text-2xl font-bold text-white">
                    {{ card.value }}
                </p>
                <p class="text-sm text-white/50">{{ card.label }}</p>
            </component>
        </StaggerGroup>

        <!-- Quick actions -->
        <div>
            <h2
                class="mb-3 text-sm font-semibold tracking-wide text-white/60 uppercase"
            >
                Acciones rápidas
            </h2>
            <StaggerGroup
                as="div"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                :stagger-ms="50"
            >
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link :href="createMedal()">
                        <Award class="size-4 text-fl-gold" />
                        Agregar medalla
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link :href="editProfile()">
                        <UserCircle class="size-4 text-fl-gold" />
                        Editar mi Legacy Profile
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link :href="eventsIndex()">
                        <Compass class="size-4 text-fl-gold" />
                        Explorar eventos
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link href="/tienda">
                        <ShoppingBag class="size-4 text-fl-gold" />
                        Ir a la tienda
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link href="/dashboard/my-gear">
                        <Package class="size-4 text-fl-gold" />
                        Mi equipo
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link href="/mis-pedidos">
                        <Receipt class="size-4 text-fl-gold" />
                        Mis pedidos
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                >
                    <Link href="/dashboard/my-plates">
                        <Boxes class="size-4 text-fl-gold" />
                        Mis Legacy Plates
                    </Link>
                </Button>

                <Dialog v-model:open="legacyCodeDialogOpen">
                    <DialogTrigger as-child>
                        <Button
                            variant="outline"
                            class="justify-start gap-2 border-white/10 bg-fl-graphite/40 text-white hover:border-fl-gold/30 hover:bg-fl-graphite/60 hover:text-white"
                        >
                            <QrCode class="size-4 text-fl-gold" />
                            Vincular Legacy Code
                        </Button>
                    </DialogTrigger>
                    <DialogContent
                        class="dark border-white/10 bg-fl-graphite text-white"
                    >
                        <DialogHeader>
                            <DialogTitle>Buscar Legacy Code</DialogTitle>
                            <DialogDescription class="text-white/50">
                                Ingresa el código impreso en tu placa para ver
                                su información.
                            </DialogDescription>
                        </DialogHeader>
                        <Input
                            v-model="legacyCodeInput"
                            placeholder="FL-XXXXXXX"
                            class="border-white/10 bg-fl-black text-white placeholder:text-white/30"
                            @keyup.enter="goToLegacyCode"
                        />
                        <DialogFooter>
                            <Button
                                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                @click="goToLegacyCode"
                            >
                                Buscar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </StaggerGroup>
        </div>

        <!-- Empty state helper when brand new -->
        <MascotEmptyState
            v-if="stats.medals === 0"
            title="Tu colección comienza con una meta."
            description="Registra tu primera medalla y empieza a construir tu Legacy."
        >
            <Button
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link :href="createMedal()">Agregar mi primera medalla</Link>
            </Button>
        </MascotEmptyState>
    </div>
</template>
