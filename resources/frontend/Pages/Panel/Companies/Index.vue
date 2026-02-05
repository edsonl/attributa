<script setup>
import IndexTable from '@/Components/Shared/Scaffold/IndexTable.vue'

const columns = [
    { name: 'id',             label: 'ID',            field: 'id',             align: 'left',  sortable: true },
    { name: 'name',           label: 'Nome',          field: 'name',           align: 'left',  sortable: true },
    { name: 'cnpj',           label: 'CNPJ',          field: 'cnpj',           align: 'left',  sortable: true },
    { name: 'corporate_name', label: 'Razão social',  field: 'corporate_name', align: 'left',  sortable: true },
    { name: 'email',          label: 'E-mail',        field: 'email',          align: 'left',  sortable: true },
    { name: 'created_at',     label: 'Criado em',     field: 'created_at',     align: 'left',  sortable: true },
    { name: 'actions',        label: 'Ações',         field: 'actions',        align: 'right' },
]

const routes = {
    index:   'panel.companies.index',
    create:  'panel.companies.create',
    edit:    'panel.companies.edit',
    destroy: 'panel.companies.destroy',
    // bulkDestroy: 'panel.companies.bulk-destroy' // se criar depois
}
</script>

<template>
    <IndexTable
        title="Empresas"
        collectionKey="companies"
        :columns="columns"
        :routes="routes"
        :labels="{
          create: 'Nova empresa',
          deleteOne: 'Excluir empresa!',
          bulkDeleteSingular: 'Excluir empresa',
          bulkDeletePlural: 'Excluir empresas selecionadas',
          searchPlaceholder: 'Pesquisar por nome, razão social, e-mail, telefone...'
       }"
        :rowsPerPageOptions="[10, 20, 50, 100]"
        :enable-selection="false"
    />
</template>
