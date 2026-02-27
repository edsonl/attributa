<script setup>
import { computed } from 'vue'
import { useQuasar } from 'quasar'

const FALLBACK_IP_CATEGORY_DETAIL = {
    name: 'Não determinado',
    color_hex: '#FCE7F3',
    description: 'Categoria ainda não determinada.',
}
const PREVIEW_CHAR_LIMIT = 20

const GEO_FIELDS = [
    { label: 'Código', key: 'country_code' },
    { label: 'Região', key: 'region_name' },
    { label: 'Cidade', key: 'city' },
    { label: 'Timezone', key: 'timezone' },
]

const NETWORK_FLAGS = [
    { key: 'is_proxy', label: 'Proxy' },
    { key: 'is_vpn', label: 'VPN' },
    { key: 'is_tor', label: 'Tor' },
    { key: 'is_datacenter', label: 'Datacenter' },
    { key: 'is_bot', label: 'Bot' },
]

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    payload: {
        type: Object,
        default: () => null,
    },
    assetBaseUrl: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['update:modelValue'])
const $q = useQuasar()

const detailDialog = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})

const detailPageview = computed(() => props.payload?.pageview ?? {})
const detailVisitor = computed(() => props.payload?.visitor ?? null)
const detailUrl = computed(() => props.payload?.url ?? { full: null, origin: null, path: null, query_params: {} })
const detailGeo = computed(() => props.payload?.geo ?? {})
const detailNetwork = computed(() => props.payload?.network ?? {})
const detailCampaignName = computed(() => detailPageview.value?.campaign?.name ?? '-')

const detailNetworkFlags = computed(() => detailNetwork.value?.flags ?? {})
const detailNetworkCategory = computed(() => detailNetwork.value?.ip_category ?? null)
const detailNetworkCategoryColor = computed(() => detailNetworkCategory.value?.color_hex ?? '#475569')
const detailNetworkCategoryName = computed(() => detailNetworkCategory.value?.name ?? '-')
const detailNetworkCategoryDescription = computed(() => detailNetworkCategory.value?.description ?? 'Sem descrição.')

const detailTrafficCategory = computed(() => detailPageview.value?.traffic_source_category ?? null)
const detailTrafficCategoryName = computed(() => detailTrafficCategory.value?.name ?? 'Não classificado')
const detailTrafficReasonLabel = computed(() => formatTrafficReason(detailPageview.value?.traffic_source_reason))

const detailCountryFlag = computed(() => {
    const code = detailGeo.value?.country_code
    if (!code) return null
    return `${props.assetBaseUrl}/assets/country-flags/${String(code).toLowerCase()}.svg`
})

const detailCleanUrl = computed(() => stripQueryString(detailUrl.value?.full || ''))
const detailLandingUrl = computed(() => detailPageview.value?.landing_url ?? null)
const detailLandingCleanUrl = computed(() => stripQueryString(detailLandingUrl.value || ''))
const detailReferrer = computed(() => detailPageview.value?.referrer ?? null)
const detailReferrerHost = computed(() => extractHost(detailReferrer.value))
const detailResolvedGclid = computed(() => resolveGclid())
const detailComposedCode = computed(() => props.payload?.composed_code ?? null)
const detailEvents = computed(() => (Array.isArray(props.payload?.events) ? props.payload.events : []))
const detailFlowStepsPayload = computed(() => (Array.isArray(props.payload?.flow_steps) ? props.payload.flow_steps : []))
const detailLatLong = computed(() => {
    const lat = detailGeo.value?.latitude
    const lng = detailGeo.value?.longitude
    if (lat === null || lat === undefined || lng === null || lng === undefined) return '-'
    return `${lat}, ${lng}`
})

const detailUrlParams = computed(() => {
    const params = detailUrl.value?.query_params ?? {}
    return Object.entries(params)
})
const hasUrlParams = computed(() => detailUrlParams.value.length > 0)

const detailFlowEventSummary = computed(() => {
    const summary = {
        page_engaged: { first: null, count: 0, reasons: {} },
        form_submit: { first: null, count: 0, reasons: {} },
        link_click: { first: null, count: 0, reasons: {} },
    }

    detailEvents.value.forEach((event) => {
        const type = String(event?.event_type || '').toLowerCase()
        if (!Object.prototype.hasOwnProperty.call(summary, type)) {
            return
        }

        if (!summary[type].first) {
            summary[type].first = event
        }
        summary[type].count += 1

        const reason = resolveEventReasonLabel(type, event)
        if (!reason) {
            return
        }

        const bucket = summary[type].reasons[reason] || { count: 0, lastEvent: null }
        bucket.count += 1
        bucket.lastEvent = pickLatestEvent(bucket.lastEvent, event)
        summary[type].reasons[reason] = bucket
    })

    return summary
})

const visitFlowSteps = computed(() => {
    if (detailFlowStepsPayload.value.length > 0) {
        return detailFlowStepsPayload.value
    }

    const engaged = detailFlowEventSummary.value.page_engaged
    const formSubmit = detailFlowEventSummary.value.form_submit
    const linkClick = detailFlowEventSummary.value.link_click
    const interactionUsesForm = Boolean(formSubmit.first)
    const interactionBase = interactionUsesForm ? formSubmit : linkClick

    const pageViewCaption = detailPageview.value?.created_at_formatted || 'Não identificado'

    const engagedReason = engaged.first ? formatEngagementReason(engaged.first) : ''
    const engagedCaption = engaged.first
        ? `${engaged.first.created_at_formatted || '-'}${engaged.count > 1 ? ` (+${engaged.count - 1})` : ''}${engagedReason ? ` • ${engagedReason}` : ''}`
        : 'Sem engajamento detectado'

    const interactionBaseCaption = interactionBase.first
        ? `${interactionBase.first.created_at_formatted || '-'}${interactionBase.count > 1 ? ` (+${interactionBase.count - 1})` : ''}`
        : (interactionUsesForm ? 'Sem envio de formulário' : 'Sem clique em link')
    const formDataCaption = interactionUsesForm && interactionBase.first
        ? (interactionBase.first.form_has_user_data ? 'Dados informados' : 'Sem dados informados')
        : ''
    const interactionCaption = formDataCaption
        ? `${interactionBaseCaption} • ${formDataCaption}`
        : interactionBaseCaption

    const conversionDone = Boolean(detailPageview.value?.conversion)
    const conversionCaption = conversionDone
        ? `${detailPageview.value?.created_at_formatted || '-'} • Convertido`
        : 'Não convertido'

    return [
        {
            name: 1,
            title: 'Page View',
            caption: pageViewCaption,
            icon: 'visibility',
            done: true,
            color: 'primary',
            tooltip: `Registro inicial da visita.\nCapturado em ${pageViewCaption}.`,
        },
        {
            name: 2,
            title: 'Page Engaged',
            caption: engagedCaption,
            icon: 'insights',
            done: Boolean(engaged.first),
            color: Boolean(engaged.first) ? 'primary' : 'grey-6',
            tooltip: Boolean(engaged.first)
                ? buildEngagedTooltip(engaged)
                : 'Nenhum evento de engajamento foi detectado nesta visita.',
        },
        {
            name: 3,
            title: interactionUsesForm ? 'Form Submit' : 'Link Click',
            caption: interactionCaption,
            icon: interactionUsesForm ? 'fact_check' : 'ads_click',
            done: Boolean(interactionBase.first),
            color: Boolean(interactionBase.first) ? 'primary' : 'grey-6',
            tooltip: Boolean(interactionBase.first)
                ? buildInteractionTooltip(interactionBase, interactionUsesForm)
                : (interactionUsesForm
                    ? 'Nenhum envio de formulário foi detectado nesta visita.'
                    : 'Nenhum clique em link rastreado foi detectado nesta visita.'),
        },
        {
            name: 4,
            title: 'Conversão',
            caption: conversionCaption,
            icon: conversionDone ? 'task_alt' : 'radio_button_unchecked',
            done: conversionDone,
            color: conversionDone ? 'positive' : 'grey-6',
            tooltip: conversionDone
                ? `Visita marcada como conversão.\nData/hora de referência: ${detailPageview.value?.created_at_formatted || '-'}.`
                : 'Até o momento, esta visita não foi marcada como conversão.',
        },
    ]
})

const visitFlowActiveStep = computed(() => {
    const doneSteps = visitFlowSteps.value.filter(step => step.done)
    if (doneSteps.length === 0) {
        return 1
    }
    return doneSteps[doneSteps.length - 1].name
})

const detailDeviceSummary = computed(() => {
    const relationName = detailPageview.value?.device_category?.name
    const type = detailPageview.value?.device_type
    const normalizedRelation = normalizeEnrichedValue(relationName)
    if (normalizedRelation !== '-') return normalizedRelation

    const normalizedType = normalizeEnrichedValue(type)
    return normalizedType
})

const detailBrowserSummary = computed(() => {
    const relationName = detailPageview.value?.browser?.name
    const name = detailPageview.value?.browser_name
    const version = detailPageview.value?.browser_version

    const normalizedName = normalizeEnrichedValue(name)
    const normalizedVersion = normalizeEnrichedValue(version)
    const normalizedRelation = normalizeEnrichedValue(relationName)

    if (normalizedName !== '-' && normalizedVersion !== '-') return `${normalizedName} ${normalizedVersion}`
    if (normalizedName !== '-') return normalizedName
    if (normalizedRelation !== '-') return normalizedRelation
    return '-'
})

const detailOsSummary = computed(() => {
    const name = detailPageview.value?.os_name
    const version = detailPageview.value?.os_version

    const normalizedName = normalizeEnrichedValue(name)
    const normalizedVersion = normalizeEnrichedValue(version)

    if (normalizedName !== '-' && normalizedVersion !== '-') return `${normalizedName} ${normalizedVersion}`
    if (normalizedName !== '-') return normalizedName
    return '-'
})

const detailVisitorStatusLabel = computed(() => {
    return String(detailVisitor.value?.status || '').toLowerCase() === 'recorrente'
        ? 'Recorrente'
        : 'Novo'
})
const detailVisitorHits = computed(() => Math.max(Number(detailVisitor.value?.hits ?? 1), 1))
const detailVisitorHitsLabel = computed(() => (detailVisitorHits.value === 1 ? 'visita' : 'visitas'))

function stripQueryString(value) {
    if (!value) return '-'

    try {
        const origin = typeof window !== 'undefined' ? window.location.origin : 'https://placeholder.local'
        const parsedUrl = new URL(value, origin)
        return `${parsedUrl.origin}${parsedUrl.pathname}`
    } catch (error) {
        return value.split('?')[0] ?? value
    }
}

function extractHost(value) {
    if (!value) return null

    try {
        const origin = typeof window !== 'undefined' ? window.location.origin : 'https://placeholder.local'
        const parsedUrl = new URL(value, origin)
        return parsedUrl.host || null
    } catch (error) {
        return null
    }
}

function hasText(value) {
    return value !== null && value !== undefined && String(value).trim() !== ''
}

function normalizeEnrichedValue(value) {
    const raw = String(value ?? '').trim()
    if (!raw) return '-'

    const normalized = raw.toLowerCase()
    if (normalized === '-' || normalized === 'unknown' || normalized === 'desconhecido' || normalized === 'unk') {
        return '-'
    }

    return raw
}

function formatFlag(value) {
    if (value === null || value === undefined) return '-'
    return value ? 'Sim' : 'Não'
}

function formatValue(value) {
    if (value === null || value === undefined) return '-'

    if (Array.isArray(value) || typeof value === 'object') {
        try {
            return JSON.stringify(value)
        } catch (error) {
            return String(value)
        }
    }

    return String(value)
}

function previewValue(value) {
    const text = formatValue(value)
    if (!hasText(text) || text === '-') return '-'
    if (text.length <= PREVIEW_CHAR_LIMIT) return text
    return text.slice(0, PREVIEW_CHAR_LIMIT)
}

function resolveGclid() {
    const fromPageview = detailPageview.value?.gclid
    if (hasText(fromPageview)) return String(fromPageview)

    const queryParams = detailUrl.value?.query_params ?? {}
    const match = Object.entries(queryParams).find(([key]) => String(key).toLowerCase() === 'gclid')
    if (!match) return ''

    return formatValue(match[1])
}

function formatTrafficReason(value) {
    const raw = String(value || '').trim()
    if (!raw) return '-'

    if (raw.startsWith('click_id:')) {
        const key = raw.slice('click_id:'.length)
        return `Clique identificado por ${key}`
    }

    if (raw.startsWith('utm_medium:')) {
        const medium = raw.slice('utm_medium:'.length)
        return `Classificado por utm_medium=${medium}`
    }

    if (raw.startsWith('referrer_search:')) {
        const host = raw.slice('referrer_search:'.length)
        return `Referência de buscador (${host})`
    }

    if (raw.startsWith('referrer_social:')) {
        const host = raw.slice('referrer_social:'.length)
        return `Referência de rede social (${host})`
    }

    if (raw.startsWith('referrer:')) {
        const host = raw.slice('referrer:'.length)
        return `Referência externa (${host})`
    }

    if (raw === 'internal_referrer') {
        return 'Navegação interna no mesmo domínio'
    }

    if (raw.startsWith('utm_source_only:')) {
        const source = raw.slice('utm_source_only:'.length)
        return `Somente utm_source informado (${source})`
    }

    if (raw === 'no_referrer_no_utm_no_click_id') {
        return 'Sem referrer, UTM ou click-id (direto)'
    }

    return raw
}

function formatEngagementReason(event) {
    const rawElementId = String(event?.element_id || '').trim().toLowerCase()
    let reason = ''

    if (rawElementId.startsWith('engagement_reason:')) {
        reason = rawElementId.slice('engagement_reason:'.length)
    } else {
        const rawName = String(event?.element_name || '').trim()
        const match = /^page engaged\s*\((.+)\)$/i.exec(rawName)
        reason = match?.[1] ? String(match[1]).trim().toLowerCase() : ''
    }

    const labels = {
        scroll_30: 'Scroll 30%',
        time_10s: 'Tempo 10s',
        link_click: 'Clique em link',
        form_submit: 'Envio de formulário',
        interactions: '2+ interações',
    }

    return labels[reason] || (reason ? `Engajamento: ${reason}` : '')
}

function resolveEventReasonLabel(type, event) {
    if (type === 'page_engaged') {
        return formatEngagementReason(event)
    }
    if (type === 'form_submit') {
        return event?.form_has_user_data ? 'Dados informados' : 'Sem dados informados'
    }
    if (type === 'link_click') {
        return 'Clique em link'
    }

    return ''
}

function resolveEventMs(event) {
    const raw = String(event?.created_at || '').trim()
    if (!raw) return 0

    const ts = Date.parse(raw)
    return Number.isFinite(ts) ? ts : 0
}

function pickLatestEvent(currentEvent, candidateEvent) {
    if (!currentEvent) return candidateEvent
    return resolveEventMs(candidateEvent) >= resolveEventMs(currentEvent) ? candidateEvent : currentEvent
}

function buildDetailedReasonLines(reasonBuckets, labelsWithoutCount = []) {
    const entries = Object.entries(reasonBuckets || {})
    if (entries.length === 0) return []

    entries.sort((a, b) => resolveEventMs(b[1]?.lastEvent) - resolveEventMs(a[1]?.lastEvent))

    return entries.map(([label, meta]) => {
        const count = Math.max(Number(meta?.count || 0), 1)
        const lastAt = String(meta?.lastEvent?.created_at_formatted || '-').trim() || '-'

        if (labelsWithoutCount.includes(label)) {
            return `${label} - ${lastAt}`
        }

        return `(${count}) ${label} - ${lastAt}`
    })
}

function buildEngagedTooltip(engaged) {
    const lines = buildDetailedReasonLines(engaged?.reasons, ['Scroll 30%'])
    if (lines.length === 0) {
        return 'Usuário atingiu critério de engajamento nesta visita.'
    }
    return `Usuário atingiu critério de engajamento nesta visita.\nDetalhado:\n${lines.join('\n')}`
}

function buildInteractionTooltip(interaction, useFormStep) {
    const base = useFormStep
        ? 'Visitante enviou formulário na página.'
        : 'Visitante clicou em link rastreado.'
    const lines = buildDetailedReasonLines(interaction?.reasons)

    if (lines.length === 0) return base
    return `${base}\nDetalhado:\n${lines.join('\n')}`
}

async function copyValue(value) {
    const text = formatValue(value)

    try {
        await navigator.clipboard.writeText(text)
        $q.notify({ type: 'positive', message: 'Valor copiado.' })
    } catch (error) {
        $q.notify({ type: 'negative', message: 'Nao foi possivel copiar.' })
    }
}
</script>

<template>
    <q-dialog v-model="detailDialog" transition-show="scale" transition-hide="scale">
        <q-card class="pageview-detail-card">
            <q-card-section class="tw-flex tw-justify-end detail-close-section">
                <q-btn icon="close" flat round dense @click="detailDialog = false" />
            </q-card-section>

            <q-linear-progress v-if="loading" indeterminate color="primary" />

            <q-card-section
                v-if="!loading"
                class="tw-grid tw-gap-6 tw-grid-cols-1 xl:tw-grid-cols-2 detail-content-section"
            >
                <section class="detail-section first-row-section">
                    <div class="section-card">
                        <div class="section-card__header">VISITA</div>
                        <div class="section-card__body">
                            <q-stepper
                                flat
                                animated
                                alternative-labels
                                class="visit-flow-stepper"
                                :vertical="$q.screen.lt.lg"
                                :model-value="visitFlowActiveStep"
                            >
                                <q-step
                                    v-for="step in visitFlowSteps"
                                    :key="step.name"
                                    :name="step.name"
                                    :title="step.title"
                                    :caption="step.caption"
                                    :icon="step.icon"
                                    :done="step.done"
                                    :color="step.color"
                                />
                            </q-stepper>
                            <q-tooltip
                                v-for="(step, index) in visitFlowSteps"
                                :key="`step-tooltip-${step.name}`"
                                :target="`.visit-flow-stepper .q-stepper__tab:nth-child(${index + 1})`"
                                class="visit-step-tooltip"
                                anchor="bottom middle"
                                self="top middle"
                                :offset="[0, -10]"
                                :style="{
                                    background: '#1d4ed8',
                                    color: '#e2e8f0',
                                    border: '1px solid #1e40af',
                                    boxShadow: '0 8px 22px rgba(30, 64, 175, 0.28)',
                                    maxWidth: '260px',
                                    whiteSpace: 'pre-line',
                                }"
                            >
                                {{ step.tooltip || `${step.title}: ${step.caption || '-'}` }}
                            </q-tooltip>

                            <div class="subsection-separator" />

                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                                <div>
                                    <div class="detail-label">IP</div>
                                    <div class="value-with-copy value-with-copy--inline">
                                        <div class="detail-value">{{ detailPageview.ip || '-' }}</div>
                                        <q-btn
                                            v-if="hasText(detailPageview.ip)"
                                            dense
                                            flat
                                            round
                                            size="sm"
                                            icon="content_copy"
                                            @click="copyValue(detailPageview.ip)"
                                        >
                                            <q-tooltip>Copiar IP</q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Campanha</div>
                                    <div class="detail-value">
                                        {{ detailCampaignName }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Canal</div>
                                    <div class="detail-value">
                                        {{ detailTrafficCategoryName }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Visitante</div>
                                    <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                        <q-badge
                                            rounded
                                            color="blue-grey-1"
                                            text-color="blue-grey-10"
                                            class="visitor-chip"
                                        >
                                            {{ detailVisitorStatusLabel }}
                                        </q-badge>
                                        <span class="detail-value tw-font-semibold">
                                            {{ detailVisitorHits.toLocaleString('pt-BR') }} {{ detailVisitorHitsLabel }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Primeira visita (campanha)</div>
                                    <div class="detail-value">
                                        {{ detailVisitor?.first_seen_at_formatted || '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Última visita (campanha)</div>
                                    <div class="detail-value">
                                        {{ detailVisitor?.last_seen_at_formatted || '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">GCLID</div>
                                    <div class="value-with-copy value-with-copy--inline">
                                        <div class="truncated-preview">{{ previewValue(detailResolvedGclid) }}</div>
                                        <q-btn
                                            v-if="hasText(detailResolvedGclid)"
                                            dense
                                            flat
                                            round
                                            size="sm"
                                            icon="content_copy"
                                            @click="copyValue(detailResolvedGclid)"
                                        >
                                            <q-tooltip>Copiar GCLID</q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                                <div class="lg:tw-col-start-2">
                                    <div class="detail-label">Código de acompanhamento</div>
                                    <div class="value-with-copy value-with-copy--inline">
                                        <div class="truncated-preview">{{ previewValue(detailComposedCode) }}</div>
                                        <q-btn
                                            v-if="hasText(detailComposedCode)"
                                            dense
                                            flat
                                            round
                                            size="sm"
                                            icon="content_copy"
                                            @click="copyValue(detailComposedCode)"
                                        >
                                            <q-tooltip>Copiar código composto</q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                            </div>

                            <div class="subsection-separator" />
                            <div class="subsection-title">GEOLOCALIZAÇÃO</div>
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                                <div>
                                    <div class="detail-label">País</div>
                                    <div class="detail-value tw-flex tw-items-center tw-gap-2">
                                        <img
                                            v-if="detailCountryFlag"
                                            :src="detailCountryFlag"
                                            :alt="detailGeo.country_code"
                                            class="tw-w-6 tw-h-4 tw-rounded-sm tw-object-cover tw-border tw-border-gray-200"
                                        />
                                        <span>{{ detailGeo.country_name ?? '-' }}</span>
                                    </div>
                                </div>
                                <div v-for="field in GEO_FIELDS" :key="field.key">
                                    <div class="detail-label">{{ field.label }}</div>
                                    <div class="detail-value">
                                        {{ detailGeo[field.key] ?? '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Latitude/Longitude</div>
                                    <div class="detail-value">
                                        {{ detailLatLong }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section first-row-section">
                    <div class="section-card">
                        <div class="section-card__header">DISPOSITIVO, NAVEGADOR &amp; REDE</div>
                        <div class="section-card__body">
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                                <div>
                                    <div class="detail-label">Categoria</div>
                                    <div class="detail-value">{{ detailDeviceSummary }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Marca</div>
                                    <div class="detail-value">{{ formatValue(detailPageview.device_brand) }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Modelo</div>
                                    <div class="detail-value">{{ formatValue(detailPageview.device_model) }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Sistema Operacional</div>
                                    <div class="detail-value">{{ detailOsSummary }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Navegador</div>
                                    <div class="detail-value">{{ detailBrowserSummary }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Idioma</div>
                                    <div class="detail-value">{{ formatValue(detailPageview.language) }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Plataforma</div>
                                    <div class="detail-value">{{ formatValue(detailPageview.platform) }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Tela</div>
                                    <div class="detail-value">
                                        {{ formatValue(detailPageview.screen_width) }} x {{ formatValue(detailPageview.screen_height) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Viewport</div>
                                    <div class="detail-value">
                                        {{ formatValue(detailPageview.viewport_width) }} x {{ formatValue(detailPageview.viewport_height) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">DPR</div>
                                    <div class="detail-value">{{ formatValue(detailPageview.device_pixel_ratio) }}</div>
                                </div>
                            </div>

                            <div class="subsection-separator" />
                            <div class="subsection-title">INFORMAÇÕES DA REDE</div>
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4">
                                <div>
                                    <div class="detail-label">ISP</div>
                                    <div class="detail-value">
                                        {{ detailNetwork.isp || '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Organização</div>
                                    <div class="detail-value">
                                        {{ detailNetwork.organization || '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Categoria IP (Lookup)</div>
                                    <div class="tw-mt-1">
                                        <div
                                            class="ip-category-label"
                                            :style="{ color: detailNetworkCategoryColor }"
                                        >
                                            {{ detailNetworkCategoryName }}
                                        </div>
                                        <div class="ip-category-description">
                                            {{ detailNetworkCategoryDescription }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Fraud Score</div>
                                    <div class="detail-value">
                                        {{ detailNetwork.fraud_score ?? '-' }}
                                    </div>
                                </div>
                            </div>
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-3 tw-mt-4">
                                <div v-for="flag in NETWORK_FLAGS" :key="flag.key">
                                    <div class="detail-label">{{ flag.label }}</div>
                                    <div class="detail-value">
                                        {{ formatFlag(detailNetworkFlags[flag.key]) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section xl:tw-col-span-2">
                    <div class="section-card">
                        <div class="section-card__header">ORIGEM DO TRÁFEGO</div>
                        <div class="section-card__body section-body--compact">
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-3">
                                <div>
                                    <div class="detail-label">Canal</div>
                                    <div class="detail-value">{{ detailTrafficCategoryName }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Motivo da classificação</div>
                                    <div class="detail-value">{{ detailTrafficReasonLabel }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Domínio de referrer</div>
                                    <div class="detail-value">{{ detailReferrerHost || '-' }}</div>
                                </div>
                                <div>
                                    <div class="detail-label">Referrer completo</div>
                                    <div class="value-with-copy">
                                        <div class="detail-value tw-break-all">{{ detailReferrer || '-' }}</div>
                                        <q-btn
                                            v-if="hasText(detailReferrer)"
                                            dense
                                            flat
                                            round
                                            size="sm"
                                            icon="content_copy"
                                            @click="copyValue(detailReferrer)"
                                        >
                                            <q-tooltip>Copiar referrer</q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                                <div class="lg:tw-col-span-2">
                                    <div class="detail-label">Landing page (sem parâmetros)</div>
                                    <div class="value-with-copy">
                                        <div class="detail-value tw-break-all">{{ detailLandingCleanUrl }}</div>
                                        <q-btn
                                            v-if="hasText(detailLandingUrl)"
                                            dense
                                            flat
                                            round
                                            size="sm"
                                            icon="content_copy"
                                            @click="copyValue(detailLandingUrl)"
                                        >
                                            <q-tooltip>Copiar landing URL</q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                                <div class="lg:tw-col-span-2">
                                    <div class="detail-label">Página atual (sem parâmetros)</div>
                                    <div class="detail-value tw-break-all">{{ detailCleanUrl }}</div>
                                </div>
                                <div class="lg:tw-col-span-4">
                                    <div class="detail-label">Parâmetros da URL</div>
                                    <div v-if="hasUrlParams" class="url-params-inline">
                                        <div
                                            v-for="([key, value]) in detailUrlParams"
                                            :key="`query-${key}-${value}`"
                                            class="url-param-chip"
                                        >
                                            <div class="url-param-key">{{ key }}</div>
                                            <div class="value-with-copy value-with-copy--inline">
                                                <div class="truncated-preview">{{ previewValue(value) }}</div>
                                                <q-btn
                                                    dense
                                                    flat
                                                    round
                                                    size="sm"
                                                    icon="content_copy"
                                                    @click="copyValue(value)"
                                                >
                                                    <q-tooltip>Copiar {{ key }}</q-tooltip>
                                                </q-btn>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="traffic-empty">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.pageview-detail-card {
    width: 100vw;
    max-width: 100vw;
}

.detail-close-section {
    padding-top: 0.35rem;
    padding-bottom: 0.1rem;
}

.detail-content-section {
    padding-top: 0.35rem;
}

@media (min-width: 1024px) {
    .pageview-detail-card {
        width: 80vw;
        max-width: 80vw;
    }
}

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.section-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    overflow: hidden;
}

.section-card__header {
    background: #f8fafc;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #475569;
}

.section-card__body {
    padding: 1rem;
}

.section-body--stack > * + * {
    margin-top: 1rem;
}

.section-body--compact > * + * {
    margin-top: 0.75rem;
}

.subsection-separator {
    margin: 1rem 0;
    border-top: 1px solid #e2e8f0;
}

.subsection-title {
    margin-bottom: 0.75rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #475569;
}

.visit-flow-stepper {
    border: 0;
    border-radius: 0;
    background: transparent;
}

.visit-flow-stepper :deep(.q-stepper__tab) {
    cursor: default;
}

.visit-flow-stepper :deep(.q-stepper__tab .q-focus-helper) {
    display: none;
}

.visit-flow-stepper :deep(.q-stepper__tab:hover) {
    background: transparent;
}

.visit-flow-stepper :deep(.q-stepper__tab) {
    color: #7c8aa0;
}

.visit-flow-stepper :deep(.q-stepper__tab.q-stepper__tab--done .q-stepper__title),
.visit-flow-stepper :deep(.q-stepper__tab.q-stepper__tab--done .q-stepper__caption) {
    color: #54657e;
}

.visit-flow-stepper :deep(.q-stepper__tab.q-stepper__tab--done .q-stepper__caption) {
    color: #97a7bb;
}

.visit-flow-stepper :deep(.q-stepper__tab:nth-child(4).q-stepper__tab--done .q-stepper__title),
.visit-flow-stepper :deep(.q-stepper__tab:nth-child(4).q-stepper__tab--done .q-stepper__caption) {
    color: var(--q-positive);
}

@media (min-width: 1024px) {
    .visit-flow-stepper :deep(.q-stepper__header) {
        overflow: hidden;
        min-height: 0;
    }

    .visit-flow-stepper :deep(.q-stepper__tab) {
        flex: 1 1 0;
        min-width: 0;
        min-height: 0;
        padding: 0.3rem 0.2rem 0;
    }

    .visit-flow-stepper :deep(.q-stepper__step-inner) {
        padding-bottom: 0;
    }

    .visit-flow-stepper :deep(.q-stepper__title) {
        font-size: 0.95rem;
        line-height: 1.1;
        font-weight: 600;
        color: #4b5d76;
    }

    .visit-flow-stepper :deep(.q-stepper__caption) {
        font-size: 0.82rem;
        line-height: 1.22;
        font-weight: 400;
        color: #8fa1b8;
        white-space: normal;
        max-width: 170px;
        margin: 0.05rem auto 0;
        text-wrap: balance;
    }
}

.rounded-borders {
    border: none;
}

.url-params-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.traffic-empty {
    margin-top: 0.25rem;
    font-size: 0.9rem;
    color: #64748b;
}

.url-param-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    background: #f8fafc;
    max-width: 100%;
}

.url-param-key {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    white-space: nowrap;
}

.url-param-value {
    font-size: 0.875rem;
    font-weight: 500;
    color: #0f172a;
    word-break: break-all;
}

.detail-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.detail-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #0f172a;
}

.value-with-copy {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
}

.value-with-copy--inline {
    align-items: center;
}

.ip-category-label {
    font-size: 0.95rem;
    font-weight: 600;
}

.ip-category-description {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.15rem;
}

.gclid-param-row {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.truncated-preview {
    position: relative;
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    padding-right: 18px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #0f172a;
}

.truncated-preview::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 24px;
    height: 100%;
    pointer-events: none;
    background: linear-gradient(to right, rgba(255, 255, 255, 0), #ffffff 80%);
}

.visitor-chip {
    font-size: 12px;
    line-height: 1;
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
}



@media (min-width: 1024px) {
    .first-row-section > .section-card {
        height: 100%;
    }
}
</style>
