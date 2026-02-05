<script setup>
import { Head, router } from '@inertiajs/vue3'
import FormTask from './FormTask.vue'

function submit(values) {
    router.post(route('panel.tasks.store'), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <!--<Head title="Cadastrar tarefa" />-->
    <div class="tw-space-y-4">
        <FormTask :mode="'create'" @submit="submit" />
    </div>
</template>
