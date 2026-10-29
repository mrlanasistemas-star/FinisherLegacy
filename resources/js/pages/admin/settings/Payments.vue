<script setup lang="ts">
/**
 * /admin/configuracion/pagos — active gateway and OpenPay credentials.
 * The private key is write-only: the server stores it encrypted and this
 * page only ever receives a masked hint ("••••1234"). Leaving the field
 * empty keeps the key already saved.
 */
import { Head, useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Copy,
    CreditCard,
    ExternalLink,
    KeyRound,
    PlugZap,
    ShieldCheck,
    Webhook,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { postJson } from '@/lib/commerce';
import { test as testRoute, update } from '@/routes/admin/settings/payments';

type Settings = {
    default_gateway: 'openpay' | 'stripe' | string;
    api_gateway: string;
    openpay: {
        merchant_id: string | null;
        public_key: string | null;
        private_key_hint: string | null;
        private_key_source: 'panel' | 'env' | null;
        country: string;
        production_mode: boolean;
        use_3d_secure: boolean;
        configured: boolean;
        missing: string[];
        verified_at: string | null;
        webhook_url: string;
        webhook_verification: { code: string; received_at: string } | null;
        dashboard_url: string;
    };
    stripe: {
        configured: boolean;
        webhook_configured: boolean;
        webhook_url: string;
    };
    updated_at: string | null;
};

const props = defineProps<{ settings: Settings }>();

const form = useForm({
    default_gateway: props.settings.default_gateway,
    openpay_merchant_id: props.settings.openpay.merchant_id ?? '',
    openpay_public_key: props.settings.openpay.public_key ?? '',
    openpay_private_key: '',
    openpay_country: props.settings.openpay.country || 'MX',
    openpay_production_mode: props.settings.openpay.production_mode,
    openpay_use_3d_secure: props.settings.openpay.use_3d_secure,
});

function save() {
    form.put(update().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('openpay_private_key'),
    });
}

const testing = ref(false);
const testResult = ref<{ ok: boolean; message: string } | null>(null);

async function testConnection() {
    testing.value = true;
    testResult.value = null;

    try {
        testResult.value = await postJson<{ ok: boolean; message: string }>(
            testRoute().url,
        );
    } catch {
        testResult.value = {
            ok: false,
            message: 'No pudimos ejecutar la prueba. Intenta de nuevo.',
        };
    } finally {
        testing.value = false;
    }
}

const copied = ref<string | null>(null);

async function copy(value: string) {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        // Clipboard blocked — the URL stays selectable on screen.
    }
}

const dateFormat = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const verifiedLabel = computed(() =>
    props.settings.openpay.verified_at
        ? dateFormat.format(new Date(props.settings.openpay.verified_at))
        : null,
);

const gateways = [
    {
        value: 'openpay',
        title: 'OpenPay',
        body: 'Formulario de tarjeta de OpenPay (BBVA). Visa, Mastercard, American Express y Carnet en MXN.',
    },
    {
        value: 'stripe',
        title: 'Stripe',
        body: 'Payment Element de Stripe. Requiere STRIPE_KEY, STRIPE_SECRET y STRIPE_WEBHOOK_SECRET en el servidor.',
    },
] as const;
</script>

<template>
    <Head title="Pagos" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <header
            class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="fl-eyebrow">Configuración</p>
                <h1
                    class="mt-1 flex items-center gap-2 font-serif text-3xl font-semibold text-foreground"
                >
                    <CreditCard class="size-6 text-fl-gold-ink" />
                    Pagos
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Elige con qué pasarela cobra la tienda en línea y captura
                    las llaves de OpenPay. Lo que guardes aquí tiene prioridad
                    sobre la configuración del servidor (.env).
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge
                    :variant="
                        settings.openpay.configured ? 'default' : 'secondary'
                    "
                    class="rounded-full px-3 py-1"
                    :class="
                        settings.openpay.configured
                            ? 'bg-emerald-600 text-white hover:bg-emerald-600'
                            : 'bg-amber-100 text-amber-900 hover:bg-amber-100'
                    "
                >
                    OpenPay:
                    {{
                        settings.openpay.configured
                            ? 'configurada'
                            : 'incompleta'
                    }}
                </Badge>
                <Badge
                    variant="secondary"
                    class="rounded-full px-3 py-1"
                    :class="
                        settings.openpay.production_mode
                            ? 'bg-foreground text-background hover:bg-foreground'
                            : ''
                    "
                >
                    {{
                        settings.openpay.production_mode
                            ? 'Producción'
                            : 'Sandbox (pruebas)'
                    }}
                </Badge>
            </div>
        </header>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]"
            @submit.prevent="save"
        >
            <div class="space-y-6">
                <!-- Active gateway -->
                <section class="fl-card p-6">
                    <h2 class="text-base font-semibold">Pasarela activa</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        La que usa el checkout de la tienda web. La app móvil
                        cobra con Stripe ({{ settings.api_gateway }}) porque usa
                        su hoja de pago nativa.
                    </p>
                    <div
                        class="mt-5 grid gap-3 md:grid-cols-2"
                        role="radiogroup"
                        aria-label="Pasarela activa"
                    >
                        <button
                            v-for="gateway in gateways"
                            :key="gateway.value"
                            type="button"
                            role="radio"
                            :aria-checked="
                                form.default_gateway === gateway.value
                            "
                            class="flex min-h-24 flex-col items-start gap-1 rounded-2xl border p-4 text-left transition"
                            :class="
                                form.default_gateway === gateway.value
                                    ? 'border-foreground bg-white ring-1 ring-foreground'
                                    : 'border-border bg-white hover:border-foreground/40'
                            "
                            @click="form.default_gateway = gateway.value"
                        >
                            <span
                                class="flex w-full items-center justify-between font-medium"
                            >
                                {{ gateway.title }}
                                <CircleCheck
                                    v-if="
                                        form.default_gateway === gateway.value
                                    "
                                    class="size-5 text-foreground"
                                />
                            </span>
                            <span class="text-sm text-muted-foreground">{{
                                gateway.body
                            }}</span>
                            <span
                                v-if="
                                    gateway.value === 'stripe' &&
                                    !settings.stripe.configured
                                "
                                class="mt-1 text-xs font-medium text-amber-800"
                                >Stripe no tiene llaves en el servidor.</span
                            >
                        </button>
                    </div>
                    <InputError
                        class="mt-2"
                        :message="form.errors.default_gateway"
                    />
                </section>

                <!-- OpenPay credentials -->
                <section class="fl-card p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2
                                class="flex items-center gap-2 text-base font-semibold"
                            >
                                <KeyRound class="size-4 text-fl-gold-ink" />
                                Credenciales de OpenPay
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                En el panel de OpenPay: Configuración → Llaves
                                de API. Usa las de sandbox mientras pruebas.
                            </p>
                        </div>
                        <a
                            :href="settings.openpay.dashboard_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-fl-gold-ink hover:underline"
                        >
                            Abrir panel <ExternalLink class="size-3.5" />
                        </a>
                    </div>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="op-merchant">Merchant ID</Label>
                            <Input
                                id="op-merchant"
                                v-model="form.openpay_merchant_id"
                                autocomplete="off"
                                spellcheck="false"
                                class="h-11 font-mono"
                                placeholder="mzdtln0bmtms6o3kck8f"
                            />
                            <InputError
                                :message="form.errors.openpay_merchant_id"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="op-country">País</Label>
                            <Input
                                id="op-country"
                                :model-value="'México (MX)'"
                                class="h-11"
                                disabled
                            />
                            <InputError
                                :message="form.errors.openpay_country"
                            />
                        </div>
                        <div class="grid gap-1.5 md:col-span-2">
                            <Label for="op-public">Llave pública</Label>
                            <Input
                                id="op-public"
                                v-model="form.openpay_public_key"
                                autocomplete="off"
                                spellcheck="false"
                                class="h-11 font-mono"
                                placeholder="pk_…"
                            />
                            <p class="text-xs text-muted-foreground">
                                Se usa en el navegador para tokenizar la
                                tarjeta; es pública por diseño.
                            </p>
                            <InputError
                                :message="form.errors.openpay_public_key"
                            />
                        </div>
                        <div class="grid gap-1.5 md:col-span-2">
                            <Label for="op-private">Llave privada</Label>
                            <Input
                                id="op-private"
                                v-model="form.openpay_private_key"
                                type="password"
                                autocomplete="new-password"
                                spellcheck="false"
                                class="h-11 font-mono"
                                :placeholder="
                                    settings.openpay.private_key_hint
                                        ? 'Déjala vacía para conservar la actual'
                                        : 'sk_…'
                                "
                            />
                            <p
                                v-if="settings.openpay.private_key_hint"
                                class="flex items-center gap-1.5 text-xs text-emerald-700"
                            >
                                <ShieldCheck class="size-3.5" />
                                {{ settings.openpay.private_key_hint }}
                                configurada
                                {{
                                    settings.openpay.private_key_source ===
                                    'env'
                                        ? '(desde el servidor)'
                                        : '(guardada cifrada)'
                                }}
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Se guarda cifrada y nunca se vuelve a mostrar.
                            </p>
                            <InputError
                                :message="form.errors.openpay_private_key"
                            />
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 border-t border-border pt-5">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl p-2 hover:bg-muted/40"
                        >
                            <Checkbox
                                v-model="form.openpay_production_mode"
                                class="mt-0.5"
                            />
                            <span>
                                <span class="block text-sm font-medium"
                                    >Modo producción</span
                                >
                                <span class="text-sm text-muted-foreground"
                                    >Cobros reales. Desactivado = sandbox, con
                                    tarjetas de prueba y sin cargos.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl p-2 hover:bg-muted/40"
                        >
                            <Checkbox
                                v-model="form.openpay_use_3d_secure"
                                class="mt-0.5"
                            />
                            <span>
                                <span class="block text-sm font-medium"
                                    >Usar 3D Secure en todos los cobros</span
                                >
                                <span class="text-sm text-muted-foreground"
                                    >El banco pide verificación al cliente.
                                    Desactivado, solo se usa cuando el
                                    antifraude de OpenPay rechaza un
                                    cobro.</span
                                >
                            </span>
                        </label>
                    </div>
                </section>

                <div
                    class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-3 border-t border-border bg-background/95 px-4 py-4 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-emerald-700"
                    >
                        Guardado.
                    </p>
                    <Button
                        type="submit"
                        class="h-11 rounded-full px-6"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" class="size-4" />
                        Guardar configuración
                    </Button>
                </div>
            </div>

            <aside class="space-y-6">
                <!-- Status + test -->
                <section class="fl-card p-6">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <PlugZap class="size-4 text-fl-gold-ink" />
                        Estado de OpenPay
                    </h2>
                    <div
                        v-if="settings.openpay.configured"
                        class="mt-4 flex items-start gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900"
                    >
                        <CircleCheck class="mt-0.5 size-4 shrink-0" />
                        <span>
                            Credenciales completas.
                            <template v-if="verifiedLabel">
                                Conexión verificada el {{ verifiedLabel }}.
                            </template>
                            <template v-else>
                                Aún sin probar la conexión.
                            </template>
                        </span>
                    </div>
                    <div
                        v-else
                        class="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" />
                        <span
                            >Incompleta — falta:
                            {{ settings.openpay.missing.join(', ') }}.</span
                        >
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        class="mt-4 h-11 w-full rounded-full"
                        :disabled="testing || !settings.openpay.configured"
                        @click="testConnection"
                    >
                        <Spinner v-if="testing" class="size-4" />
                        Probar conexión
                    </Button>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Consulta los datos del comercio en OpenPay con las
                        llaves guardadas. No genera ningún cargo. Guarda antes
                        de probar llaves nuevas.
                    </p>
                    <p
                        v-if="testResult"
                        class="mt-3 rounded-xl px-3 py-2 text-sm"
                        :class="
                            testResult.ok
                                ? 'bg-emerald-50 text-emerald-900'
                                : 'bg-red-50 text-red-800'
                        "
                        role="status"
                    >
                        {{ testResult.message }}
                    </p>
                </section>

                <!-- Webhook -->
                <section class="fl-card p-6">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <Webhook class="size-4 text-fl-gold-ink" />
                        Webhook de OpenPay
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Pégalo en el panel de OpenPay → Configuración →
                        Webhooks. Al guardarlo, OpenPay envía un código de
                        verificación que aparecerá aquí (recarga la página) para
                        que lo captures en su panel.
                    </p>
                    <div
                        class="mt-4 flex items-center gap-2 rounded-xl border border-border bg-muted/30 p-2 pl-3"
                    >
                        <code class="min-w-0 flex-1 truncate text-xs">{{
                            settings.openpay.webhook_url
                        }}</code>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-9 shrink-0 rounded-full"
                            @click="copy(settings.openpay.webhook_url)"
                        >
                            <Copy class="size-3.5" />
                            {{
                                copied === settings.openpay.webhook_url
                                    ? 'Copiado'
                                    : 'Copiar'
                            }}
                        </Button>
                    </div>
                    <div
                        v-if="settings.openpay.webhook_verification"
                        class="mt-4 rounded-xl border border-fl-gold/40 bg-fl-cream/60 p-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            Código de verificación recibido
                        </p>
                        <div
                            class="mt-1 flex items-center justify-between gap-2"
                        >
                            <code
                                class="legacy-numeric text-base font-semibold"
                                >{{
                                    settings.openpay.webhook_verification.code
                                }}</code
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-9 rounded-full"
                                @click="
                                    copy(
                                        settings.openpay.webhook_verification
                                            .code,
                                    )
                                "
                            >
                                <Copy class="size-3.5" />
                                {{
                                    copied ===
                                    settings.openpay.webhook_verification.code
                                        ? 'Copiado'
                                        : 'Copiar'
                                }}
                            </Button>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Aunque el webhook no esté activo, la tienda confirma
                        cada pago consultando el cargo directamente en OpenPay.
                    </p>
                </section>

                <section class="fl-card p-6 text-sm">
                    <h2 class="text-base font-semibold">Stripe</h2>
                    <ul class="mt-3 space-y-1.5 text-muted-foreground">
                        <li>
                            Llaves:
                            <span
                                :class="
                                    settings.stripe.configured
                                        ? 'text-emerald-700'
                                        : 'text-amber-800'
                                "
                                >{{
                                    settings.stripe.configured
                                        ? 'configuradas'
                                        : 'sin configurar'
                                }}</span
                            >
                        </li>
                        <li>
                            Webhook:
                            <span
                                :class="
                                    settings.stripe.webhook_configured
                                        ? 'text-emerald-700'
                                        : 'text-amber-800'
                                "
                                >{{
                                    settings.stripe.webhook_configured
                                        ? 'configurado'
                                        : 'sin configurar'
                                }}</span
                            >
                        </li>
                        <li class="text-xs break-all">
                            {{ settings.stripe.webhook_url }}
                        </li>
                    </ul>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Las llaves de Stripe se configuran en el servidor
                        (.env).
                    </p>
                </section>
            </aside>
        </form>
    </div>
</template>
