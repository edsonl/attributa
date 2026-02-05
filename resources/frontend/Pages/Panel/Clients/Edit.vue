<template>
    <div class="tw-space-y-4">
        <ClientForm
            mode="edit"
            :endpoint="route('panel.clients.update', client.id)"
            :defaults="{ image_max_width: image_meta.max_width, image_max_height: image_meta.max_height }"
            :entity="client"
            :errors="$page.props.errors || {}"
        />
    </div>
</template>
<script setup>
    import ClientForm from './ClientForm.vue'
    const props = defineProps({ client: Object, image_meta: Object })
</script>
