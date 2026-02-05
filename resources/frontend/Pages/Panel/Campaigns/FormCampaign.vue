<script setup>
import { computed, ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Notify } from 'quasar'

const props = defineProps({
    campaign: {
        type: Object,
        default: null,
    },
    channels: {
        type: Array,
        required: true,
    },
    countries: {
        type: Array,
        required: true,
    },
})

const isEdit = computed(() => !!props.campaign)

const form = useForm({
    name: props.campaign?.name ?? '',
    status: props.campaign?.status ?? true,
    channel_id: props.campaign?.channel_id ?? null,
    countries: props.campaign?.countries
        ? props.campaign.countries.map(c => c.id)
        : [],
    commission_value: props.campaign?.commission_value ?? null,
})

function submit() {
    if (isEdit.value) {
        form.put(route('panel.campaigns.update', props.campaign.id))
    } else {
        form.post(route('panel.campaigns.store'))
    }
}

/**
 * Copia o código da campanha (com fallback para HTTP)
 */
function copyCampaignCode() {
    if (!props.campaign?.code) return

    const text = props.campaign.code

    // ✅ Método moderno (HTTPS / localhost)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            Notify.create({
                type: 'positive',
                message: 'Código copiado',
                timeout: 2000,
                position: 'top-right',
            })
        }).catch(fallbackCopy)
    } else {
        // ⚠️ Fallback para HTTP
        fallbackCopy()
    }

    function fallbackCopy() {
        try {
            const input = document.createElement('input')
            input.value = text
            document.body.appendChild(input)
            input.select()
            document.execCommand('copy')
            document.body.removeChild(input)

            Notify.create({
                type: 'positive',
                message: 'Código copiado',
                timeout: 2000,
                position: 'top-right',
            })
        } catch (e) {
            Notify.create({
                type: 'negative',
                message: 'Não foi possível copiar o código',
                timeout: 3000,
                position: 'top-right',
            })
        }
    }
}

    const filteredCountries = ref([...props.countries])

    function filterCountries(val, update) {
        if (val === '') {
            update(() => {
                filteredCountries.value = props.countries
            })
            return
        }
        const needle = val.toLowerCase()
        update(() => {
            filteredCountries.value = props.countries.filter(country => {
                return (
                    country.nome?.toLowerCase().includes(needle) ||
                    country.iso2?.toLowerCase().includes(needle) ||
                    country.iso3?.toLowerCase().includes(needle) ||
                    country.currency?.toLowerCase().includes(needle)
                )
            })
        })
    }
</script>


<template>
    <form @submit.prevent="submit" class="tw-space-y-6">
        <!-- Nome -->
        <q-input
            v-model="form.name"
            label="Nome da campanha"
            outlined
            dense
            :error="!!form.errors.name"
            :error-message="form.errors.name"
        />

        <!-- Canal -->
        <q-select
            v-model="form.channel_id"
            :options="channels"
            option-label="name"
            option-value="id"
            emit-value
            map-options
            label="Canal"
            outlined
            dense
            :error="!!form.errors.channel_id"
            :error-message="form.errors.channel_id"
        />

        <!-- Países -->
        <q-select
            v-model="form.countries"
            :options="filteredCountries"
            option-label="nome"
            option-value="id"
            emit-value
            map-options
            multiple
            use-chips
            use-input
            input-debounce="300"
            @filter="filterCountries"
            label="Países"
            outlined
            dense
        />

        <q-input
            v-model="form.commission_value"
            label="Valor recebido (BRL)"
            outlined
            dense
            prefix="R$"
            mask="#.##"
            fill-mask="0"
            reverse-fill-mask
            input-class="tw-text-right"
        />

        <!-- Código da Campanha (somente leitura) -->
        <q-input
            v-if="campaign"
            :model-value="campaign.code"
            label="Código da Campanha"
            outlined
            readonly
        >
            <template #append>
                <q-btn
                    flat
                    dense
                    icon="content_copy"
                    @click="copyCampaignCode"
                />
            </template>
        </q-input>

        <!-- Status -->
        <q-toggle
            v-model="form.status"
            label="Campanha ativa"
        />

        <!-- Ações -->
        <div class="tw-flex tw-justify-end tw-gap-2">
            <q-btn
                flat
                label="Cancelar"
                @click="router.visit(route('panel.campaigns.index'))"
            />

            <q-btn
                color="primary"
                :label="isEdit ? 'Atualizar' : 'Criar'"
                type="submit"
                :loading="form.processing"
                unelevated
            />
        </div>
    </form>
</template>
