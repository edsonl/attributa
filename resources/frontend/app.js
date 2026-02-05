
import { createApp, h } from 'vue'
import { router } from '@inertiajs/vue3'
import { createInertiaApp, Head, Link } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'
import { Quasar, Dialog, Notify } from 'quasar'
import { InertiaProgress  } from '@inertiajs/progress'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmPlugin from '@/Plugins/confirm'
import axios from 'axios'


// Configuração axios
axios.defaults.withCredentials = true
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN'
axios.defaults.xsrfCookieName = 'XSRF-TOKEN'

// Torna acessível globalmente
window.axios = axios


// inicia a barra de progresso
InertiaProgress.init({ color: '#9618a6', showSpinner: false })  // 👈 em vez de NProgress.init

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'))
            .then((module) => {
               // const page = mod.default
               // page.layout ??= AppLayout
               // return page
                const page = module.default
                // define layout padrão e repassa props opcionais do componente
                page.layout = page.layout || ((h, pageInstance) =>
                        h(AppLayout, page.layoutProps || {}, () => pageInstance)
                )
                return module;
            }),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
        app.use(plugin)

        // 1) Quasar primeiro
        app.use(Quasar, {
            plugins: { Dialog, Notify },
            config: {
                notify: {
                    position: 'bottom',
                    timeout: 2500, // 0 = não fecha
                    html: false,
                    actions: [{ icon: 'close', round: true, dense: true }]
                }
            }
        })
        // 2) Depois o plugin de confirmação (vai renderizar o QDialog já com Quasar ativo)
        app.use(ConfirmPlugin)

        app.use(ZiggyVue,window.Ziggy)
        app.component('Head', Head)
        app.component('Link', Link)
        app.mount(el)
    },
})

// força atualização do header CSRF após qualquer resposta do Laravel
router.on('success', () => {
    const token = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
    if (token) {
        axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(token)
    }
})

