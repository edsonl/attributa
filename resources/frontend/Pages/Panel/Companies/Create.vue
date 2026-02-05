<script setup>
import { router } from '@inertiajs/vue3'
import FormCompany from './FormCompany.vue'

function submit(values) {
    router.post(route('panel.companies.store'), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <!-- container centralizado -->
    <div class="tw-flex tw-justify-center tw-py-6">
        <div class="tw-w-full md:tw-w-[760px]">
            <FormCompany :mode="'create'" @submit="submit" />
        </div>
    </div>
</template>
