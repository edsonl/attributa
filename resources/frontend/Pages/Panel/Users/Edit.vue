<script setup>
import { Head, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import FormUser from './FormUser.vue'

const page = usePage()
const user = computed(() => page.props.user)
const notificationOptions = computed(() => page.props.notification_options || [])
const notificationPreferences = computed(() => page.props.notification_preferences || [])

function submit(values) {
    router.put(route('panel.users.update', user.value.id), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Editar usuário" />
    <div class="tw-space-y-4">
        <FormUser
            :mode="'edit'"
            :user="user"
            :notification-options="notificationOptions"
            :notification-preferences="notificationPreferences"
            @submit="submit"
        />
    </div>
</template>
