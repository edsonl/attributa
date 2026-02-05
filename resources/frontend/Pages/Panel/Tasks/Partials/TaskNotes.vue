<template>
    <div class="tw-mt-4">
        <!-- Se a tarefa ainda não foi salva -->
        <template v-if="!taskId">
            <div class="tw-bg-gray-50 tw-border tw-rounded-lg tw-p-3 tw-text-sm tw-text-gray-600">
                💡 Salve a tarefa para adicionar anotações.
            </div>
        </template>

        <!-- Se já existe uma tarefa -->
        <template v-else>
            <q-expansion-item
                icon="chat"
                label="Anotações"
                expand-separator
            >
                <div class="tw-space-y-3">
                    <!-- LISTA DE ANOTAÇÕES -->
                    <div
                        v-for="note in notes"
                        :key="note.id"
                        class="tw-bg-gray-50 tw-rounded-lg tw-p-3 tw-flex tw-flex-col tw-gap-1"
                    >
                        <!--
                        <div class="tw-flex tw-justify-between tw-items-center">
                            <span class="tw-font-medium tw-text-sm">{{ note.user?.name ?? 'Usuário' }}</span>
                            <span class="tw-text-xs tw-text-gray-500">{{ formatDate(note.date) }}</span>
                        </div>-->
                        <div class="tw-flex tw-justify-between tw-items-center">
                            <div class="tw-flex tw-items-center">
                                <span class="tw-text-xs tw-text-gray-500">{{ formatDate(note.date) }}</span>
                                <span v-if="note.time_minutes" class="tw-ms-2">Tempo: {{ formatTime(note.time_minutes) }}</span>
                                <span v-if="note.value" class="tw-ms-2">{{ formatValue(note.value) }}</span>
                                <template v-if="note.value > 0">
                                    <q-badge color="positive" v-if="note.paid" class="tw-ms-2">
                                        Pago
                                    </q-badge>
                                    <q-badge color="orange" class="tw-ms-2" v-else>
                                        Pendente
                                    </q-badge>
                                </template>
                            </div>
                            <div class="tw-flex tw-gap-2 tw-justify-end">
                                <q-btn flat size="sm" icon="check_circle" round @click="toggleDone(note)"  :color="`${note.done ? 'positive':'warning'}`" />
                                <q-btn flat size="sm" color="primary" icon="edit" round @click="openEdit(note)" />
                                <q-btn flat size="sm" color="negative" icon="delete" round @click="deleteNote(note.id)" />
                            </div>
                        </div>
                        <div class="tw-flex tw-w-full">
                            <p class="tw-text-gray-600">
                                {{ note.description }}
                            </p>
                        </div>
                    </div>

                    <!-- FORMULÁRIO DE NOVA ANOTAÇÃO -->
                    <div class="tw-bg-white tw-border tw-rounded-lg tw-p-3">
                        <q-input v-model="form.description" label="Descrição" dense maxlength="255"
                                 type="textarea"
                                 autogrow
                        />
                        <div class="tw-grid tw-grid-cols-3 tw-gap-2 tw-mt-2">
                            <q-input v-model="form.date" type="datetime-local" label="Data" dense />
                            <div class="tw-grid tw-grid-cols-2 tw-gap-2">
                                <q-input v-model.number="form.time_hours" type="number" min="0" label="Horas" dense />
                                <q-input v-model.number="form.time_minutes" type="number" min="0" max="59" label="Minutos" dense />
                            </div>
                            <q-input v-model="form.value" type="number" step="0.01" label="Valor (R$)" dense />
                        </div>
                        <div class="tw-flex tw-mt-2">
                            <q-toggle v-model="form.paid" label="Pago?" color="green" left-label />
                            <q-toggle v-model="form.done" label="Finalizado?" color="green" left-label />
                        </div>
                        <div class="tw-text-right tw-mt-2">
                            <q-btn color="primary" icon="add" label="Adicionar" size="sm" @click="addNote" />
                        </div>
                    </div>
                </div>
            </q-expansion-item>
        </template>

        <!-- MODAL DE EDIÇÃO -->
        <q-dialog v-model="editDialog">
            <q-card class="tw-w-[500px] max-w-full">
                <q-card-section>
                    <div class="tw-font-semibold tw-text-lg tw-mb-2">Editar anotação</div>
                    <q-input v-model="editForm.description" label="Descrição" dense maxlength="255"
                             type="textarea"
                             autogrow
                    />
                    <div class="tw-grid tw-grid-cols-3 tw-gap-2 tw-mt-2">
                        <q-input v-model="editForm.date" type="datetime-local" label="Data" dense />
                        <div class="tw-grid tw-grid-cols-2 tw-gap-2">
                            <q-input v-model.number="editForm.time_hours" type="number" min="0" label="Horas" dense />
                            <q-input v-model.number="editForm.time_minutes" type="number" min="0" max="59" label="Minutos" dense />
                        </div>
                        <q-input v-model="editForm.value" type="number" step="0.01" label="Valor (R$)" dense />
                    </div>
                    <div class="tw-flex tw-mt-2">
                        <q-toggle v-model="editForm.paid" label="Pago?" color="green" left-label />
                        <q-toggle v-model="editForm.done" label="Finalizado?" color="green" left-label />
                    </div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="primary" label="Salvar" @click="updateNote" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useQuasar } from 'quasar'

const props = defineProps({ taskId: Number })
const $q = useQuasar()

// Lista de anotações
const notes = ref([])

// Formulário para adicionar nova nota
const form = ref({
    description: '',
    date: '',
    time_hours: null,
    time_minutes: null,
    value: null,
    paid: false,
    done: false,
})

// Formulário de edição
const editDialog = ref(false)
const editingNote = ref(null)
const editForm = ref({
    description: '',
    date: '',
    time_hours: null,
    time_minutes: null,
    value: null,
    paid: false,
    done: false,
})

// === Funções de formatação ===
function formatDate(date) {
    if (!date) return ''
    return new Date(date).toLocaleString('pt-BR')
}
function formatTime(mins) {
    const h = Math.floor(mins / 60)
    const m = mins % 60
    return `${h}h ${m}m`
}
function formatValue(value) {
    const num = Number(value)
    if (isNaN(num)) return ''
    return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

// === CRUD ===
const loadNotes = () => {
    if (!props.taskId) return
    axios.get(route('panel.tasks.notes.index', props.taskId))
        .then(res => (notes.value = res.data))
        .catch(() => {})
}

const addNote = () => {
    if (!form.value.description) {
        $q.notify({ type: 'warning', message: 'Descrição é obrigatória.' })
        return
    }

    axios.post(route('panel.tasks.notes.store', props.taskId), {
        ...form.value,
        paid: form.value.paid ? 1 : 0,
    }).then(res => {
            notes.value.unshift(res.data.note)
            form.value = { description: '', date: '', time_hours: null, time_minutes: null, value: null, paid: false, done:false }
            $q.notify({ type: 'positive', message: 'Anotação adicionada.' })
        })

}


const toggleDone = (note) => {
    note.done = !note.done;
    axios.put(route('panel.tasks.notes.update', [props.taskId, note.id]), {
        ...note,
        done: note.done ? 1 : 0, // ✅ força número
    }).then(res => {
        $q.notify({ type: 'positive', message: 'Anotação atualizada.' })
    })
}

const deleteNote = (id) => {
    $q.dialog({
        title: 'Excluir',
        message: 'Deseja remover esta anotação?',
        cancel: true,
        persistent: true
    }).onOk(() => {
        axios.delete(route('panel.tasks.notes.destroy', [props.taskId, id]))
            .then(() => {
                notes.value = notes.value.filter(n => n.id !== id)
                $q.notify({ type: 'positive', message: 'Anotação excluída.' })
            })
    })
}

// === Edição ===
const openEdit = (note) => {
    editingNote.value = note
    editForm.value = {
        description: note.description,
        date: note.date ? note.date.slice(0, 16) : '',
        time_hours: note.time_minutes ? Math.floor(note.time_minutes / 60) : null,
        time_minutes: note.time_minutes ? note.time_minutes % 60 : null,
        value: note.value ?? null,
        //paid: note.paid ?? false,
        paid: !!note.paid, // ✅ conversão garantida
        done: !!note.done,
    }
    editDialog.value = true
}

const updateNote = () => {
    if (!editingNote.value) return
    axios.put(route('panel.tasks.notes.update', [props.taskId, editingNote.value.id]), {
        ...editForm.value,
        paid: editForm.value.paid ? 1 : 0, // ✅ força número
        done: editForm.value.done ? 1 : 0, // ✅ força número
    }).then(res => {
            const updated = res.data.note
            const index = notes.value.findIndex(n => n.id === updated.id)
            if (index !== -1) notes.value[index] = updated
            editDialog.value = false
            $q.notify({ type: 'positive', message: 'Anotação atualizada.' })
    })
}

onMounted(() => {
    if (props.taskId) loadNotes()
})
</script>
