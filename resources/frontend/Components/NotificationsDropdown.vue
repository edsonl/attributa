<script setup>
import { computed, nextTick, ref } from 'vue'
import axios from 'axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()

const menuOpen = ref(false)
const menuRef = ref(null)
const loading = ref(false)
const unreadCount = ref(0)
const rows = ref([])
const pagination = ref({
    page: 1,
    perPage: 8,
    lastPage: 1,
    total: 0,
})

const columns = [
    { name: 'notification', label: 'Notificação', field: 'title', align: 'left', style: 'width: 88%;', headerStyle: 'width: 88%;' },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right', style: 'width: 12%;', headerStyle: 'width: 12%;' },
]

const hasRows = computed(() => Array.isArray(rows.value) && rows.value.length > 0)
const unreadBadge = computed(() => Math.max(0, Number(unreadCount.value || 0)))

function severityColor(item) {
    const slug = String(item?.type?.slug || '')
    if (slug === 'lead_trash') return 'grey-6'

    const severity = String(item?.type?.severity || 'info')
    if (severity === 'error') return 'negative'
    if (severity === 'warning') return 'warning'
    if (severity === 'success') return 'positive'
    return 'primary'
}

function severityDotColor(item) {
    const colorKey = severityColor(item)
    if (colorKey === 'grey-6') return '#9ca3af'
    if (colorKey === 'negative') return '#ef4444'
    if (colorKey === 'warning') return '#f59e0b'
    if (colorKey === 'positive') return '#22c55e'
    return '#1e40af'
}

function statusLabel(status) {
    return String(status) === 'read' ? 'Lida' : 'Não lida'
}

function statusColor(status) {
    return String(status) === 'read' ? 'grey-6' : 'negative'
}

async function fetchUnreadCount() {
    try {
        const { data } = await axios.get(route('panel.notifications.unread-count'))
        unreadCount.value = Number(data?.unread_count || 0)
    } catch {
        // noop: contador falhou, não deve quebrar navbar
    }
}

async function fetchNotifications(page = 1) {
    loading.value = true
    try {
        const { data } = await axios.get(route('panel.notifications.data'), {
            params: {
                page,
                per_page: pagination.value.perPage,
            },
        })

        rows.value = Array.isArray(data?.data) ? data.data : []
        pagination.value = {
            ...pagination.value,
            page: Number(data?.current_page || 1),
            perPage: Number(data?.per_page || pagination.value.perPage),
            lastPage: Number(data?.last_page || 1),
            total: Number(data?.total || 0),
        }
        unreadCount.value = Number(data?.unread_count || unreadCount.value || 0)
        await nextTick()
        menuRef.value?.updatePosition?.()
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível carregar as notificações.',
        })
    } finally {
        loading.value = false
    }
}

async function handleMenuShow() {
    await fetchNotifications(1)
}

async function markAsRead(row) {
    if (!row?.id || row.status === 'read') return

    try {
        await axios.patch(route('panel.notifications.mark-as-read', row.id))
        row.status = 'read'
        unreadCount.value = Math.max(0, Number(unreadCount.value || 0) - 1)
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
        }

        const becameEmptyPage = rows.value.length === 0 && pagination.value.page > 1
        if (becameEmptyPage) {
            await fetchNotifications(pagination.value.page - 1)
        } else {
            pagination.value.total = Math.max(0, Number(pagination.value.total || 0) - 1)
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
    } catch {
        $q.notify({
            type: 'negative',
            message: 'Não foi possível marcar todas como lidas.',
        })
    }
}

fetchUnreadCount()
</script>

<template>
    <div class="tw-rounded-full tw-p-1 tw-bg-white/10 hover:tw-bg-white/20 tw-transition tw-me-3">
        <q-btn
            flat
            round
            dense
            :ripple="false"
            class="toolbar-icon tw-text-white"
            icon="notifications"
        >
            <q-badge v-if="unreadBadge > 0" color="negative" floating>{{ unreadBadge }}</q-badge>

            <q-menu
                ref="menuRef"
                v-model="menuOpen"
                @show="handleMenuShow"
            >
                <div>
                    <div class="tw-flex tw-items-center tw-justify-between tw-px-3 tw-pt-3 tw-pb-2">
                        <div class="tw-text-sm tw-font-semibold">Notificações</div>
                        <q-btn
                            flat
                            dense
                            no-caps
                            size="sm"
                            label="Marcar todas como lidas"
                            :disable="loading || unreadBadge === 0"
                            @click="markAllAsRead"
                        />
                    </div>

                    <q-table
                        flat
                        dense
                        :rows="rows"
                        :columns="columns"
                        row-key="id"
                        :rows-per-page-options="[0]"
                        hide-bottom
                        hide-header
                        :loading="loading"
                    >
                        <template #loading>
                            <q-inner-loading showing color="primary">
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <q-spinner size="20px" />
                                    <span class="tw-text-sm">Carregando notificações...</span>
                                </div>
                            </q-inner-loading>
                        </template>

                        <template #body-cell-notification="props">
                            <q-td :props="props">
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <span class="notification-dot" :style="{ backgroundColor: severityDotColor(props.row) }"></span>
                                    <div class="tw-min-w-0">
                                        <div class="tw-text-sm tw-font-medium tw-leading-5">
                                            {{ props.row.title || '-' }}
                                            <span class="notification-date-inline">
                                                {{ props.row.created_at_formatted || '-' }}
                                            </span>
                                        </div>
                                        <div class="tw-text-xs tw-text-slate-500 tw-leading-4 tw-whitespace-normal tw-break-words">
                                            {{ props.row.message || '-' }}
                                        </div>
                                    </div>
                                </div>
                            </q-td>
                        </template>

                        <template #body-cell-actions="props">
                            <q-td :props="props" class="tw-text-right">
                                <q-btn
                                    dense
                                    flat
                                    size="sm"
                                    :icon="props.row.status === 'read' ? 'done_all' : 'done'"
                                    :color="props.row.status === 'read' ? 'positive' : 'grey-7'"
                                    @click="markAsRead(props.row)"
                                >
                                    <q-tooltip>Marcar como lida</q-tooltip>
                                </q-btn>
                                <q-btn
                                    dense
                                    flat
                                    size="sm"
                                    color="grey-6"
                                    icon="delete"
                                    @click="removeNotification(props.row)"
                                >
                                    <q-tooltip>Excluir</q-tooltip>
                                </q-btn>
                            </q-td>
                        </template>

                        <template #no-data>
                            <div class="tw-w-full tw-text-center tw-text-sm tw-text-slate-500 tw-py-6">
                                Nenhuma notificação encontrada.
                            </div>
                        </template>
                    </q-table>

                    <div v-if="hasRows" class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-border-t tw-border-slate-100">
                        <div class="tw-text-xs tw-text-slate-500">
                            {{ pagination.total }} registro(s)
                        </div>
                        <q-pagination
                            v-model="pagination.page"
                            color="primary"
                            size="sm"
                            :max="Math.max(1, pagination.lastPage)"
                            :max-pages="5"
                            boundary-numbers
                            direction-links
                            @update:model-value="fetchNotifications"
                        />
                    </div>
                </div>
            </q-menu>
        </q-btn>
    </div>
</template>

<style scoped>
.q-menu :deep(.q-table td),
.q-menu :deep(.q-table th) {
    white-space: normal;
    word-break: break-word;
}

.q-menu :deep(table) {
    width: 100%;
    table-layout: auto;
}

.notification-date-inline {
    margin-left: 8px;
    font-size: 12px;
    font-weight: 400;
    color: #64748b;
}

.notification-dot {
    width: 10px;
    height: 10px;
    min-width: 10px;
    min-height: 10px;
    display: inline-block;
    border-radius: 999px;
    flex: 0 0 10px;
}
</style>
