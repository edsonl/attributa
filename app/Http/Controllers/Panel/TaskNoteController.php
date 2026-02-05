<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskNote;
use Illuminate\Http\Request;

class TaskNoteController extends Controller
{
    public function index(Task $task)
    {
        return response()->json($task->notes()->with('user:id,name')->get());
    }

    public function store(Request $request, Task $task)
    {
        $data = $request->validate([
            'description' => 'required|string|max:255',
            'date'        => 'nullable|date',
            'time_hours'  => 'nullable|integer|min:0',
            'time_minutes'=> 'nullable|integer|min:0|max:59',
            'value'       => 'nullable|numeric|min:0',
            'paid'        => 'nullable|boolean',
            'done'        => 'nullable|boolean',
        ]);

        $minutes = ($data['time_hours'] ?? 0) * 60 + ($data['time_minutes'] ?? 0);

        $note = $task->notes()->create([
            'description'  => $data['description'],
            'date'         => $data['date'] ?? now(),
            'time_minutes' => $minutes ?: null,
            'value'        => $data['value'] ?? null,
            'paid'         => $data['paid'] ?? false,
            'done'         => $data['done'] ?? false,
            'user_id'      => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'note' => $note->load('user:id,name'),
        ]);
    }

    public function update(Request $request, Task $task, TaskNote $note)
    {
        abort_unless($note->task_id === $task->id, 403);

        $data = $request->validate([
            'description' => 'required|string|max:255',
            'date'        => 'nullable|date',
            'time_hours'  => 'nullable|integer|min:0',
            'time_minutes'=> 'nullable|integer|min:0|max:59',
            'value'       => 'nullable|numeric|min:0',
            'paid'        => 'nullable|boolean',
            'done'        => 'nullable|boolean',
        ]);

        $minutes = ($data['time_hours'] ?? 0) * 60 + ($data['time_minutes'] ?? 0);

        $note->update([
            'description'  => $data['description'],
            'date'         => $data['date'] ?? now(),
            'time_minutes' => $minutes ?: null,
            'value'        => $data['value'] ?? null,
            'paid'         => $data['paid'] ?? false,
            'done'         => $data['done'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'note' => $note->load('user:id,name'),
        ]);
    }


    public function destroy(Task $task, TaskNote $note)
    {
        abort_unless($note->task_id === $task->id, 403);
        $note->delete();
        return response()->json(['success' => true]);
    }
}
