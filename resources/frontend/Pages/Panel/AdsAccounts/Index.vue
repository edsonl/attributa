<script setup>
import { router } from '@inertiajs/vue3'
import { Dialog } from 'quasar'
defineProps({
    accounts: Array
})

function toggleStatus(account) {
    const action = account.active ? 'desativar' : 'ativar'

    Dialog.create({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} conta`,
        message: `Deseja ${action} a conta ${account.google_ads_customer_id}?`,
        cancel: true,
        persistent: true,
    }).onOk(() => {
        router.patch(
            route('panel.ads-accounts.toggle', account.id)
        )
    })
}

</script>

<template>
    <q-page padding>
        <div class="row items-center q-mb-md">
            <div class="col">
                <h5 class="text-weight-medium">
                    Contas de Anúncios
                </h5>
                <div class="text-grey-7 text-sm">
                    Gerencie as contas do Google Ads conectadas ao sistema
                </div>
            </div>

            <div class="col-auto">
                <q-btn
                    color="primary"
                    label="Conectar Google Ads"
                    icon="link"
                    disable
                >
                    <q-tooltip>
                        OAuth será implementado no próximo passo
                    </q-tooltip>
                </q-btn>
            </div>
        </div>

        <q-card flat bordered>
            <q-table
                :rows="accounts"
                row-key="id"
                :columns="[
          { name: 'customer', label: 'Customer ID', field: 'google_ads_customer_id' },
          { name: 'email', label: 'Email', field: 'email' },
          { name: 'status', label: 'Status', field: 'active' },
          { name: 'created', label: 'Criada em', field: 'created_at' },
          { name: 'actions', label: 'Ações', field: 'actions', align: 'right' }
        ]"
                flat
            >
                <template #body-cell-status="props">
                    <q-td>
                        <q-badge
                            :color="props.value ? 'green' : 'grey'"
                            outline
                        >
                            {{ props.value ? 'Ativa' : 'Inativa' }}
                        </q-badge>
                    </q-td>
                </template>
                <template #body-cell-actions="props">
                    <q-td align="right" class="q-gutter-xs">
                        <!-- Desativar -->
                        <q-btn
                            v-if="props.row.active"
                            icon="pause_circle"
                            color="warning"
                            flat
                            round
                            size="sm"
                            @click="toggleStatus(props.row)"
                        >
                            <q-tooltip>Desativar conta</q-tooltip>
                        </q-btn>
                        <!-- Ativar -->
                        <q-btn
                            v-else
                            icon="play_circle"
                            color="positive"
                            flat
                            round
                            size="sm"
                            @click="toggleStatus(props.row)"
                        >
                            <q-tooltip>Ativar conta</q-tooltip>
                        </q-btn>
                    </q-td>
                </template>
            </q-table>
        </q-card>
    </q-page>
</template>
