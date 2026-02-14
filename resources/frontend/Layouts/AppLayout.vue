<template>
    <Head :title="metaTitleLocal"></Head>
    <q-layout view="hHh lpR fFf">
        <!-- Header -->
        <q-header elevated class="text-white bg-primary">
            <q-toolbar style="padding: 0;">
                <div class="left-content-toolbar tw-inline-flex tw-h-full tw-items-stretch tw-bg-brand-primary tw-text-white toolbar-height"
                     :style="{ 'width': MIN_WIDTH_BRAND + 'px'}"
                >
                    <div class="tw-inline-flex tw-place-items-center tw-justify-center"
                         :style="{ 'width': MINI_COL + 'px' }">
                        <Link :href="route('dashboard')" class="tw-no-underline">
                            <svg height="32px" width="32px" version="1.1" id="Layer_1"
                                 xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                 viewBox="0 0 291.32 291.32" xml:space="preserve" fill="#ffffff" stroke="#ffffff">
                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                <g id="SVGRepo_iconCarrier"> <g><path style="fill:#ffffff;" d="M145.66,0C65.21,0,0,65.21,0,145.66c0,80.431,65.21,145.66,145.66,145.66 s145.66-65.219,145.66-145.66S226.109,0,145.66,0z M145.66,264.008c-65.365,0-118.348-52.984-118.348-118.348 S80.295,27.311,145.66,27.311S264.008,80.295,264.008,145.66S211.024,264.008,145.66,264.008z M144.986,104.711 c1.029-1.839,0.41-4.188-1.393-5.253l-9.868-5.726c-1.803-1.056-4.142-0.419-5.171,1.411l-15.121,26.692l-16.669,29.396 l-13.91,24.544c-1.038,1.83-0.41,4.188,1.393,5.226l9.868,5.744c1.803,1.056,4.133,0.419,5.18-1.402l19.327-34.112l12.49-22.04 C131.112,129.191,144.986,104.711,144.986,104.711z M137.138,136.592l-15.176,27.366h53.566l-13.656-27.366 C161.873,136.592,137.138,136.592,137.138,136.592z M93.477,136.592H72.83c-5.025,0-9.104,4.088-9.104,9.122v9.131 c0,5.034,4.078,9.113,9.104,9.113h4.261C77.09,163.958,93.477,136.592,93.477,136.592z M90.873,191.151l-9.859-5.735 c-1.803-1.047-3.469-0.182-3.714,1.903l-1.711,15.039c-0.237,2.094,0.974,2.849,2.704,1.666l12.736-8.794 C92.758,194.037,92.694,192.198,90.873,191.151z M198.598,151.604l-0.2-0.373l-6.227-11.99l-0.118-0.209l-0.701-1.366l-0.528-0.992 l-0.446-0.874l-0.819-1.566l-0.255-0.482l-0.929-1.793l-0.273-0.528c-6.955-13.301-15.686-29.696-21.066-38.308 c-9.422-15.103-22.013-40.257-25.527-38.481c-4.925,2.485,10.315,34.43,15.358,46.438c5.043,11.999,24.726,58.009,28.668,60.012 c3.942,2.003,5.48,0.892,8.111-0.209C196.294,159.761,201.01,156.074,198.598,151.604z M218.489,136.592h-19.4l15.613,27.366h3.787 c5.034,0,9.104-4.078,9.104-9.113v-9.131C227.593,140.68,223.524,136.592,218.489,136.592z M209.85,175.319l-4.698-9.14 c-1.065-2.067-3.569-2.913-5.562-1.875l-5.663,2.95c-1.994,1.038-2.622,3.496-1.393,5.471l5.945,9.513 c1.229,1.966,3.814,2.613,5.744,1.42l4.042-2.458C210.196,180.035,210.906,177.377,209.85,175.319z M208.402,188.529 c-1.475,1.447-4.989,4.561-0.737,10.624c4.242,6.063,13.728,6.336,14.985,10.36C222.641,209.513,225.617,178.706,208.402,188.529z"></path> </g> </g>
                           </svg>
                        </Link>
                    </div>
                    <div class="tw-inline-flex tw-place-items-center tw-justify-start tw-ms-2" v-if="!drawerMini && isDesktop">
                        <Link :href="route('dashboard')" class="tw-no-underline">
                          <q-toolbar-title>{{ appName }}</q-toolbar-title>
                        </Link>
                    </div>
                </div>
                <div class="tw-inline-flex tw-ms-4">
                    <!-- Desktop: alterna mini | Mobile: abre/fecha overlay -->
                    <q-btn dense flat round icon="menu" @click="menuClick" />
                </div>
                <div class="tw-inline-flex tw-ms-4 tw-pt-2 tw-pb-2 toolbar-height">
                    <q-separator vertical/>
                </div>
                <q-toolbar-title
                    class="tw-text-white tw-font-normal tw-tracking-tight
                             tw-text-lg lg:tw-text-lg
                             tw-whitespace-nowrap tw-overflow-hidden tw-text-ellipsis">
                    {{ sectionTitleLocal }}
                </q-toolbar-title>

                <q-space />

                <!-- 1) Notificações -->
                <div class="tw-rounded-full tw-p-1 tw-bg-white/10 hover:tw-bg-white/20 tw-transition tw-me-3">
                    <q-btn  flat round dense :ripple="false"
                            class="toolbar-icon tw-text-white"
                            icon="notifications" @click="onNotificationsClick">
                            <!-- badge “flutuante” no canto do ícone -->
                            <q-badge v-if="notifyCount > 0" color="negative" floating>{{ notifyCount }}</q-badge>
                    </q-btn>
                </div>
                <!-- 2) Avatar + Menu do usuário -->
                <div class="tw-rounded-full tw-p-1 tw-bg-white/10 hover:tw-bg-white/20 tw-transition tw-me-3">
                <q-btn flat round dense :ripple="false"
                       class="toolbar-icon tw-text-white"
                       no-caps>
                    <q-avatar size="32px">
                        <!-- foto, inicial ou ícone default -->
                        <img v-if="userPhoto" :src="userPhoto" :alt="userName" />
                        <template v-else-if="userInitials">{{ userInitials }}</template>
                        <q-icon v-else name="person" />
                    </q-avatar>
                    <q-menu
                        anchor="bottom right"
                        self="top right"
                        :offset="[0, 8]"
                        transition-show="jump-down"
                        transition-hide="jump-up"
                    >
                        <q-list class="tw-min-w-[200px] tw-py-1 tw-rounded-lg tw-shadow-lg tw-bg-white dark:tw-bg-gray-900">
                            <!-- Editar conta -->
                            <q-item
                                clickable v-ripple tag="a" :href="route('account.edit')"
                                class="group tw-mx-1 tw-rounded-md tw-no-underline tw-cursor-pointer
                                 tw-text-slate-700 dark:tw-text-slate-200
                                 hover:tw-bg-brand-primary/10 focus:tw-bg-brand-primary/10
                                 tw-outline-none focus-visible:tw-ring-2 focus-visible:tw-ring-brand-primary/40
                                 tw-transition-colors tw-duration-150"
                            >
                                <q-item-section avatar>
                                    <q-icon
                                        name="manage_accounts"
                                        class="tw-text-slate-500 dark:tw-text-slate-300 group-hover:tw-text-brand-primary"
                                    />
                                </q-item-section>
                                <q-item-section class="group-hover:tw-text-brand-primary">
                                    Editar conta
                                </q-item-section>
                            </q-item>

                            <q-separator spaced />

                            <!-- Sair -->
                            <q-item
                                clickable v-ripple tag="a" :href="route('auth.logout')"
                                class="group tw-mx-1 tw-rounded-md tw-no-underline tw-cursor-pointer
                                 tw-text-slate-700 dark:tw-text-slate-200
                                 hover:tw-bg-brand-primary/10 focus:tw-bg-brand-primary/10
                                 tw-outline-none focus-visible:tw-ring-2 focus-visible:tw-ring-brand-primary/40
                                 tw-transition-colors tw-duration-150"
                            >
                                <q-item-section avatar>
                                    <q-icon
                                        name="logout"
                                        class="tw-text-slate-500 dark:tw-text-slate-300 group-hover:tw-text-brand-primary"
                                    />
                                </q-item-section>
                                <q-item-section class="group-hover:tw-text-brand-primary">
                                    Sair
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </q-menu>
                </q-btn>
                </div>
                <!-- Botão explícito de mini/expand (só desktop) -->
                <div class="tw-me-4">
                    <q-btn
                        v-if="isDesktop"
                        dense flat round
                        :icon="drawerMini ? 'chevron_right' : 'chevron_left'"
                        :title="drawerMini ? 'Expandir menu' : 'Minimizar menu'"
                        @click="toggleMini"
                    />
                    <q-tooltip anchor="center left" self="center right" :offset="[10, 10]">
                        {{ drawerMini ? 'Expandir menu' : 'Minimizar menu' }}
                    </q-tooltip>
                </div>
            </q-toolbar>
        </q-header>
        <!--
         <q-drawer
            v-model="leftDrawerOpen"
            :behavior="isDesktop ? 'desktop' : 'mobile'"  desktop empurra | mobile overlay
        :mini="drawerMini"                              mini = só ícones (apenas desktop)
        :width="240"
        :mini-width="MINI_COL"
        :breakpoint="992"                             992px vira 'mobile'
        show-if-above                                  aberto por padrão no desktop
        bordered
        class="app-drawer"
        :style="{ '--mini-col': MINI_COL + 'px' }"
        >
        -->
        <!-- Drawer (bg primário + texto claro) -->
        <!--<q-drawer
            v-model="leftDrawerOpen"
            :behavior="isDesktop ? 'desktop' : 'mobile'"
        :mini="drawerMini"
        :width="240"
        :mini-width="MINI_COL"
        :breakpoint="992"
        show-if-above
        bordered
        class="app-drawer tw-bg-brand-primary tw-bg-opacity-90 text-white"
        :style="{ '--mini-col': MINI_COL + 'px' }"
        >
        <q-list padding>
            <q-item
                v-for="l in links" :key="l.route"
                clickable v-ripple
                class="group tw-mx-1 tw-rounded-sm tw-no-underline tw-cursor-pointer tw-transition-colors tw-duration-75"
                :class="route().current(l.route)
                ? 'tw-bg-white/15 tw-text-white'
                : 'tw-text-white/80 hover:tw-bg-white/10 hover:tw-text-white'"
                @click="go(l.route)"
            >
                <q-item-section avatar>
                    <q-icon :name="l.icon"
                            :class="route().current(l.route)
                ? 'tw-text-white'
                : 'tw-text-white/80 group-hover:tw-text-white'"/>
                </q-item-section>
                <q-item-section v-show="!drawerMini">{{ l.label }}</q-item-section>
            </q-item>
        </q-list>
        </q-drawer>-->
        <AppDrawer
            v-model="leftDrawerOpen"
            :drawerMini="drawerMini"
            :miniCol="MINI_COL"
            :isDesktop="isDesktop"
            :menuItems="links"
            @select="handleSelect"
        />

        <!-- Conteúdo -->
        <q-page-container>
            <FlashToaster />
            <q-page :class="['q-pa-md', { 'app-page-gradient': gradientPage }]">
                <slot />
            </q-page>
        </q-page-container>

    </q-layout>
</template>

<script setup>
import { ref, computed, watch, onMounted, provide } from 'vue'
import { useQuasar, LocalStorage } from 'quasar'
import {Head, Link, router, usePage} from '@inertiajs/vue3'

import AppDrawer from '@/Components/AppDrawer.vue'
import FlashToaster from '@/Components/Shared/FlashToaster.vue'

//Props compartilhadas pelo Inertia
const page = usePage()
const pageTitle = computed(() => page.props?.title ?? '')
const appName   = computed(() => page.props?.app?.name ?? 'Laravel App')

//Props passadas para o Layout
const props = defineProps({
    gradientPage: { type: Boolean, default: true },
    sectionTitle: { type: String, default:'' },
    metaTitle: { type: String, default:'' },
})
// Computa o título final com fallback
const sectionTitleLocal = computed(() => {
    // Se a página passar o valor, usa ele
    if (props.sectionTitle) return props.sectionTitle
    // Caso contrário, tenta pegar do Inertia page.props
    return page.props?.title ?? '';
})
// Computa o título(meta-title) final com fallback
const metaTitleLocal = computed(() => {
    if (props.metaTitle) return props.metaTitle
    return page.props?.title ?? '';
})

const $q = useQuasar()
const isDesktop = computed(() => !$q.screen.lt.lg)

// Drawer: aberto em desktop, fechado em mobile
const leftDrawerOpen = ref(isDesktop.value)
watch(isDesktop, (desk) => { leftDrawerOpen.value = desk })

// Persistência do mini por usuário (padrão expandido)
const uid = page.props?.auth?.user?.id ?? 'guest'
const key = `ui.drawer-mini.${uid}`
const miniWanted = ref(false)
onMounted(() => {
    const saved = LocalStorage.getItem(key)
    if (typeof saved === 'boolean') miniWanted.value = saved
})
watch(miniWanted, v => LocalStorage.set(key, v))


// Mini só funciona no desktop
const drawerMini = computed(() => isDesktop.value ? miniWanted.value : false)

// Largura da coluna do ícone (e do mini)
const MINI_COL = 65;

// Determina o tamanho do content left-toolbar (Local onde mostra o logo e o nome do App)
// No mobile fica somente o ícone - no desktop quando espandido mostra também o nome do app
const MIN_WIDTH_BRAND = computed(() =>{
      if(!drawerMini.value && isDesktop.value){
         return 240;
      }
     if(drawerMini.value && isDesktop.value){
        return MINI_COL;
     }
     return MINI_COL;
})


// Botões
function menuClick () {
    if (isDesktop.value) toggleMini()               // desktop: mini/expand
    else leftDrawerOpen.value = !leftDrawerOpen.value // mobile: overlay abre/fecha
}
function toggleMini () { if (isDesktop.value) miniWanted.value = !miniWanted.value }

// Navegação
function go (name) { router.visit(route(name)) }

// Links
const links = [
    { label: 'Atividade', icon: 'insights', route: 'panel.atividade.pageviews'},
    { label: 'Conversões', icon: 'paid', route: 'panel.conversoes.index'},
    { label: 'Campanhas',  icon: 'ads_click',  route: 'panel.campaigns.index' ,svg:''},
    { label: 'Metas de conversao',  icon: 'flag',  route: 'panel.conversion-goals.index' ,svg:''},
    { label: 'Usuários', icon: 'people_alt', route: 'panel.users.index'},
    { label: 'Contas de anúncio', icon: 'insights', route: 'panel.ads-accounts.index'},
    { label: 'Países', icon: 'public', route: 'panel.countries.index'},
]

// Usuário (foto/nome/iniciais)
const userName = computed(() => page.props?.auth?.user?.name ?? '')
const userPhoto = computed(() => page.props?.auth?.user?.profile_photo_url ?? '')
const userInitials = computed(() => page.props.auth.user?.initials ?? '')

// Ao clicar em item do menu
function handleSelect(item) {
    //console.log('Item clicado:', item)
    // Exemplo: navegar (se usar Ziggy ou Vue Router)
    router.visit(route(item.route))
}

// Notificações (fictício por enquanto)
const notifyCount = ref(7)
function onNotificationsClick () {
    // coloque aqui sua lógica (abrir página de notificações, dialog, etc.)
    // por enquanto só zera como exemplo:
    // notifyCount.value = 0
}



</script>
<style scoped>


/* zera recuos para usarmos a coluna fixa do avatar */
.app-drawer :deep(.q-list) { padding: 0; }
.app-drawer :deep(.q-item) { padding-left: 0; }


/* coluna do avatar/ícone SEMPRE com a mesma largura do mini */
.app-drawer :deep(.q-item__section--avatar) {
    min-width: var(--mini-col);
    width: var(--mini-col);
    display: flex;
    justify-content: center;
}

/* texto não invade a coluna do ícone */
.app-drawer :deep(.q-item__section:not(.q-item__section--avatar)) {
    min-width: 0;
}
.toolbar-height{
    height: 50px;
}

/* remove o overlay de hover/focus do QBtn (que causava o “halo”) */
/*
.toolbar-icon :deep(.q-focus-helper) {
    opacity: 0 !important;
}
.toolbar-icon:hover :deep(.q-focus-helper) {
    opacity: 0 !important;
}
*/
</style>
