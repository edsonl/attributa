<script setup>
import { computed, ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Notify } from 'quasar'
import axios from 'axios'

const props = defineProps({
    campaign: {
        type: Object,
        default: null,
    },
    channels: {
        type: Array,
        required: true,
    },
    countries: {
        type: Array,
        required: true,
    },
    affiliate_platforms: {
        type: Array,
        required: true,
    },
    googleAdsAccounts: {
        type: Array,
        required: true,
    },
    defaults: {
        type: Object,
        default: () => ({}),
    },
})

const isEdit = computed(() => !!props.campaign)

/**
 * ===== FORM =====
 */
const countryOptions = ref(props.countries ?? [])
const filteredCountries = ref([...(props.countries ?? [])])
const countrySelectRef = ref(null)

watch(() => props.countries, (val = []) => {
    countryOptions.value = val
    filteredCountries.value = [...val]
})

const form = useForm({
    name: props.campaign?.name ?? '',
    pixel_code: props.campaign?.pixel_code ?? '',
    status: props.campaign?.status ?? true,
    channel_id: props.campaign?.channel_id ?? props.defaults?.channel_id ?? null,
    affiliate_platform_id: props.campaign?.affiliate_platform_id ?? props.defaults?.affiliate_platform_id ?? null,
    google_ads_account_id: props.campaign?.google_ads_account_id ?? null,
    countries: props.campaign?.countries
        ? props.campaign.countries.map(c => c.id)
        : [],
    commission_value: props.campaign?.commission_value ?? null,
})

const countryError = computed(() => {
    if (form.errors.countries) return form.errors.countries
    const entry = Object.entries(form.errors).find(([key]) => key.startsWith('countries.'))
    return entry ? entry[1] : null
})

function submit() {
    if (isEdit.value) {
        form.put(route('panel.campaigns.update', props.campaign.id))
    } else {
        form.post(route('panel.campaigns.store'))
    }
}

function filterCountries(val, update, abort) {
    if (!countryOptions.value.length) {
        return abort()
    }

    const needle = (val || '').trim().toLowerCase()

    update(() => {
        if (!needle) {
            filteredCountries.value = [...countryOptions.value]
            return
        }

        filteredCountries.value = countryOptions.value.filter(country => {
            const name = (country.name || '').toLowerCase()
            const iso2 = (country.iso2 || '').toLowerCase()
            const iso3 = (country.iso3 || '').toLowerCase()

            return (
                name.includes(needle) ||
                iso2.includes(needle) ||
                iso3.includes(needle)
            )
        })
    })
}

function closeCountriesPopup() {
    if (countrySelectRef.value?.hidePopup) {
        countrySelectRef.value.hidePopup()
    }
}

/**
 * ===== TRACKING SCRIPT =====
 */
const showTrackingDialog = ref(false)
const trackingLoading = ref(false)
const trackingScript = ref('')
const trackingTextarea = ref(null)

async function openTrackingDialog() {
    if (!props.campaign?.id) return

    showTrackingDialog.value = true
    trackingLoading.value = true
    trackingScript.value = ''

    try {
        const response = await axios.get(
            route('panel.campaigns.tracking_code', props.campaign.id)
        )
        trackingScript.value = String(response.data.script || '').trim()
    } catch {
        Notify.create({
            type: 'negative',
            message: 'Não foi possível carregar o código de acompanhamento',
            position: 'top-right',
        })
    } finally {
        trackingLoading.value = false
    }
}

/**
 * ===== COPY (100% FUNCIONAL) =====
 */
function copyTrackingScript() {
    if (!trackingTextarea.value) return

    trackingTextarea.value.focus()
    trackingTextarea.value.select()

    try {
        const ok = document.execCommand('copy')

        if (!ok) throw new Error()

        Notify.create({
            type: 'positive',
            message: 'Código copiado',
            timeout: 2000,
            position: 'top-right',
        })
    } catch {
        Notify.create({
            type: 'negative',
            message: 'Não foi possível copiar o código',
            timeout: 3000,
            position: 'top-right',
        })
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="tw-space-y-6">
        <!-- Nome -->
        <q-input
            v-model="form.name"
            label="Nome da campanha"
            outlined
            dense
            :error="Boolean(form.errors.name)"
            :error-message="form.errors.name"
        />

        <!-- Canal -->
        <q-select
            v-model="form.channel_id"
            :options="channels"
            option-label="label"
            option-value="id"
            emit-value
            map-options
            label="Canal"
            outlined
            dense
            :error="Boolean(form.errors.channel_id)"
            :error-message="form.errors.channel_id"
        />

       <q-select
            v-model="form.affiliate_platform_id"
            :options="affiliate_platforms"
            option-label="label"
            option-value="id"
            emit-value
            map-options
            label="Plataforma de Afiliado"
            outlined
            dense
            :error="Boolean(form.errors.affiliate_platform_id)"
            :error-message="form.errors.affiliate_platform_id"
        />

        <!-- Países -->
        <q-select
            ref="countrySelectRef"
            label="Regiões de segmentação (países)"
            v-model="form.countries"
            :options="filteredCountries"
            option-label="name"
            option-value="id"
            emit-value
            map-options
            multiple
            use-chips
            use-input
            @filter="filterCountries"
            @update:model-value="closeCountriesPopup"
            outlined
            dense
            :error="Boolean(countryError)"
            :error-message="countryError"
        />

        <q-select
            v-model="form.google_ads_account_id"
            :options="googleAdsAccounts"
            option-value="id"
            option-label="label"
            emit-value
            map-options
            clearable
            label="Conta de Anúncios"
            hint="Selecione a conta do Google Ads desta campanha"
            :error="Boolean(form.errors.google_ads_account_id)"
            :error-message="form.errors.google_ads_account_id"
        />

        <!-- Código da campanha -->
        <q-input
            v-model="form.pixel_code"
            label="Pixel de acompanhamento"
            outlined
            dense
            :error="Boolean(form.errors.pixel_code)"
            :error-message="form.errors.pixel_code"
        />

        <!-- Botão -->
        <q-btn
            v-if="campaign"
            flat
            icon="code"
            color="primary"
            label="Ver código de acompanhamento"
            @click="openTrackingDialog"
        />

        <!-- Status -->
        <q-toggle
            v-model="form.status"
            label="Campanha ativa"
        />

        <!-- Ações -->
        <div class="tw-flex tw-justify-end tw-gap-2">
            <q-btn flat label="Cancelar" @click="router.visit(route('panel.campaigns.index'))" />
            <q-btn color="primary" label="Salvar" type="submit" />
        </div>
    </form>

    <!-- ===== DIALOG ===== -->
    <q-dialog v-model="showTrackingDialog">
        <q-card style="
                min-width: 720px;
                max-width: 95vw;
                min-height: 450px;
                ">
            <q-card-section class="tw-flex tw-justify-between tw-items-center">
                <div class="tw-text-lg tw-font-semibold">
                    Código de acompanhamento
                </div>
                <q-btn flat dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section>
                <div v-if="trackingLoading" class="tw-text-center tw-py-6">
                    Carregando...
                </div>

                <!-- 🔑 TEXTAREA REAL (fonte da cópia) -->
                <textarea
                    ref="trackingTextarea"
                    class="tw-w-full tw-h-72 tw-font-mono tw-text-sm tw-p-3 tw-bg-gray-100 tw-rounded"
                    readonly
                >{{ trackingScript }}</textarea>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right">
                <q-btn
                    flat
                    icon="content_copy"
                    label="Copiar"
                    :disable="!trackingScript"
                    @click="copyTrackingScript"
                />
                <q-btn color="primary" label="Fechar" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
