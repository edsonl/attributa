<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import FormCompany from './FormCompany.vue'

const page = usePage()
const company = computed(() => page.props.company)

function submit(values) {
    router.put(route('panel.companies.update', company.value.id), values, {
        preserveScroll: true,
    })
}
</script>

<template>
    <!-- container centralizado -->
    <div class="tw-flex tw-justify-center tw-py-6">
        <div class="tw-w-full md:tw-w-[760px]">
            <FormCompany :mode="'edit'" :company="company" @submit="submit" />
        </div>
    </div>
</template>
