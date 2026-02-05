<script setup>
import { ref, watch, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import TaskNotes from './Partials/TaskNotes.vue'

const props = defineProps({
    mode: { type: String, default: 'create' },
    task: {
        type: Object,
        default: () => ({
            id: null,
            title: '',
            description: '',
            status: 'pending',
            priority: 'medium',
            due_date: '',
            company_id: null,
        }),
    },
})

const emit = defineEmits(['submit'])

const page = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id ?? null)
const statusOptions = computed(() => page.props.statusOptions ?? [])
const priorityOptions = computed(() => page.props.priorityOptions ?? [])
const companyOptions = computed(() => page.props.companyOptions ?? [])

const form = ref({
    title: '',
    description: '',
    status: 'pending',
    priority: 'medium',
    due_date: '',
    assigned_to_id: null,
    company_id: null,
})

const isEdit = computed(() => props.mode === 'edit')

watch(
    () => props.task,
    (val) => {
        if (!val) return
        Object.assign(form.value, {
            title: val?.title ?? '',
            description: val?.description ?? '',
            status: val?.status ?? 'pending',
            priority: val?.priority ?? 'medium',
            due_date: val?.due_date ?? '',
            company_id: val?.company_id ?? null,
            assigned_to_id: currentUserId.value,
        })
    },
    { immediate: true }
)

function onSubmit() {
    const payload = { ...form.value, assigned_to_id: currentUserId.value }
    emit('submit', payload)
}
</script>

<template>
    <!-- container centralizado -->
    <div class="tw-flex tw-justify-center tw-py-6">
        <div class="tw-w-full md:tw-w-[760px]">
            <q-card flat bordered class="tw-rounded-2xl">
                <q-card-section class="tw-text-lg tw-font-semibold">
                    {{ isEdit ? 'Editar tarefa' : 'Cadastrar tarefa' }}
                </q-card-section>

                <q-card-section>
                    <q-form @submit.prevent="onSubmit" class="tw-space-y-4">

                        <!-- Título -->
                        <q-input
                            v-model="form.title"
                            label="Título *"
                            outlined
                            dense
                            lazy-rules
                            :rules="[(v) => !!v || 'Informe o título']"
                        />

                        <!-- Linha com status / prioridade / data -->
                        <div class="md:tw-grid md:tw-grid-cols-3 tw-grid-cols-1 tw-gap-3">
                            <q-select
                                v-model="form.status"
                                :options="statusOptions"
                                emit-value
                                map-options
                                label="Status"
                                outlined
                                dense
                            />

                            <q-select
                                v-model="form.priority"
                                :options="priorityOptions"
                                emit-value
                                map-options
                                label="Prioridade"
                                outlined
                                dense
                            />

                            <q-input
                                v-model="form.due_date"
                                type="date"
                                label="Data de entrega"
                                outlined
                                dense
                            />
                        </div>
                        <!-- Empresa -->
                        <q-select
                            v-model="form.company_id"
                            :options="companyOptions"
                            option-value="id"
                            option-label="name"
                            emit-value
                            map-options
                            label="Empresa (opcional)"
                            outlined
                            dense
                            clearable
                        />
                        <q-input
                            v-model="form.description"
                            type="textarea"
                            label="Descrição"
                            outlined
                            dense
                            autogrow
                        />

                        <TaskNotes :task-id="task?.id" />

                        <!-- Ações -->
                        <div class="tw-flex tw-justify-between tw-pt-2">
                            <Link :href="route('panel.tasks.index')" as="button" type="button">
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

