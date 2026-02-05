<template>
    <q-drawer
        v-model="modelValue"
        :behavior="isDesktop ? 'desktop' : 'mobile'"
        :mini="drawerMini"
        :width="240"
        :mini-width="miniCol"
        :breakpoint="992"
        show-if-above
        bordered
        class="app-drawer tw-bg-brand-primary tw-bg-opacity-90 text-white"
        :style="{ '--mini-col': miniCol + 'px' }"
    >
        <!-- Lista de itens do menu -->
        <q-list>
        <q-item
            v-for="item in menuItems"
            :key="item.label"
            clickable
            v-ripple
            @click="onItemClick(item)"
            :class="[
                     'tw-transition-colors tw-duration-300 hover:tw-bg-white/10',
                     isActive(item) ? 'tw-bg-white/20' : ''
                  ]"
        >
            <q-item-section avatar>
                <template v-if="item.svg">
                    <div
                        class="tw-w-5 tw-h-5 tw-transition-colors tw-duration-300"
                        :class="isActive(item) ? '' : 'tw-text-white'"
                        v-html="item.svg"
                    />
                </template>
                <template v-else>
                    <q-icon
                        :name="item.icon"
                        size="sm"
                        class="tw-transition-colors tw-duration-300"
                        :class="isActive(item) ? '' : 'tw-text-white'"
                    />
                </template>
            </q-item-section>
            <q-item-section
                v-if="!drawerMini"
                class="tw-font-medium tw-transition-colors tw-duration-300"
                :class="isActive(item) ? '' : 'tw-text-white'"
            >
                {{ item.label }}
            </q-item-section>
         </q-item>
        </q-list>
        <!-- Slot opcional para conteúdo extra -->
        <slot />
    </q-drawer>
</template>

<script setup>
import { computed,watch } from 'vue'
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    modelValue: { type: Boolean, required: true },  // v-model
    drawerMini: { type: Boolean, default: false },
    miniCol: { type: Number, default: 60 },
    isDesktop: { type: Boolean, default: true },
    menuItems: { type: Array, default: () => [] }   // ✅ recebe os itens do pai
})

const emit = defineEmits(['update:modelValue', 'select']) // emite evento ao clicar

const modelValue = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

// Emite o item selecionado para o pai
function onItemClick(item) {
    emit('select', item)
}

//Obter as 2 primeiras partes da string delimitada por ponto
function getFirstTwoParts(text) {
    const parts = text.split('.');
    return parts.length >= 2 ? `${parts[0]}.${parts[1]}` : text;
}
//console.log(getFirstTwoParts('panel.clients.index')); // "panel.clients"

// Página atual reativa
const page = usePage()
const currentRoute = computed(() => page.props.route?.name ?? '')
const isActive = (item) => {
    // ativa se for a mesma rota OU qualquer sub-rota
    return currentRoute.value === item.route ||
        currentRoute.value.startsWith(getFirstTwoParts(item.route) + '.')
}

</script>
