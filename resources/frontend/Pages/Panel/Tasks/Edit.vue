<script setup>
import { Head, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import FormTask from './FormTask.vue'

const page = usePage()
const task = computed(() => page.props.task)

function submit(values) {
    router.put(route('panel.tasks.update', task.value.id), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Editar tarefa" />
    <div class="tw-space-y-4">
        <FormTask :mode="'edit'" :task="task" @submit="submit" />
    </div>
</template>
