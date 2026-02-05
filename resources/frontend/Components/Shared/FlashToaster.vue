<script setup>
import { watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Notify } from 'quasar'

const page = usePage()

function fireToastBag(flash) {
    if (!flash) return

    const bag = Array.isArray(flash) ? flash : [flash] // permite array ou string simples
    for (const msg of bag) {
        if (!msg) continue
        // map simples de cor por tipo
    }
}

function notify(type, message) {
    const map = {
        success: 'positive',
        info: 'info',
        warning: 'warning',
        error: 'negative'
    }
    Notify.create({
        type: map[type] ?? 'info',
        message
    })
}

// dispara assim que montar (primeira página) e também em mudanças de props
onMounted(() => {
    const f = page.props.flash
    if (f?.success) notify('success', f.success)
    if (f?.info)    notify('info',    f.info)
    if (f?.warning) notify('warning', f.warning)
    if (f?.error)   notify('error',   f.error)
})

// observa mudanças no flash após cada navegação Inertia
watch(
    () => page.props.flash,
    (f) => {
        if (f?.success) notify('success', f.success)
        if (f?.info)    notify('info',    f.info)
        if (f?.warning) notify('warning', f.warning)
        if (f?.error)   notify('error',   f.error)
    },
    { deep: true }
)
</script>

<template>
    <!-- componente “fantasma” (sem UI) -->
    <div style="display:none;"></div>
</template>
<!--
Uso manual (em qualquer página/componente)
Se você quiser acionar um toast manualmente no frontend:

import { Notify } from 'quasar'
Notify.create({ type: 'positive', message: 'Operação concluída!' })
Notify.create({ type: 'info',     message: 'Apenas uma dica.' })
Notify.create({ type: 'warning',  message: 'Atenção aos campos.' })
Notify.create({ type: 'negative', message: 'Ocorreu um erro.' })
-->
