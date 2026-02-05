<template>
    <div class="tw-p-4 tw-bg-slate-100">
        <q-btn label="Quasar + Tailwind" class="tw-ml-5 text-warning" />
        <div class="tw-bg-brand-primary/10 tw-text-brand-primary tw-p-4 tw-rounded-lg">
            Box Tailwind
        </div>

        <q-btn class="tw-bg-brand-accent tw-text-white tw-ml-2" label="Accent (tw-)" />

        <div class="tw-bg-gradient-to-r tw-from-brand-primary tw-to-brand-accent tw-text-white tw-p-4 tw-rounded-xl">
            teste
       </div>
    </div>
</template>
<script setup>
 import MainLayout from "@/Layouts/MainLayout.vue";
 defineOptions({ layout: MainLayout })
</script>
