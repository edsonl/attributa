<script setup>
import { ref, watch, computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    mode: { type: String, default: 'create' },
    company: {
        type: Object,
        default: () => ({
            id: null,
            name: '',
            corporate_name: '',
            cnpj: '',
            phone: '',
            whatsapp: '',
            email: '',
            site: '',
            notes: '',
        }),
    },
})

const emit = defineEmits(['submit'])

const form = ref({
    name: '',
    corporate_name: '',
    cnpj: '',
    phone: '',
    whatsapp: '',
    email: '',
    site: '',
    notes: '',
})

const isEdit = computed(() => props.mode === 'edit')

// ===== Helpers =====
const onlyDigits = (v) => (v || '').replace(/\D+/g, '')

// ----- TELEFONES -----
function isValidPhoneBR(digits) {
    // Aceita DDD + 8 ou 9 dígitos
    return [10, 11, 8, 9].includes(digits.length)
}

function formatPhoneBR(digits) {
    if (!digits) return ''
    digits = onlyDigits(digits)
    if (digits.length === 11)
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
    if (digits.length === 10)
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
    if (digits.length === 9)
        return `${digits.slice(0, 5)}-${digits.slice(5)}`
    if (digits.length === 8)
        return `${digits.slice(0, 4)}-${digits.slice(4)}`
    return ''
}

function normalizeAndFormatPhone() {
    const d = onlyDigits(form.value.phone)
    form.value.phone = isValidPhoneBR(d) ? formatPhoneBR(d) : null
}
function normalizeAndFormatWhatsapp() {
    const d = onlyDigits(form.value.whatsapp)
    form.value.whatsapp = isValidPhoneBR(d) ? formatPhoneBR(d) : null
}

// máscaras reativas e dinâmicas
const phoneMask = ref('')
const whatsappMask = ref('')

function onPhoneInput(field) {
    const digits = onlyDigits(form.value[field])
    let mask = ''
    if (digits.length >= 11) mask = '(##) #####-####'
    else if (digits.length === 10) mask = '(##) ####-####'
    else if (digits.length === 9) mask = '#####-####'
    else if (digits.length === 8) mask = '####-####'
    else mask = ''
    if (field === 'phone') phoneMask.value = mask
    else whatsappMask.value = mask
}

// ----- CNPJ -----
function isValidCNPJ(digits) {
    if (!digits || digits.length !== 14) return false
    if (/^(\d)\1{13}$/.test(digits)) return false
    const calcDV = (base) => {
        let size = base.length
        let pos = size - 7
        let sum = 0
        for (let i = size; i >= 1; i--) {
            sum += parseInt(base[size - i], 10) * pos--
            if (pos < 2) pos = 9
        }
        const result = 11 - (sum % 11)
        return result > 9 ? 0 : result
    }
    const dv1 = calcDV(digits.substring(0, 12))
    const dv2 = calcDV(digits.substring(0, 12) + dv1.toString())
    return digits.endsWith(`${dv1}${dv2}`)
}

function formatCNPJ(digits) {
    if (digits.length !== 14) return ''
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`
}

function normalizeAndFormatCNPJ() {
    const d = onlyDigits(form.value.cnpj)
    form.value.cnpj = isValidCNPJ(d) ? formatCNPJ(d) : null
}

// ----- watcher hidrata ao editar -----
watch(
    () => props.company,
    (val) => {
        if (!val) return
        Object.assign(form.value, {
            name: val.name ?? '',
            corporate_name: val.corporate_name ?? '',
            cnpj: val.cnpj ?? '',
            phone: val.phone ?? '',
            whatsapp: val.whatsapp ?? '',
            email: val.email ?? '',
            site: val.site ?? '',
            notes: val.notes ?? '',
        })
    },
    { immediate: true }
)

// ----- submit final -----
function onSubmit() {
    const dPhone = onlyDigits(form.value.phone)
    const dWhats = onlyDigits(form.value.whatsapp)
    const dCNPJ = onlyDigits(form.value.cnpj)
    const payload = {
        ...form.value,
        phone: dPhone ? (isValidPhoneBR(dPhone) ? formatPhoneBR(dPhone) : null) : null,
        whatsapp: dWhats ? (isValidPhoneBR(dWhats) ? formatPhoneBR(dWhats) : null) : null,
        cnpj: dCNPJ ? (isValidCNPJ(dCNPJ) ? formatCNPJ(dCNPJ) : null) : null,
    }
    emit('submit', payload)
}
</script>

<template>
    <!-- container centralizado -->
    <div class="tw-flex tw-justify-center tw-py-6">
        <div class="tw-w-full md:tw-w-[760px]">
            <q-card flat bordered class="tw-rounded-2xl">
                <q-card-section class="tw-text-lg tw-font-semibold">
                    {{ isEdit ? 'Editar empresa' : 'Cadastrar empresa' }}
                </q-card-section>

                <q-card-section>
                    <q-form @submit.prevent="onSubmit" class="tw-space-y-4">
                        <q-input
                            v-model="form.name"
                            label="Nome *"
                            outlined
                            dense
                            lazy-rules
                            :rules="[(v) => !!v || 'Informe o nome']"
                        />

                        <q-input
                            v-model="form.corporate_name"
                            label="Razão social (opcional)"
                            outlined
                            dense
                        />

                        <q-input
                            v-model="form.cnpj"
                            label="CNPJ (opcional)"
                            outlined
                            dense
                            mask="##.###.###/####-##"
                            fill-mask
                            @blur="normalizeAndFormatCNPJ"
                            maxlength="18"
                        />

                        <div class="md:tw-grid md:tw-grid-cols-3 tw-grid-cols-1 tw-gap-3">
                            <q-input
                                v-model="form.phone"
                                :mask="phoneMask"
                                label="Telefone (opcional)"
                                type="tel"
                                outlined
                                dense
                                fill-mask
                                :unmasked-value="false"
                                @input="onPhoneInput('phone')"
                                @blur="normalizeAndFormatPhone"
                                hint="Aceita DDD + 8 ou 9 dígitos"
                            />
                            <q-input
                                v-model="form.whatsapp"
                                :mask="whatsappMask"
                                label="WhatsApp (opcional)"
                                type="tel"
                                outlined
                                dense
                                fill-mask
                                :unmasked-value="false"
                                @input="onPhoneInput('whatsapp')"
                                @blur="normalizeAndFormatWhatsapp"
                                hint="Aceita DDD + 8 ou 9 dígitos"
                            />
                            <q-input
                                v-model="form.email"
                                label="E-mail (opcional)"
                                type="email"
                                outlined
                                dense
                            />
                        </div>

                        <q-input
                            v-model="form.site"
                            label="Site (opcional)"
                            type="url"
                            outlined
                            dense
                            hint="https://exemplo.com"
                        />

                        <q-input
                            v-model="form.notes"
                            label="Observações (até 255 caracteres)"
                            outlined
                            dense
                            maxlength="255"
                            counter
                        />

                        <div class="tw-flex tw-justify-between tw-pt-2">
                            <Link :href="route('panel.companies.index')" as="button" type="button">
                                <q-btn flat color="primary" label="Voltar" />
                            </Link>
                            <q-btn
                                type="submit"
                                color="positive"
                                unelevated
                                :label="isEdit ? 'Salvar alterações' : 'Cadastrar'"
                            />
                        </div>
                    </q-form>
                </q-card-section>
            </q-card>
        </div>
    </div>
</template>
