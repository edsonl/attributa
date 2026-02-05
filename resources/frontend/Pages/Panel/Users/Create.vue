<script setup>
import { Head, router } from '@inertiajs/vue3'
import FormUser from './FormUser.vue'

function submit(values) {
    router.post(route('panel.users.store'), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <!--<Head title="Cadastrar usuário" />-->
    <div class="tw-space-y-4">
        <FormUser :mode="'create'" @submit="submit" />
    </div>
</template>
