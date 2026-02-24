<script setup>
import { computed, nextTick, ref } from 'vue'
import axios from 'axios'
import { useQuasar } from 'quasar'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    headerHeight: { type: Number, default: 50 },
})

const emit = defineEmits(['update:modelValue', 'unread-updated'])
const $q = useQuasar()

const open = computed({
    get: () => Boolean(props.modelValue),
    set: (value) => emit('update:modelValue', Boolean(value)),
})

const loading = ref(false)
const loadingMore = ref(false)
const rows = ref([])
const unreadCount = ref(0)
const scrollTargetRef = ref(null)
const infiniteRef = ref(null)
const pagination = ref({
    page: 1,
    perPage: 12,
    lastPage: 1,
    total: 0,
})

const drawerStyle = computed(() => ({
    top: `${props.headerHeight}px`,
    height: `calc(100vh - ${props.headerHeight}px)`,
}))
const drawerWidth = computed(() => {
    if ($q.screen.lt.md) {
        return Math.min(440, Math.round($q.screen.width * 0.95))
    }
    if ($q.screen.lt.lg) {
        return 460
    }
    return 560
})

function severityColor(item) {
    const slug = String(item?.type?.slug || '')
    if (slug === 'lead_trash') return '#9ca3af'

    const severity = String(item?.type?.severity || 'info')
    if (severity === 'error') return '#ef4444'
    if (severity === 'warning') return '#f59e0b'
    if (severity === 'success') return '#22c55e'
    return '#1e40af'
}

async function fetchNotifications(page = 1, { append = false } = {}) {
    if (append) {
        loadingMore.value = true
    } else {
        loading.value = true
    }

    try {
        const { data } = await axios.get(route('panel.notifications.data'), {
            params: {
                page,
                per_page: pagination.value.perPage,
            },
        })

        const incoming = Array.isArray(data?.data) ? data.data : []
        rows.value = append ? [...rows.value, ...incoming] : incoming
        pagination.value = {
            ...pagination.value,
            page: Number(data?.current_page || 1),
            perPage: Number(data?.per_page || pagination.value.perPage),
            lastPage: Number(data?.last_page || 1),
            total: Number(data?.total || 0),
        }
        unreadCount.value = Number(data?.unread_count || 0)
        emit('unread-updated', unreadCount.value)
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível carregar as notificações.',
        })
    } finally {
        if (append) {
            loadingMore.value = false
        } else {
            loading.value = false
        }
    }
}

async function onDrawerShow() {
    await fetchNotifications(1)
    await nextTick()
    infiniteRef.value?.reset()
    infiniteRef.value?.resume()
}

async function markAsRead(row) {
    if (!row?.id || row.status === 'read') return
    try {
        await axios.patch(route('panel.notifications.mark-as-read', row.id))
        row.status = 'read'
        unreadCount.value = Math.max(0, Number(unreadCount.value || 0) - 1)
        emit('unread-updated', unreadCount.value)
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível marcar a notificação como lida.',
        })
    }
}

async function removeNotification(row) {
    if (!row?.id) return
    try {
        await axios.delete(route('panel.notifications.destroy', row.id))
        const wasUnread = row.status !== 'read'
        rows.value = rows.value.filter(item => Number(item.id) !== Number(row.id))
        if (wasUnread) {
            unreadCount.value = Math.max(0, Number(unreadCount.value || 0) - 1)
            emit('unread-updated', unreadCount.value)
        }
        pagination.value.total = Math.max(0, Number(pagination.value.total || 0) - 1)
        if (rows.value.length === 0 && pagination.value.total > 0) {
            await fetchNotifications(1)
        }
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível excluir a notificação.',
        })
    }
}

async function markAllAsRead() {
    try {
        await axios.post(route('panel.notifications.mark-all-as-read'))
        rows.value = rows.value.map(item => ({ ...item, status: 'read' }))
        unreadCount.value = 0
        emit('unread-updated', unreadCount.value)
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível marcar todas como lidas.',
        })
    }
}

async function onLoadMore(_index, done) {
    if (loading.value || loadingMore.value || pagination.value.page >= pagination.value.lastPage) {
        done()
        return
    }

    await fetchNotifications(pagination.value.page + 1, { append: true })

    if (pagination.value.page >= pagination.value.lastPage) {
        infiniteRef.value?.stop()
    }

    done()
}
</script>

<template>
    <q-drawer
        v-model="open"
        side="right"
        overlay
        bordered
        :width="drawerWidth"
        class="notifications-right-drawer"
        :style="drawerStyle"
        @show="onDrawerShow"
    >
        <div class="tw-h-full tw-flex tw-flex-col">
            <div class="tw-flex tw-items-center tw-justify-between tw-px-3 tw-py-3 tw-border-b tw-border-slate-200">
                <div>
                    <div class="tw-text-base tw-font-semibold">Notificações</div>
                    <div class="tw-text-xs tw-text-slate-500">{{ unreadCount }} não lida(s)</div>
                </div>
                <div class="tw-flex tw-items-center tw-gap-2">
                    <q-btn
                        flat
                        dense
                        no-caps
                        size="sm"
                        label="Marcar todas como lidas"
                        :disable="loading || unreadCount === 0"
                        @click="markAllAsRead"
                    />
                    <q-btn flat round dense icon="close" @click="open = false" />
                </div>
            </div>

            <div ref="scrollTargetRef" class="tw-flex-1 tw-min-h-0 tw-overflow-auto">
                <q-inner-loading :showing="loading && rows.length === 0" color="primary">
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <q-spinner size="20px" />
                        <span class="tw-text-sm">Carregando notificações...</span>
                    </div>
                </q-inner-loading>

                <div v-if="!loading && rows.length === 0" class="tw-p-4 tw-text-sm tw-text-slate-500">
                    Nenhuma notificação encontrada.
                </div>

                <div v-else>
                    <div
                        v-for="row in rows"
                        :key="row.id"
                        class="notification-row"
                    >
                        <div class="notification-cell-layout">
                            <div class="notification-dot-wrap">
                                <span class="notification-dot" :style="{ backgroundColor: severityColor(row) }"></span>
                            </div>
                            <div class="tw-min-w-0 notification-text-wrap">
                                <div class="tw-text-sm tw-font-medium notification-title-line">
                                    {{ row.title || '-' }}
                                    <span class="notification-date-inline">{{ row.created_at_formatted || '-' }}</span>
                                </div>
                                <div class="tw-text-xs tw-text-slate-500 notification-message-line tw-break-words">
                                    {{ row.message || '-' }}
                                </div>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <q-btn
                                dense
                                flat
                                size="sm"
                                :icon="row.status === 'read' ? 'done_all' : 'done'"
                                :color="row.status === 'read' ? 'positive' : 'grey-7'"
                                @click="markAsRead(row)"
                            />
                            <q-btn dense flat size="sm" color="grey-6" icon="delete" @click="removeNotification(row)" />
                        </div>
                    </div>

                    <q-infinite-scroll
                        ref="infiniteRef"
                        :scroll-target="scrollTargetRef"
                        :offset="120"
                        @load="onLoadMore"
                    >
                        <template #loading>
                            <div class="tw-flex tw-justify-center tw-py-3">
                                <q-spinner color="primary" size="20px" />
                            </div>
                        </template>
                    </q-infinite-scroll>
                </div>
            </div>

            <div class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-border-t tw-border-slate-200">
                <div class="tw-text-xs tw-text-slate-500">{{ pagination.total }} registro(s)</div>
                <div class="tw-text-xs tw-text-slate-400" v-if="loadingMore">Carregando mais...</div>
            </div>
        </div>
    </q-drawer>
</template>

<style scoped>
.notification-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 8px;
    padding: 8px 12px;
    border-bottom: 1px solid #e5e7eb;
}

.notification-row:last-child {
    border-bottom: 0;
}

.notification-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 2px;
}

.notification-dot {
    width: 10px;
    height: 10px;
    min-width: 10px;
    min-height: 10px;
    display: inline-block;
    flex: 0 0 10px;
    border-radius: 999px;
}

.notification-cell-layout {
    display: grid;
    grid-template-columns: 14px 1fr;
    column-gap: 8px;
    align-items: center;
    min-height: 42px;
}

.notification-dot-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.notification-text-wrap {
    min-width: 0;
}

.notification-title-line {
    line-height: 1.35;
    margin-bottom: 2px;
}

.notification-message-line {
    line-height: 1.45;
}

.notification-date-inline {
    margin-left: 8px;
    font-size: 11px;
    font-weight: 500;
    color: #334155;
}
</style>
