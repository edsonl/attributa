<script setup>
import { computed } from 'vue'
import { useQuasar } from 'quasar'

const FALLBACK_IP_CATEGORY_DETAIL = {
    name: 'Não determinado',
    color_hex: '#FCE7F3',
    description: 'Categoria ainda não determinada.',
}

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

const detailDrawer = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})
const drawerWidth = computed(() => {
    if ($q.screen.lt.lg) return $q.screen.width
    return Math.round($q.screen.width * 0.8)
})

const detailPageview = computed(() => props.payload?.pageview ?? {})
const detailUrl = computed(() => props.payload?.url ?? { full: null, origin: null, path: null, query_params: {} })
const detailGeo = computed(() => props.payload?.geo ?? {})
const detailNetwork = computed(() => props.payload?.network ?? {})
const detailUrlParams = computed(() => {
    const params = detailUrl.value?.query_params ?? {}
    return Object.entries(params)
})
const hasUrlParams = computed(() => detailUrlParams.value.length > 0)
const detailCampaignName = computed(() => detailPageview.value?.campaign?.name ?? '-')
const detailNetworkFlags = computed(() => detailNetwork.value?.flags ?? {})
const detailPageviewCategory = computed(() => detailPageview.value?.ip_category ?? FALLBACK_IP_CATEGORY_DETAIL)
const detailNetworkCategory = computed(() => detailNetwork.value?.ip_category ?? null)
const detailNetworkCategoryColor = computed(() => detailNetworkCategory.value?.color_hex ?? '#475569')
const detailNetworkCategoryName = computed(() => detailNetworkCategory.value?.name ?? '-')
const detailNetworkCategoryDescription = computed(() => detailNetworkCategory.value?.description ?? 'Sem descrição.')
const detailCleanUrl = computed(() => stripQueryString(detailUrl.value?.full || ''))
const detailResolvedGclid = computed(() => resolveGclid())
const detailCountryFlag = computed(() => {
    const code = detailGeo.value?.country_code
    if (!code) return null
    return `${props.assetBaseUrl}/assets/country-flags/${String(code).toLowerCase()}.svg`
})

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

function formatFlag(value) {
    if (value === null || value === undefined) return '-'
    return value ? 'Sim' : 'Não'
}

function formatParamValue(value) {
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

function hasText(value) {
    return value !== null && value !== undefined && String(value).trim() !== ''
}

function isGclidParam(key) {
    return String(key || '').toLowerCase() === 'gclid'
}

function resolveGclid() {
    const pageviewGclid = detailPageview.value?.gclid
    if (hasText(pageviewGclid)) return String(pageviewGclid)

    const queryParams = detailUrl.value?.query_params ?? {}
    const match = Object.entries(queryParams).find(([key]) => isGclidParam(key))
    if (!match) return ''

    return formatParamValue(match[1])
}

async function copyParamValue(value) {
    const text = formatParamValue(value)

    try {
        await navigator.clipboard.writeText(text)
        $q.notify({ type: 'positive', message: 'GCLID copiado.' })
    } catch (error) {
        $q.notify({ type: 'negative', message: 'Nao foi possivel copiar.' })
    }
}
</script>

<template>
    <div v-if="detailDrawer" class="detail-drawer-backdrop" @click="detailDrawer = false" />
    <q-drawer
        v-model="detailDrawer"
        side="right"
        overlay
        bordered
        :width="drawerWidth"
        class="pageview-detail-drawer"
    >
        <div class="drawer-shell">
            <div class="drawer-header">
                <q-btn icon="close" flat round dense @click="detailDrawer = false" />
            </div>

            <q-linear-progress v-if="loading" indeterminate color="primary" />

            <div
                v-if="!loading"
                class="drawer-content tw-grid tw-gap-6 tw-grid-cols-1 xl:tw-grid-cols-2"
            >
                <section class="detail-section">
                    <div class="section-card">
                        <div class="section-card__header">VISITA</div>
                        <div class="section-card__body">
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
                                <div>
                                    <div class="detail-label">Data/Horário</div>
                                    <div class="detail-value">
                                        {{ detailPageview.created_at_formatted }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Campanha</div>
                                    <div class="detail-value">
                                        {{ detailCampaignName }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">IP</div>
                                    <div class="detail-value">
                                        {{ detailPageview.ip || '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Categoria de IP</div>
                                    <div class="tw-mt-1">
                                        <div
                                            class="ip-category-label"
                                            :style="{ color: detailPageviewCategory.color_hex }"
                                        >
                                            {{ detailPageviewCategory.name }}
                                        </div>
                                        <div class="ip-category-description">
                                            {{ detailPageviewCategory.description || 'Sem descrição.' }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-label">Conversão</div>
                                    <div class="tw-mt-1">
                                        <q-badge
                                            v-if="detailPageview.conversion"
                                            color="green"
                                            label="Convertido"
                                        />
                                        <q-badge
                                            v-else
                                            color="grey-4"
                                            text-color="dark"
                                            label="Não convertido"
                                        />
                                    </div>
                                </div>
                                <div v-if="hasText(detailResolvedGclid)">
                                    <div class="detail-label">GCLID</div>
                                    <div class="gclid-param-row">
                                        <div class="gclid-preview">{{ detailResolvedGclid }}</div>
                                        <q-btn
                                            dense
                                            flat
                                            round
                                            size="sm"
                                            icon="content_copy"
                                            @click="copyParamValue(detailResolvedGclid)"
                                        >
                                            <q-tooltip>Copiar GCLID</q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section">
                    <div class="section-card">
                        <div class="section-card__header">GEOLOCALIZAÇÃO</div>
                        <div class="section-card__body">
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
                                <div v-for="field in [{ label: 'Código', key: 'country_code' }, { label: 'Região', key: 'region_name' }, { label: 'Cidade', key: 'city' }, { label: 'Latitude', key: 'latitude' }, { label: 'Longitude', key: 'longitude' }, { label: 'Timezone', key: 'timezone' }]" :key="field.key">
                                    <div class="detail-label">{{ field.label }}</div>
                                    <div class="detail-value">
                                        {{ detailGeo[field.key] ?? '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section xl:tw-col-span-2">
                    <div class="section-card">
                        <div class="section-card__header">ORIGEM DA URL</div>
                        <div class="section-card__body section-body--stack">
                            <div>
                                <div class="detail-label">Página (sem parâmetros)</div>
                                <div class="detail-value tw-break-all">
                                    {{ detailCleanUrl }}
                                </div>
                            </div>
                            <div>
                                <div class="detail-label">Parâmetros</div>
                                <div class="tw-mt-2">
                                    <div v-if="hasUrlParams" class="url-params-inline">
                                        <div
                                            v-for="([key, value]) in detailUrlParams"
                                            :key="`${key}-${value}`"
                                            class="url-param-chip"
                                        >
                                            <div class="url-param-key">{{ key }}</div>
                                            <div v-if="isGclidParam(key)" class="gclid-param-row">
                                                <div class="gclid-preview">{{ formatParamValue(value) }}</div>
                                                <q-btn
                                                    dense
                                                    flat
                                                    round
                                                    size="sm"
                                                    icon="content_copy"
                                                    @click="copyParamValue(value)"
                                                >
                                                    <q-tooltip>Copiar GCLID</q-tooltip>
                                                </q-btn>
                                            </div>
                                            <div v-else class="url-param-value">{{ formatParamValue(value) }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="tw-text-sm tw-text-slate-500">Sem parâmetros na URL.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="detail-section xl:tw-col-span-2">
                    <div class="section-card">
                        <div class="section-card__header">REDE &amp; SEGURANÇA</div>
                        <div class="section-card__body">
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
                                <div>
                                    <div class="detail-label">Última verificação</div>
                                    <div class="detail-value">
                                        {{ detailNetwork.last_checked_formatted ?? detailNetwork.last_checked ?? '-' }}
                                    </div>
                                </div>
                            </div>
                            <div class="tw-grid md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-3 tw-mt-4">
                                <div v-for="flag in [{ key: 'is_proxy', label: 'Proxy' }, { key: 'is_vpn', label: 'VPN' }, { key: 'is_tor', label: 'Tor' }, { key: 'is_datacenter', label: 'Datacenter' }, { key: 'is_bot', label: 'Bot' }]" :key="flag.key">
                                    <div class="detail-label">{{ flag.label }}</div>
                                    <div class="detail-value">
                                        {{ formatFlag(detailNetworkFlags[flag.key]) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </q-drawer>
</template>

<style scoped>
.pageview-detail-drawer {
    max-width: 100vw;
}

:deep(.q-drawer.pageview-detail-drawer) {
    z-index: 3100 !important;
}

.drawer-shell {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.drawer-header {
    display: flex;
    justify-content: flex-end;
    padding: 0.5rem 0.75rem;
}

.drawer-content {
    flex: 1;
    overflow: auto;
    padding: 0 1rem 1rem;
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

.rounded-borders {
    border: none;
}

.detail-drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.36);
    z-index: 3000;
}

.url-params-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
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

.gclid-preview {
    position: relative;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    padding-right: 18px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #0f172a;
}

.gclid-preview::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 24px;
    height: 100%;
    pointer-events: none;
    background: linear-gradient(to right, rgba(255, 255, 255, 0), #ffffff 80%);
}
</style>
