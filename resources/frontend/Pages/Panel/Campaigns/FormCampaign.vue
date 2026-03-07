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
        default: () => [],
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
    conversionGoals: {
        type: Array,
        required: true,
    },
    campaignStatuses: {
        type: Array,
        required: true,
    },
    defaults: {
        type: Object,
        default: () => ({}),
    },
    googleAdsLeadForm: {
        type: Object,
        default: () => ({
            webhook_url: null,
            key: null,
        }),
    },
})

const isEdit = computed(() => !!props.campaign)
const CAMPAIGN_NAME_MAX_LENGTH = 74
const STREAM_CODE_MAX_LENGTH = 30
const googleAdsFormKey = ref(props.googleAdsLeadForm?.key ?? '')
const regeneratingGoogleAdsFormKey = ref(false)
const syncingGoogleAdsLeadFormState = ref(false)

const googleAdsLeadFormWebhookUrl = computed(() => props.googleAdsLeadForm?.webhook_url ?? '')

/**
 * ===== FORM =====
 */
const countryOptions = ref(props.countries ?? [])
const showCountriesDialog = ref(false)
const countrySearch = ref('')
const countriesDraft = ref([])
const affiliateOptions = computed(() =>
    (props.affiliate_platforms ?? []).map(option => ({
        ...option,
        value: String(option.id),
        label: option.label ?? option.name,
    })),
)
const googleAdsAccountOptions = computed(() =>
    (props.googleAdsAccounts ?? []).map(option => ({
        ...option,
        value: String(option.id),
        label: option.label,
    })),
)
const conversionGoalOptions = computed(() =>
    (props.conversionGoals ?? []).map(option => ({
        ...option,
        value: String(option.id),
        label: option.active === false
            ? `${option.label ?? option.goal_code} (inativa)`
            : (option.label ?? option.goal_code),
    })),
)
const campaignStatusOptions = computed(() =>
    (props.campaignStatuses ?? []).map(option => ({
        ...option,
        value: String(option.id),
        label: option.label ?? option.name,
    })),
)

watch(() => props.countries, (val = []) => {
    countryOptions.value = val

    const validIds = new Set(val.map(country => String(country.id)))
    form.countries = (form.countries ?? []).filter(id => validIds.has(String(id)))
    countriesDraft.value = (countriesDraft.value ?? []).filter(id => validIds.has(String(id)))
})

function normalizeSelectValue(currentValue, options = []) {
    if (currentValue === null || currentValue === undefined || currentValue === '') {
        return currentValue
    }

    const match = options.find(option => String(option?.value) === String(currentValue))
    return match ? match.value : currentValue
}

function toStringOrNull(value) {
    if (value === null || value === undefined || value === '') {
        return null
    }

    return String(value)
}

const form = useForm({
    name: props.campaign?.name ?? '',
    product_url: props.campaign?.product_url ?? '',
    stream_code: props.campaign?.stream_code ?? '',
    form_lead_active: props.campaign?.form_lead_active ?? props.defaults?.form_lead_active ?? false,
    campaign_status_id: toStringOrNull(props.campaign?.campaign_status_id ?? props.defaults?.campaign_status_id ?? null),
    conversion_goal_id: toStringOrNull(props.campaign?.conversion_goal_id),
    channel_id: toStringOrNull(props.defaults?.channel_id ?? props.campaign?.channel_id ?? null),
    affiliate_platform_id: toStringOrNull(props.campaign?.affiliate_platform_id ?? props.defaults?.affiliate_platform_id ?? null),
    google_ads_account_id: toStringOrNull(props.campaign?.google_ads_account_id),
    countries: props.campaign?.countries
        ? props.campaign.countries.map(c => c.id)
        : [],
    commission_value: props.campaign?.commission_value ?? null,
})
const campaignNameLength = computed(() => String(form.name || '').length)

watch(affiliateOptions, (options) => {
    form.affiliate_platform_id = normalizeSelectValue(form.affiliate_platform_id, options)
}, { immediate: true })

watch(conversionGoalOptions, (options) => {
    form.conversion_goal_id = normalizeSelectValue(form.conversion_goal_id, options)
}, { immediate: true })

watch(campaignStatusOptions, (options) => {
    form.campaign_status_id = normalizeSelectValue(form.campaign_status_id, options)
}, { immediate: true })

watch(googleAdsAccountOptions, (options) => {
    form.google_ads_account_id = normalizeSelectValue(form.google_ads_account_id, options)
}, { immediate: true })

watch(() => form.form_lead_active, async (enabled, previousEnabled) => {
    if (previousEnabled === undefined || syncingGoogleAdsLeadFormState.value) {
        return
    }

    if (isEdit.value && props.campaign) {
        syncingGoogleAdsLeadFormState.value = true
        const campaignRouteKey = props.campaign.hashid ?? props.campaign.id

        try {
            const response = await axios.patch(
                route('panel.campaigns.google-ads-lead-form.state', campaignRouteKey),
                {
                    active: enabled,
                    stream_code: form.stream_code,
                },
            )

            form.form_lead_active = Boolean(response.data?.form_lead_active)
            form.stream_code = response.data?.stream_code ?? form.stream_code
            googleAdsFormKey.value = String(response.data?.google_ads_form_key ?? '').trim()
        } catch (error) {
            form.form_lead_active = previousEnabled
            const message = error?.response?.data?.message || 'Não foi possível atualizar o formulário de lead'
            Notify.create({
                type: 'negative',
                message,
                timeout: 3500,
                position: 'top-right',
            })
            syncingGoogleAdsLeadFormState.value = false
            return
        }

        syncingGoogleAdsLeadFormState.value = false
    }

    const wasEnabled = Boolean(previousEnabled)
    const isEnabled = Boolean(enabled)
    const hasStreamCode = String(form.stream_code ?? '').trim() !== ''

    if (!isEnabled || wasEnabled || hasStreamCode) {
        return
    }

    Notify.create({
        type: 'warning',
        message: 'O formulário de lead só funciona com o stream code da campanha informado.',
        timeout: 3500,
        position: 'top-right',
    })
})

const countryError = computed(() => {
    if (form.errors.countries) return form.errors.countries
    const entry = Object.entries(form.errors).find(([key]) => key.startsWith('countries.'))
    return entry ? entry[1] : null
})

const countryById = computed(() => {
    const map = new Map()

    for (const country of countryOptions.value) {
        map.set(String(country.id), country)
    }

    return map
})

const selectedCountries = computed(() => {
    return (form.countries ?? [])
        .map(id => countryById.value.get(String(id)))
        .filter(Boolean)
})

const filteredCountries = computed(() => {
    const needle = countrySearch.value.trim().toLowerCase()
    if (!needle) {
        return countryOptions.value
    }

    return countryOptions.value.filter(country => {
        const name = (country.name ?? '').toLowerCase()
        const iso2 = (country.iso2 ?? '').toLowerCase()
        const iso3 = (country.iso3 ?? '').toLowerCase()

        return name.includes(needle) || iso2.includes(needle) || iso3.includes(needle)
    })
})

const draftSelectedCountries = computed(() => {
    return (countriesDraft.value ?? [])
        .map(id => countryById.value.get(String(id)))
        .filter(Boolean)
})

const draftSelectionSummary = computed(() => {
    const selected = draftSelectedCountries.value
    if (!selected.length) {
        return 'Nenhum país selecionado'
    }

    const maxVisibleNames = 5
    const maxLabelLength = 70
    const visibleNames = selected.slice(0, maxVisibleNames).map(country => country.name).join(', ')
    const hiddenCount = selected.length - maxVisibleNames
    const croppedVisibleNames = visibleNames.length > maxLabelLength
        ? `${visibleNames.slice(0, maxLabelLength).trim()} (...)`
        : visibleNames

    return hiddenCount > 0
        ? `${croppedVisibleNames} (+${hiddenCount})`
        : croppedVisibleNames
})

function submit() {
    const campaignRouteKey = props.campaign?.hashid ?? props.campaign?.id

    if (isEdit.value) {
        form.put(route('panel.campaigns.update', campaignRouteKey))
    } else {
        form.post(route('panel.campaigns.store'))
    }
}

function removeCountry(countryId) {
    form.countries = (form.countries ?? []).filter(id => String(id) !== String(countryId))
}

function openCountriesDialog() {
    countriesDraft.value = [...(form.countries ?? [])]
    countrySearch.value = ''
    showCountriesDialog.value = true
}

function closeCountriesDialog() {
    showCountriesDialog.value = false
}

function applyCountriesSelection() {
    form.countries = [...(countriesDraft.value ?? [])]
    closeCountriesDialog()
}

function selectAllFilteredCountries() {
    const selected = new Set((countriesDraft.value ?? []).map(id => String(id)))

    for (const country of filteredCountries.value) {
        selected.add(String(country.id))
    }

    countriesDraft.value = countryOptions.value
        .filter(country => selected.has(String(country.id)))
        .map(country => country.id)
}

function clearDraftCountries() {
    countriesDraft.value = []
}

function toggleDraftCountry(countryId) {
    const current = new Set((countriesDraft.value ?? []).map(id => String(id)))
    const targetId = String(countryId)

    if (current.has(targetId)) {
        current.delete(targetId)
    } else {
        current.add(targetId)
    }

    countriesDraft.value = countryOptions.value
        .filter(country => current.has(String(country.id)))
        .map(country => country.id)
}

/**
 * ===== TRACKING SCRIPT =====
 */
const showTrackingDialog = ref(false)
const trackingLoading = ref(false)
const trackingScript = ref('')
const trackingTextarea = ref(null)

async function openTrackingDialog() {
    const campaignRouteKey = props.campaign?.hashid ?? props.campaign?.id
    if (!campaignRouteKey) return

    showTrackingDialog.value = true
    trackingLoading.value = true
    trackingScript.value = ''

    try {
        const response = await axios.get(
            route('panel.campaigns.tracking_code', campaignRouteKey)
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

async function copyText(text, label) {
    const value = String(text ?? '').trim()
    if (!value) return

    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(value)
        } else {
            const textarea = document.createElement('textarea')
            textarea.value = value
            textarea.setAttribute('readonly', '')
            textarea.style.position = 'absolute'
            textarea.style.left = '-9999px'
            document.body.appendChild(textarea)
            textarea.select()
            document.execCommand('copy')
            document.body.removeChild(textarea)
        }

        Notify.create({
            type: 'positive',
            message: `${label} copiada`,
            timeout: 2000,
            position: 'top-right',
        })
    } catch {
        Notify.create({
            type: 'negative',
            message: `Não foi possível copiar ${label.toLowerCase()}`,
            timeout: 3000,
            position: 'top-right',
        })
    }
}

async function regenerateGoogleAdsFormKey() {
    const campaignRouteKey = props.campaign?.hashid ?? props.campaign?.id
    if (!campaignRouteKey || regeneratingGoogleAdsFormKey.value) return

    regeneratingGoogleAdsFormKey.value = true

    try {
        const response = await axios.patch(
            route('panel.campaigns.google-ads-lead-form.regenerate-key', campaignRouteKey),
        )

        googleAdsFormKey.value = String(response.data?.google_ads_form_key ?? '').trim()
        Notify.create({
            type: 'positive',
            message: 'Chave regenerada com sucesso',
            timeout: 2000,
            position: 'top-right',
        })
    } catch (error) {
        const message = error?.response?.data?.message || 'Não foi possível regenerar a chave'
        Notify.create({
            type: 'negative',
            message,
            timeout: 3000,
            position: 'top-right',
        })
    } finally {
        regeneratingGoogleAdsFormKey.value = false
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="tw-space-y-6">
        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-4">
            <!-- Nome -->
            <q-input
                v-model="form.name"
                label="Nome da campanha"
                :maxlength="CAMPAIGN_NAME_MAX_LENGTH"
                :hint="`${campaignNameLength}/${CAMPAIGN_NAME_MAX_LENGTH} caracteres`"
                counter
                outlined
                dense
                :error="Boolean(form.errors.name)"
                :error-message="form.errors.name"
            />

            <q-input
                v-model="form.product_url"
                label="URL do produto autorizada"
                hint="Exemplo: https://biovitania.online"
                outlined
                dense
                :error="Boolean(form.errors.product_url)"
                :error-message="form.errors.product_url"
            />

            <q-select
                v-model="form.affiliate_platform_id"
                :options="affiliateOptions"
                option-label="label"
                option-value="value"
                emit-value
                map-options
                label="Plataforma de Afiliado"
                outlined
                dense
                :error="Boolean(form.errors.affiliate_platform_id)"
                :error-message="form.errors.affiliate_platform_id"
            />

            <q-input
                v-model="form.stream_code"
                label="Código stream"
                :maxlength="STREAM_CODE_MAX_LENGTH"
                hint="Opcional. Sem espaços. Exemplo: OFERTA123"
                outlined
                dense
                :error="Boolean(form.errors.stream_code)"
                :error-message="form.errors.stream_code"
            />

            <div class="campaign-status-block">
                <div class="campaign-status-label">
                    Status da campanha
                </div>
                <q-option-group
                    v-model="form.campaign_status_id"
                    :options="campaignStatusOptions"
                    type="radio"
                    inline
                    dense
                />
                <div
                    v-if="form.errors.campaign_status_id"
                    class="campaign-status-error"
                >
                    {{ form.errors.campaign_status_id }}
                </div>
            </div>

            <q-select
                v-model="form.conversion_goal_id"
                :options="conversionGoalOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                clearable
                label="Meta de conversao"
                hint="Selecione a meta/codigo de conversao vinculada a campanha"
                outlined
                dense
                :error="Boolean(form.errors.conversion_goal_id)"
                :error-message="form.errors.conversion_goal_id"
            />

            <!-- Países -->
            <q-field
                class="lg:tw-col-span-2"
                label="Regiões de segmentação (países)"
                outlined
                dense
                stack-label
                :error="Boolean(countryError)"
                :error-message="countryError"
            >
                <template #control>
                    <div class="tw-flex tw-items-center tw-gap-2 tw-py-1 tw-min-h-8 tw-w-full">
                        <q-btn
                            flat
                            dense
                            icon="travel_explore"
                            label="Selecionar"
                            @click="openCountriesDialog"
                        />
                        <div class="tw-flex tw-items-center tw-flex-wrap tw-gap-2">
                            <q-chip
                                v-for="country in selectedCountries"
                                :key="country.id"
                                removable
                                dense
                                square
                                @remove="removeCountry(country.id)"
                            >
                                {{ country.name }}
                            </q-chip>
                            <span v-if="!selectedCountries.length" class="tw-text-gray-500">
                                Nenhum país selecionado
                            </span>
                        </div>
                    </div>
                </template>
            </q-field>

        </div>

        <!-- Botão -->
        <q-btn
            v-if="campaign"
            flat
            icon="code"
            color="primary"
            label="Ver código de acompanhamento"
            @click="openTrackingDialog"
        />

        <q-card flat bordered>
            <q-card-section class="tw-space-y-3">
                <div class="tw-flex tw-items-center">
                    <q-toggle
                        v-model="form.form_lead_active"
                        label="Ativar formulário de lead (Google Ads)"
                        :true-value="true"
                        :false-value="false"
                    />
                    <span v-if="form.errors.form_lead_active" class="campaign-status-error">
                        {{ form.errors.form_lead_active }}
                    </span>
                </div>

                <q-card
                    v-if="form.form_lead_active"
                    flat
                    bordered
                >
                    <q-card-section class="tw-space-y-3">
                        <div class="tw-text-sm tw-font-medium">
                            Integração com webhook do formulário de lead (Google Ads)
                        </div>

                        <q-banner
                            v-if="!isEdit"
                            dense
                            class="tw-bg-amber-50 tw-text-amber-900 tw-rounded-md"
                        >
                            Salve a campanha para visualizar URL e chave da integração.
                        </q-banner>

                        <template v-else>
                            <q-input
                                :model-value="googleAdsLeadFormWebhookUrl"
                                label="URL do webhook"
                                outlined
                                dense
                                readonly
                            >
                                <template #append>
                                    <q-btn
                                        flat
                                        dense
                                        icon="content_copy"
                                        @click="copyText(googleAdsLeadFormWebhookUrl, 'URL do webhook')"
                                    />
                                </template>
                            </q-input>

                            <q-input
                                :model-value="googleAdsFormKey"
                                label="Senha"
                                outlined
                                dense
                                readonly
                            >
                                <template #append>
                                    <q-btn
                                        flat
                                        dense
                                        icon="key"
                                        :loading="regeneratingGoogleAdsFormKey"
                                        @click="regenerateGoogleAdsFormKey"
                                    />
                                    <q-btn
                                        flat
                                        dense
                                        icon="content_copy"
                                        @click="copyText(googleAdsFormKey, 'Senha')"
                                    />
                                </template>
                            </q-input>

                            <div class="tw-rounded-md tw-border tw-border-amber-200 tw-bg-amber-50 tw-p-3 tw-text-xs tw-text-amber-900 tw-space-y-1">
                                <div>
                                    Se você gerar nova senha, atualize a credencial no Google Ads imediatamente. Enquanto a senha antiga estiver configurada lá, o Google não conseguirá autenticar e o recebimento falhará.
                                </div>
                                <div>
                                    O formulário de lead só funciona com o código stream desta campanha informado.
                                </div>
                            </div>
                        </template>
                    </q-card-section>
                </q-card>
            </q-card-section>
        </q-card>

        <!-- Ações -->
        <div class="tw-flex tw-justify-end tw-gap-2">
            <q-btn flat label="Cancelar" @click="router.visit(route('panel.campaigns.index'))" />
            <q-btn color="primary" label="Salvar" type="submit" />
        </div>
    </form>

    <!-- ===== DIALOG ===== -->
    <q-dialog v-model="showTrackingDialog">
        <q-card style="
                min-width: 750px;
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

    <q-dialog v-model="showCountriesDialog">
        <q-card style="min-width: 720px; max-width: 95vw; width: 900px; max-height: 90vh;">
            <q-card-section class="tw-flex tw-items-center tw-justify-between">
                <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                    <div class="tw-text-lg tw-font-semibold">
                        Selecionar países
                    </div>
                    <span class="countries-info-badge">
                        Se a campanha for global, não é necessário selecionar países.
                    </span>
                </div>
                <q-btn flat dense icon="close" @click="closeCountriesDialog" />
            </q-card-section>

            <q-separator />

            <q-card-section class="tw-space-y-3">
                <q-input
                    v-model="countrySearch"
                    dense
                    outlined
                    clearable
                    autofocus
                    label="Buscar país por nome ou código"
                />

                <div class="tw-flex tw-items-center tw-justify-between tw-gap-2">
                    <div class="tw-text-sm tw-text-gray-600">
                        {{ draftSelectionSummary }}
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <q-btn flat dense label="Selecionar filtrados" @click="selectAllFilteredCountries" />
                        <q-btn flat dense label="Limpar" @click="clearDraftCountries" />
                    </div>
                </div>

                <div class="tw-border tw-rounded tw-max-h-96 tw-overflow-y-auto tw-p-2">
                    <q-list separator>
                        <q-item
                            v-for="country in filteredCountries"
                            :key="country.id"
                            clickable
                            dense
                            @click="toggleDraftCountry(country.id)"
                        >
                            <q-item-section avatar>
                                <q-checkbox v-model="countriesDraft" :val="country.id" @click.stop />
                            </q-item-section>
                            <q-item-section>
                                <q-item-label>{{ country.name }}</q-item-label>
                                <q-item-label caption>{{ country.iso2 }} · {{ country.iso3 }}</q-item-label>
                            </q-item-section>
                        </q-item>
                    </q-list>
                    <div v-if="!filteredCountries.length" class="tw-text-sm tw-text-gray-500 tw-p-3">
                        Nenhum país encontrado para esta busca.
                    </div>
                </div>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right">
                <q-btn flat label="Cancelar" @click="closeCountriesDialog" />
                <q-btn color="primary" label="Aplicar" @click="applyCountriesSelection" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
<style lang="css" scoped>
  .bg-white-campanha {
    background-color: #fff;
  }

  .campaign-status-block {
    padding-top: 2px;
  }

  .campaign-status-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
  }

  .campaign-status-error {
    margin-top: 4px;
    font-size: 12px;
    color: #c10015;
  }

  .countries-info-badge {
    display: inline-flex;
    align-items: center;
    background-color: #eff6ff;
    color: #1e3a8a;
    border: 1px solid #bfdbfe;
    border-radius: 9999px;
    padding: 4px 10px;
    font-size: 12px;
    line-height: 1.2;
    font-weight: 500;
  }
</style>
