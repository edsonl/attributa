import ConfirmDialog from '@/Components/Shared/ConfirmDialog.vue'
import { createVNode, render, nextTick } from 'vue'

export default {
    install(app) {
        let vnode = null
        let mounted = false

        function mountOnce () {
            if (mounted) return

            const id = 'confirm-dialog-root'
            let el = (typeof document !== 'undefined') ? document.getElementById(id) : null
            if (!el && typeof document !== 'undefined') {
                el = document.createElement('div')
                el.id = id
                document.body.appendChild(el)
            }

            // cria o vnode e injeta o contexto da app (inclui Quasar, i18n, etc.)
            vnode = createVNode(ConfirmDialog)
            vnode.appContext = app._context

            render(vnode, el)
            mounted = true
        }

        async function confirm (options = {}) {
            // monta on-demand
            if (!mounted) {
                mountOnce()
                await nextTick()
            }

            // pega a API exposta (defineExpose) — open()
            let api = vnode?.component?.exposed
            if (!api) {
                await nextTick()
                api = vnode?.component?.exposed
            }

            if (!api || typeof api.open !== 'function') {
                throw new Error('ConfirmDialog não inicializado (open ausente).')
            }

            return api.open(options)
        }

        // Disponibiliza em 3 vias
        app.config.globalProperties.$confirm = confirm       // this.$confirm (Options API / template)
        app.provide('confirmDialog', confirm)                // inject('confirmDialog') via composable

        // Global no browser (sem SSR)
        if (typeof window !== 'undefined') {
            window.$confirm = confirm                          // chamar direto: await $confirm({...})
        }
    }
}
