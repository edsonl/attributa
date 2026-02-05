<template>
    <div class="tw-space-y-4">
        <ClientForm
            mode="create"
            :endpoint="route('panel.clients.store')"
            :defaults="defaults"
            :errors="$page.props.errors || {}"
        />
    </div>
</template>

<script setup>
    import ClientForm from './ClientForm.vue'
    const props = defineProps({ defaults: Object })
</script>
