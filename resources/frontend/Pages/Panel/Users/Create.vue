<script setup>
import { Head, router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import FormUser from './FormUser.vue'

const page = usePage()

function submit(values) {
    router.post(route('panel.users.store'), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <!--<Head title="Cadastrar usuário" />-->
    <div class="tw-space-y-4">
        <FormUser
            :mode="'create'"
            :notification-options="page.props.notification_options || []"
            :notification-preferences="page.props.notification_preferences || []"
            @submit="submit"
        />
    </div>
</template>
