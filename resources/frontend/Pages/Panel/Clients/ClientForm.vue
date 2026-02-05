<template>
    <div class="tw-flex tw-justify-center tw-mt-8">
        <div class="tw-w-full md:tw-max-w-xl lg:tw-max-w-2xl">
            <q-card flat bordered>
                <q-card-section>
                    <div class="tw-text-lg tw-font-semibold tw-mb-2">
                        {{ isEdit ? 'Editar cliente' : 'Cadastrar cliente' }}
                    </div>

                        <q-form @submit.prevent="onSubmit" class="tw-grid tw-gap-4">
                            <div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
                                <q-input v-model="form.name" label="Nome" outlined dense :error="!!errors.name" :error-message="errors.name" />
                                <q-input v-model="form.website" label="Website (URL)" type="url" outlined dense :error="!!errors.website" :error-message="errors.website" />
                            </div>

                            <div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
                                <q-input v-model.number="form.order" label="Ordem" type="number" outlined dense :error="!!errors.order" :error-message="errors.order" />
                                <div class="tw-flex tw-items-center tw-gap-4">
                                    <q-toggle v-model="form.visible" label="Visível no site" left-label />
                                </div>
                                <!--
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <q-input v-model.number="form.image_max_width" label="Max width (px)" type="number" outlined dense />
                                    <q-input v-model.number="form.image_max_height" label="Max height (px)" type="number" outlined dense />
                                </div>
                                -->
                            </div>
                            <div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
                                    <q-file color="secondary" v-model="fileModel" label="Imagem" outlined dense @update:model-value="preview">
                                        <template #prepend>
                                            <q-icon name="cloud_upload" />
                                        </template>
                                    </q-file>
                                    <div class="tw-relative tw-w-[100px] tw-h-[100px] tw-rounded tw-overflow-hidden tw-bg-gray-100 tw-flex tw-items-center tw-justify-center">
                                        <img v-if="thumbUrl" :src="thumbUrl" class="tw-w-full tw-h-full tw-object-cover" alt="thumb" />
                                        <q-icon v-else name="image" />
                                        <q-btn v-if="thumbUrl && showRemove"
                                               round dense size="sm" color="negative" icon="close"
                                               class="tw-absolute tw-top-1 tw-right-1 tw-backdrop-blur"
                                               @click="removeImage" />
                                    </div>

                            </div>
                            <div class="tw-flex tw-gap-2 tw-items-center tw-justify-between">
                                <q-btn flat color="grey" @click="router.visit(route('panel.clients.index'))" label="Voltar" />
                                <q-btn type="submit" color="positive" label="Salvar" unelevated />
                            </div>
                        </q-form>
                </q-card-section>
            </q-card>
        </div>
    </div>
</template>

<script setup>
import {ref, watch, onMounted, computed} from 'vue'
import {router, useForm} from '@inertiajs/vue3'
import {QCard, QCardSection} from "quasar";

const props = defineProps({
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    endpoint: { type: String, required: true },
    defaults: { type: Object, default: () => ({ visible: true, order: 0, image_max_width: 300, image_max_height: 300 }) },
    entity: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
})

const form = useForm({
    name: '',
    website: '',
    order: 0,
    visible: true,
    image: null,
    image_max_width: 300,
    image_max_height: 300,
    remove_image: false,
})

const fileModel = ref(null)
const thumbUrl = ref(null)
const showRemove = ref(false)

onMounted(() => {
    if (props.mode === 'edit' && props.entity) {
        form.name = props.entity.name
        form.website = props.entity.website
        form.order = props.entity.order
        form.visible = !!props.entity.visible
        form.image_max_width = props.defaults?.image_max_width ?? 300
        form.image_max_height = props.defaults?.image_max_height ?? 300
        if (props.entity.image_url) {
            thumbUrl.value = props.entity.image_url
            showRemove.value = true
        }
    } else if (props.defaults) {
        form.visible = !!props.defaults.visible
        form.order = props.defaults.order ?? 0
        form.image_max_width = props.defaults.image_max_width ?? 300
        form.image_max_height = props.defaults.image_max_height ?? 300
    }
})

function preview() {
    if (fileModel.value && fileModel.value instanceof File) {
        form.image = fileModel.value
        thumbUrl.value = URL.createObjectURL(fileModel.value)
        showRemove.value = true
    }
}

function removeImage() {
    fileModel.value = null
    form.image = null
    form.remove_image = true
    if (props.entity?.image_url) {
        // mantém a miniatura até salvar? aqui já ocultamos
        thumbUrl.value = null
    }
}

function onSubmit() {
    if (props.mode === 'edit') {
       // form.put(props.endpoint, { forceFormData: true})
         form.transform(d => ({...d, forceFormData: true, _method: 'put' }))
         .post(props.endpoint)
    } else {
        form.post(props.endpoint, { forceFormData: true })
    }
}
const isEdit = computed(() => props.mode === 'edit')

</script>
