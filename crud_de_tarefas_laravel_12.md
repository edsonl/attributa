Abaixo está o _scaffold_ completo do CRUD **Task** para o seu projeto **laravel12.site** (Windows, Docker, Laravel 12 + Inertia v2 + Vue 3 + Quasar + Tailwind prefix `tw-` + Ziggy).

> **Estrutura gerada:** Migration, Model, Factory (opcional), Seeder (opcional), Requests, Controller (em `app/Http/Controllers/Panel`), Rotas, Páginas Inertia (`Index`, `Create`, `Edit`) e componentes auxiliares (`FormTask`, `StatusChip`, `PriorityChip`).

---

# 1) Migration
**`database/migrations/2025_10_09_000000_create_tasks_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending','in_progress','done'])->default('pending');
            $table->enum('priority', ['low','medium','high'])->default('medium');
            $table->date('due_date')->nullable();

            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['due_date']);
            $table->index(['assigned_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

---

# 2) Model
**`app/Models/Task.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'status', 'priority', 'due_date',
        'assigned_to_id', 'created_by_id',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
```

---

# 3) Factory (opcional)
**`database/factories/TaskFactory.php`**
```php
<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $status   = fake()->randomElement(['pending','in_progress','done']);
        $priority = fake()->randomElement(['low','medium','high']);

        return [
            'title'          => fake()->sentence(4),
            'description'    => fake()->optional()->paragraph(),
            'status'         => $status,
            'priority'       => $priority,
            'due_date'       => fake()->optional()->dateTimeBetween('now', '+45 days'),
            'assigned_to_id' => User::inRandomOrder()->value('id'),
            'created_by_id'  => User::inRandomOrder()->value('id') ?? User::factory(),
        ];
    }
}
```

**Seeder opcional** para popular algumas tarefas:
**`database/seeders/TaskSeeder.php`**
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        Task::factory()->count(20)->create();
    }
}
```

> Adicione no `DatabaseSeeder`:
```php
$this->call(\Database\Seeders\TaskSeeder::class);
```

---

# 4) Form Requests
**`app/Http/Requests/Panel/TaskStoreRequest.php`**
```php
<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'title'          => ['required','string','max:255'],
            'description'    => ['nullable','string'],
            'status'         => ['required', Rule::in(['pending','in_progress','done'])],
            'priority'       => ['required', Rule::in(['low','medium','high'])],
            'due_date'       => ['nullable','date'],
            'assigned_to_id' => ['nullable','integer','exists:users,id'],
        ];
    }
}
```

**`app/Http/Requests/Panel/TaskUpdateRequest.php`**
```php
<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'title'          => ['required','string','max:255'],
            'description'    => ['nullable','string'],
            'status'         => ['required', Rule::in(['pending','in_progress','done'])],
            'priority'       => ['required', Rule::in(['low','medium','high'])],
            'due_date'       => ['nullable','date'],
            'assigned_to_id' => ['nullable','integer','exists:users,id'],
        ];
    }
}
```

---

# 5) Controller (em `app/Http/Controllers/Panel`)
**`app/Http/Controllers/Panel/TaskController.php`**
```php
<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\TaskStoreRequest;
use App\Http\Requests\Panel\TaskUpdateRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','verified']);
        // Se quiser policies depois: $this->authorizeResource(Task::class, 'task');
    }

    public function index(Request $request): InertiaResponse
    {
        $filters = [
            'q'            => $request->string('q')->toString(),
            'status'       => $request->string('status')->toString(),
            'priority'     => $request->string('priority')->toString(),
            'assigned_to'  => $request->integer('assigned_to'),
            'sort'         => $request->string('sort')->toString(), // created_at|due_date|title
            'direction'    => $request->string('direction')->toString(), // asc|desc
        ];

        $query = Task::query()->with(['assignedTo:id,name','createdBy:id,name']);

        if ($filters['q']) {
            $query->where(function($q) use ($filters) {
                $q->where('title','like','%'.$filters['q'].'%')
                  ->orWhere('description','like','%'.$filters['q'].'%');
            });
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }
        if ($filters['priority']) {
            $query->where('priority', $filters['priority']);
        }
        if ($filters['assigned_to']) {
            $query->where('assigned_to_id', $filters['assigned_to']);
        }

        $sort = in_array($filters['sort'], ['created_at','due_date','title']) ? $filters['sort'] : 'created_at';
        $direction = $filters['direction'] === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $tasks = $query->paginate(10)->withQueryString();

        // opções de selects
        $statusOptions = [
            ['label' => 'Pendente',     'value' => 'pending'],
            ['label' => 'Em andamento', 'value' => 'in_progress'],
            ['label' => 'Concluída',    'value' => 'done'],
        ];
        $priorityOptions = [
            ['label' => 'Baixa',  'value' => 'low'],
            ['label' => 'Média',  'value' => 'medium'],
            ['label' => 'Alta',   'value' => 'high'],
        ];
        $userOptions = User::query()->select('id','name')->orderBy('name')->limit(100)->get();

        return Inertia::render('Panel/Tasks/Index', [
            'tasks'           => $tasks,
            'filters'         => $filters,
            'statusOptions'   => $statusOptions,
            'priorityOptions' => $priorityOptions,
            'userOptions'     => $userOptions,
            'defaultSort'     => $sort,
            'defaultDirection'=> $direction,
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Panel/Tasks/Create', [
            'statusOptions' => [
                ['label' => 'Pendente',     'value' => 'pending'],
                ['label' => 'Em andamento', 'value' => 'in_progress'],
                ['label' => 'Concluída',    'value' => 'done'],
            ],
            'priorityOptions' => [
                ['label' => 'Baixa', 'value' => 'low'],
                ['label' => 'Média', 'value' => 'medium'],
                ['label' => 'Alta',  'value' => 'high'],
            ],
            'userOptions' => \App\Models\User::select('id','name')->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function store(TaskStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by_id'] = $request->user()->id;
        $task = Task::create($data);

        return redirect()->route('panel.tasks.index')
            ->with('success', 'Tarefa criada com sucesso.');
    }

    public function edit(Task $task): InertiaResponse
    {
        return Inertia::render('Panel/Tasks/Edit', [
            'task' => [
                'id'            => $task->id,
                'title'         => $task->title,
                'description'   => $task->description,
                'status'        => $task->status,
                'priority'      => $task->priority,
                'due_date'      => optional($task->due_date)?->format('Y-m-d'),
                'assigned_to_id'=> $task->assigned_to_id,
            ],
            'statusOptions' => [
                ['label' => 'Pendente',     'value' => 'pending'],
                ['label' => 'Em andamento', 'value' => 'in_progress'],
                ['label' => 'Concluída',    'value' => 'done'],
            ],
            'priorityOptions' => [
                ['label' => 'Baixa', 'value' => 'low'],
                ['label' => 'Média', 'value' => 'medium'],
                ['label' => 'Alta',  'value' => 'high'],
            ],
            'userOptions' => \App\Models\User::select('id','name')->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function update(TaskUpdateRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()->route('panel.tasks.index')
            ->with('success', 'Tarefa atualizada com sucesso.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();
        return redirect()->back()->with('success', 'Tarefa excluída.');
    }
}
```

---

# 6) Rotas (resource dentro de `panel`)
**`routes/web.php`**
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Panel\TaskController;

Route::middleware(['auth','verified'])->prefix('panel')->name('panel.')->group(function () {
    Route::resource('tasks', TaskController::class);
});
```

---

# 7) Páginas Inertia (Vue 3 + Quasar)
Crie as páginas em **`resources/frontend/Pages/Panel/Tasks`** e os componentes em **`resources/frontend/Components/Panel/Task`**.

## 7.1 `Index.vue`
```vue
<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import StatusChip from '@/Components/Panel/Task/StatusChip.vue'
import PriorityChip from '@/Components/Panel/Task/PriorityChip.vue'

const props = defineProps({
  tasks: Object,
  filters: Object,
  statusOptions: Array,
  priorityOptions: Array,
  userOptions: Array,
  defaultSort: String,
  defaultDirection: String,
})

const q = ref(props.filters.q || '')
const status = ref(props.filters.status || '')
const priority = ref(props.filters.priority || '')
const assignedTo = ref(props.filters.assigned_to || '')
const sort = ref(props.defaultSort || 'created_at')
const direction = ref(props.defaultDirection || 'desc')

function applyFilters(page = 1) {
  router.get(route('panel.tasks.index'), {
    q: q.value || undefined,
    status: status.value || undefined,
    priority: priority.value || undefined,
    assigned_to: assignedTo.value || undefined,
    sort: sort.value,
    direction: direction.value,
    page,
  }, { preserveState: true, preserveScroll: true, replace: true })
}

function confirmDelete(id) {
  if (window.$confirm) {
    window.$confirm({
      title: 'Excluir tarefa',
      message: 'Tem certeza que deseja excluir esta tarefa? Essa ação pode ser desfeita (lixeira).',
      ok: 'Excluir', cancel: 'Cancelar',
      onOk: () => router.delete(route('panel.tasks.destroy', id))
    })
  } else if (confirm('Excluir tarefa?')) {
    router.delete(route('panel.tasks.destroy', id))
  }
}

function toggleSort(col) {
  if (!['title','created_at','due_date'].includes(col)) col = 'created_at'
  if (sort.value === col) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = col
    direction.value = 'desc'
  }
  applyFilters()
}

const rows = computed(() => props.tasks?.data ?? [])
const meta = computed(() => ({
  current_page: props.tasks?.current_page ?? 1,
  last_page: props.tasks?.last_page ?? 1,
  per_page: props.tasks?.per_page ?? 10,
  total: props.tasks?.total ?? 0,
}))
</script>

<template>
  <div class="tw-space-y-3">
    <div class="tw-bg-white tw-rounded-2xl tw-border tw-overflow-hidden">
      <div class="tw-flex tw-items-center tw-gap-2 tw-px-4 tw-py-3">
        <h1 class="tw-font-semibold tw-text-lg">Tarefas</h1>
        <div class="tw-ml-auto">
          <Link :href="route('panel.tasks.create')" class="">
            <button class="tw-bg-indigo-600 tw-text-white tw-rounded-2xl tw-px-3 tw-py-2 hover:tw-bg-indigo-700">Nova</button>
          </Link>
        </div>
      </div>

      <div class="tw-grid tw-gap-3 md:tw-grid-cols-4 tw-px-4 tw-pb-3">
        <input v-model="q" @keyup.enter="applyFilters()" placeholder="Buscar título ou descrição" class="tw-border tw-rounded-xl tw-px-3 tw-py-2" />
        <select v-model="status" @change="applyFilters()" class="tw-border tw-rounded-xl tw-px-3 tw-py-2">
          <option value="">Status</option>
          <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
        <select v-model="priority" @change="applyFilters()" class="tw-border tw-rounded-xl tw-px-3 tw-py-2">
          <option value="">Prioridade</option>
          <option v-for="o in priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
        <select v-model="assignedTo" @change="applyFilters()" class="tw-border tw-rounded-xl tw-px-3 tw-py-2">
          <option value="">Responsável</option>
          <option v-for="u in userOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>

      <div class="tw-overflow-x-auto">
        <table class="tw-min-w-full tw-text-sm">
          <thead>
            <tr class="tw-border-y tw-bg-gray-50">
              <th class="tw-text-left tw-font-medium tw-py-2 tw-pr-4"><button @click="toggleSort('title')">Título</button></th>
              <th class="tw-text-left tw-font-medium tw-py-2 tw-pr-4">Status</th>
              <th class="tw-text-left tw-font-medium tw-py-2 tw-pr-4">Prioridade</th>
              <th class="tw-text-left tw-font-medium tw-py-2 tw-pr-4"><button @click="toggleSort('due_date')">Entrega</button></th>
              <th class="tw-text-left tw-font-medium tw-py-2 tw-pr-4">Responsável</th>
              <th class="tw-text-right tw-font-medium tw-py-2 tw-pl-4">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="tw-border-b hover:tw-bg-gray-50">
              <td class="tw-py-2 tw-pr-4 tw-font-medium">
                <Link :href="route('panel.tasks.edit', row.id)" class="hover:tw-underline">{{ row.title }}</Link>
                <div v-if="row.description" class="tw-text-xs tw-text-gray-500 tw-line-clamp-1">{{ row.description }}</div>
              </td>
              <td class="tw-py-2 tw-pr-4"><StatusChip :value="row.status" /></td>
              <td class="tw-py-2 tw-pr-4"><PriorityChip :value="row.priority" /></td>
              <td class="tw-py-2 tw-pr-4">{{ row.due_date ?? '—' }}</td>
              <td class="tw-py-2 tw-pr-4">{{ row.assigned_to?.name ?? '—' }}</td>
              <td class="tw-py-2 tw-pl-4 tw-text-right tw-space-x-1">
                <Link :href="route('panel.tasks.edit', row.id)"><button class="tw-px-2 tw-py-1 tw-rounded-lg tw-border">Editar</button></Link>
                <button class="tw-px-2 tw-py-1 tw-rounded-lg tw-border tw-text-red-600" @click="confirmDelete(row.id)">Excluir</button>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="6" class="tw-text-center tw-text-gray-500 tw-py-6">Nenhuma tarefa encontrada.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3">
        <div class="tw-text-xs tw-text-gray-600">Página {{ meta.current_page }} de {{ meta.last_page }} — {{ meta.total }} itens</div>
        <div class="tw-space-x-2">
          <button class="tw-px-2 tw-py-1 tw-rounded-lg tw-border" :disabled="meta.current_page <= 1" @click="applyFilters(1)">«</button>
          <button class="tw-px-2 tw-py-1 tw-rounded-lg tw-border" :disabled="meta.current_page <= 1" @click="applyFilters(meta.current_page - 1)">‹</button>
          <button class="tw-px-2 tw-py-1 tw-rounded-lg tw-border" :disabled="meta.current_page >= meta.last_page" @click="applyFilters(meta.current_page + 1)">›</button>
          <button class="tw-px-2 tw-py-1 tw-rounded-lg tw-border" :disabled="meta.current_page >= meta.last_page" @click="applyFilters(meta.last_page)">»</button>
        </div>
      </div>
    </div>
  </div>
</template>
```

## 7.2 `Create.vue`
```vue
<script setup>
import { reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import FormTask from '@/Pages/Panel/Tasks/FormTask.vue'

const props = defineProps({
  statusOptions: Array,
  priorityOptions: Array,
  userOptions: Array,
})

const form = reactive({
  title: '',
  description: '',
  status: 'pending',
  priority: 'medium',
  due_date: '',
  assigned_to_id: null,
})

function submit() {
  router.post(route('panel.tasks.store'), form)
}
</script>

<template>
  <FormTask :form="form" :status-options="props.statusOptions" :priority-options="props.priorityOptions" :user-options="props.userOptions" @submit="submit" />
</template>
```

## 7.3 `Edit.vue`
```vue
<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import FormTask from '@/Pages/Panel/Tasks/FormTask.vue'

const props = defineProps({
  task: Object,
  statusOptions: Array,
  priorityOptions: Array,
  userOptions: Array,
})

const form = reactive({
  title: props.task.title,
  description: props.task.description,
  status: props.task.status,
  priority: props.task.priority,
  due_date: props.task.due_date,
  assigned_to_id: props.task.assigned_to_id,
})

function submit() {
  router.put(route('panel.tasks.update', props.task.id), form)
}
</script>

<template>
  <FormTask :form="form" :status-options="props.statusOptions" :priority-options="props.priorityOptions" :user-options="props.userOptions" @submit="submit" />
</template>
```

## 7.4 `FormTask.vue`
```vue
<script setup>
import { usePage, Link } from '@inertiajs/vue3'

const props = defineProps({
  form: Object,
  statusOptions: Array,
  priorityOptions: Array,
  userOptions: Array,
})
const emit = defineEmits(['submit'])

const errors = usePage().props?.errors ?? {}
</script>

<template>
  <div class="tw-bg-white tw-rounded-2xl tw-border tw-max-w-3xl tw-mx-auto tw-my-4 tw-overflow-hidden">
    <div class="tw-px-4 tw-py-3 tw-font-semibold">Dados da Tarefa</div>
    <div class="tw-px-4 tw-py-4 tw-space-y-4">
      <div>
        <label class="tw-text-sm tw-font-medium">Título</label>
        <input v-model="props.form.title" class="tw-w-full tw-border tw-rounded-xl tw-px-3 tw-py-2" />
        <div v-if="errors.title" class="tw-text-xs tw-text-red-600 tw-mt-1">{{ errors.title }}</div>
      </div>

      <div>
        <label class="tw-text-sm tw-font-medium">Descrição</label>
        <textarea v-model="props.form.description" rows="4" class="tw-w-full tw-border tw-rounded-xl tw-px-3 tw-py-2"></textarea>
        <div v-if="errors.description" class="tw-text-xs tw-text-red-600 tw-mt-1">{{ errors.description }}</div>
      </div>

      <div class="tw-grid md:tw-grid-cols-3 tw-gap-3">
        <div>
          <label class="tw-text-sm tw-font-medium">Status</label>
          <select v-model="props.form.status" class="tw-w-full tw-border tw-rounded-xl tw-px-3 tw-py-2">
            <option v-for="o in props.statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <div v-if="errors.status" class="tw-text-xs tw-text-red-600 tw-mt-1">{{ errors.status }}</div>
        </div>
        <div>
          <label class="tw-text-sm tw-font-medium">Prioridade</label>
          <select v-model="props.form.priority" class="tw-w-full tw-border tw-rounded-xl tw-px-3 tw-py-2">
            <option v-for="o in props.priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <div v-if="errors.priority" class="tw-text-xs tw-text-red-600 tw-mt-1">{{ errors.priority }}</div>
        </div>
        <div>
          <label class="tw-text-sm tw-font-medium">Entrega</label>
          <input type="date" v-model="props.form.due_date" class="tw-w-full tw-border tw-rounded-xl tw-px-3 tw-py-2" />
          <div v-if="errors.due_date" class="tw-text-xs tw-text-red-600 tw-mt-1">{{ errors.due_date }}</div>
        </div>
      </div>

      <div>
        <label class="tw-text-sm tw-font-medium">Responsável</label>
        <select v-model="props.form.assigned_to_id" class="tw-w-full tw-border tw-rounded-xl tw-px-3 tw-py-2">
          <option :value="null">—</option>
          <option v-for="u in props.userOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <div v-if="errors.assigned_to_id" class="tw-text-xs tw-text-red-600 tw-mt-1">{{ errors.assigned_to_id }}</div>
      </div>
    </div>

    <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-bg-gray-50">
      <Link :href="route('panel.tasks.index')" class="tw-text-sm">Voltar</Link>
      <button @click="$emit('submit')" class="tw-bg-indigo-600 tw-text-white tw-rounded-2xl tw-px-4 tw-py-2 hover:tw-bg-indigo-700">Salvar</button>
    </div>
  </div>
</template>
```

## 7.5 Componentes: Chips
**`resources/frontend/Components/Panel/Task/StatusChip.vue`**
```vue
<script setup>
const props = defineProps({ value: String })
</script>
<template>
  <span :class="[
    'tw-inline-flex tw-items-center tw-px-2 tw-py-0.5 tw-rounded-full tw-text-xs',
    props.value === 'done' ? 'tw-bg-green-600 tw-text-white' : props.value === 'in_progress' ? 'tw-bg-yellow-400 tw-text-black' : 'tw-bg-gray-200 tw-text-gray-800'
  ]">
    {{ props.value === 'done' ? 'Concluída' : props.value === 'in_progress' ? 'Em andamento' : 'Pendente' }}
  </span>
</template>
```

**`resources/frontend/Components/Panel/Task/PriorityChip.vue`**
```vue
<script setup>
const props = defineProps({ value: String })
</script>
<template>
  <span :class="[
    'tw-inline-flex tw-items-center tw-px-2 tw-py-0.5 tw-rounded-full tw-text-xs',
    props.value === 'high' ? 'tw-bg-red-600 tw-text-white' : props.value === 'medium' ? 'tw-bg-indigo-600 tw-text-white' : 'tw-bg-gray-200 tw-text-gray-800'
  ]">
    {{ props.value === 'high' ? 'Alta' : props.value === 'medium' ? 'Média' : 'Baixa' }}
  </span>
</template>
```

---

# 8) Passos para ativar
1. **Migration**
```bash
php artisan migrate
```
2. (Opcional) **Seed**
```bash
php artisan db:seed --class=TaskSeeder
```
3. **Acesse**: `/panel/tasks` (precisa estar autenticado)

> Se quiser, na próxima iteração troco a tabela manual pelo seu `IndexTable.vue` com slots de ações e paginação server-side.

