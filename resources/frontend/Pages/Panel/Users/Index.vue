<script setup>
import IndexTable from '@/Components/Shared/Scaffold/IndexTable.vue'
import {Link} from "@inertiajs/vue3";

const columns = [
    { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
    { name: 'name', label: 'Nome', field: 'name', align: 'left', sortable: true },
    { name: 'email', label: 'E-mail', field: 'email', align: 'left', sortable: true },
    { name: 'created_at', label: 'Criado em', field: 'created_at', align: 'left', sortable: true },
    { name: 'actions', label: 'Ações', field: 'actions', align: 'right' },
]

const routes = {
    index: 'panel.users.index',
    create: 'panel.users.create',
    edit: 'panel.users.edit',
    destroy: 'panel.users.destroy',
    bulkDestroy: 'panel.users.bulk-destroy',
}
</script>

<template>
    <IndexTable
        title="Gerenciar usuários"
        collectionKey="users"
        :columns="columns"
        :routes="routes"
        :labels="{
      create: 'Novo usuário',
      deleteOne: 'Excluir usuário!',
      bulkDeleteSingular: 'Excluir usuário',
      bulkDeletePlural: 'Excluir usuários selecionados',
      searchPlaceholder:'Pesquisar por nome ou e-mail'
    }">
        <!-- Slot de ações padrão -->
        <template #body-cell-actions="props">
            <q-td :props="props" class="tw-text-right">
                <Link :href="route(routes.edit, props.row.id)">
                    <q-btn dense flat size="sm" icon="edit" class="qtable-edit-btn" />
                </Link>
                <q-btn dense flat size="sm" icon="delete" class="qtable-delete-btn" @click="destroyOne(props.row.id)" />
            </q-td>
        </template>
    </IndexTable>
</template>
