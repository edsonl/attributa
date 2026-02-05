<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\TaskStoreRequest;
use App\Http\Requests\Panel\TaskUpdateRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Models\Company;


class TaskController extends Controller
{
    public function __construct()
    {

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

        $perPage = (int) $request->integer('per_page', 10);

        $query = Task::query()
            ->with(['company'])
            ->with(['assignedTo:id,name','createdBy:id,name'])
            ->withSum('notes as total_time_minutes', 'time_minutes')
            ->withSum('notes as total_value', 'value')
            ->with(['notes' => function ($q) {
                $q->select('id', 'task_id', 'value', 'paid');
            }]);

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

        // 🔹 Permitir ordenação pelo nome da empresa
        if ($sort === 'company') {
            $query->leftJoin('companies', 'companies.id', '=', 'tasks.company_id')
                ->select('tasks.*')
                ->orderBy('companies.name', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        //$query->orderBy($sort, $direction);

        //Descrição
        $descLimit = 30;

        $tasks = $query->paginate(10)->through(function ($task) use ($descLimit) {

                $full = (string) ($task->description ?? '');
                $isTruncated = mb_strlen($full) > $descLimit;

                $h = floor(($task->total_time_minutes ?? 0) / 60);
                $m = ($task->total_time_minutes ?? 0) % 60;

                $paid = $task->notes->where('paid', true)->sum('value');
                $pending = $task->notes->where('paid', false)->sum('value');
                $total = $paid + $pending;

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => optional($task->due_date)?->format('d/m/Y'),
                    'created_at' => optional($task->created_at)?->format('d/m/Y H:i'),
                    'description_preview' => $isTruncated ? mb_substr($full, 0, $descLimit) . '…' : $full,
                    'is_description_truncated' => $isTruncated,
                    'company' => $task->company ? $task->company->only('id','name') : null,
                    //'total_time_minutes'    => (int) ($task->total_time_minutes ?? 0),
                    'total_time_formatted'  => $task->total_time_minutes ? sprintf('%02dh %02dm', $h, $m) : null,
                    'total_value'           => (float) ($task->total_value ?? 0),
                    //'total_value_formatted' => $task->total_value
                    //    ? number_format($task->total_value, 2, ',', '.')
                    //    : null,
                    'notes_summary' => [
                        'paid' => $paid,
                        'pending' => $pending,
                        'total' => $total,
                    ],
                ];
           }
        )->withQueryString();

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
            'title'=>'Tarefas',
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
            'title'=>'Criar tarefa',
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
            'companyOptions'  => Company::select('id','name')->orderBy('name')->limit(200)->get(),
            'userOptions' => []//\App\Models\User::select('id','name')->orderBy('name')->limit(100)->get(),
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
            'title'=>'Editar tarefa',
            'task' => [
                'id'            => $task->id,
                'title'         => $task->title,
                'description'   => $task->description,
                'status'        => $task->status,
                'priority'      => $task->priority,
                'due_date'      => optional($task->due_date)?->format('Y-m-d'),
                'company_id'    => $task->company_id,
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
            'companyOptions' => Company::select('id','name')->orderBy('name')->limit(200)->get(),
            'userOptions' =>[] //\App\Models\User::select('id','name')->orderBy('name')->limit(100)->get(),
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

    public function description(\App\Models\Task $task)
    {
        return response()->json([
            'id'          => $task->id,
            'title'       => $task->title,
            'description' => (string) ($task->description ?? ''),
        ]);
    }

    public function updateStatus(Request $request, Task $task)
    {
        $request->validate([
            'status' => 'required|string|in:pending,in_progress,done',
        ]);

        if($task->status=='done'){
            $task->priority = 'low';
        }

        $task->status   = $request->status;
        $task->save();

        return response()->json([
            'success' => true,
            'status' => $task->status,
            'message' => 'Status atualizado com sucesso.',
        ]);
    }

    public function updatePriority(Request $request, Task $task)
    {
        $request->validate([
            'priority' => ['required', Rule::in(['low','medium','high'])],
        ]);

        $task->priority = $request->priority;
        $task->save();

        return response()->json([
            'success' => true,
            'priority' => $task->priority,
            'message' => 'Prioridade atualizada com sucesso.',
        ]);
    }


}
