<template>
    <div class="tw-h-screen tw-bg-gradient-to-br tw-from-brand-primary/15 tw-to-brand-accent/15 tw-flex tw-items-center tw-justify-center tw-p-4">
        <q-card
            flat bordered
            class="tw-rounded-2xl tw-shadow-xl tw-border-white/60 dark:tw-border-white/10 q-card-login"
        >
            <q-card-section class="tw-text-center">
                <div class="tw-inline-flex tw-items-center tw-justify-center tw-w-14 tw-h-14 tw-rounded-2xl tw-bg-gradient-to-br tw-from-brand-primary tw-to-brand-accent tw-text-white tw-shadow">
                    <q-icon name="lock" size="32px" />
                </div>
                <div class="tw-mt-3 tw-text-xl tw-font-bold">
                    <slot name="title">Autenticação</slot>
                </div>
                <div class="tw-text-slate-600 dark:tw-text-slate-300">
                    <slot name="subtitle"></slot>
                </div>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <slot />
            </q-card-section>

        </q-card>
        <FlashToaster />
    </div>
</template>

<script setup>
  import FlashToaster from '@/Components/Shared/FlashToaster.vue'
</script>
<style scoped>
  .q-card-login{
      width: 500px;
      max-width: 90%;
  }
</style>
