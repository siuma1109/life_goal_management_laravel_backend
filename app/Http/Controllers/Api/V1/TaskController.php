<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::query()
            ->with('sub_tasks')
            ->when($request->has('project_id'), function ($query) use ($request) {
                return $query->where('project_id', $request->project_id);
            })
            ->when($request->has('parent_id'), function ($query) use ($request) {
                return $query->where('parent_id', $request->parent_id);
            })
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($tasks);
    }

    public function tasks_count(Request $request)
    {
        $task_count = Task::query()
            ->with('sub_tasks')
            ->when($request->has('project_id'), function ($query) use ($request) {
                return $query->where('project_id', $request->project_id);
            })
            ->when($request->has('parent_id'), function ($query) use ($request) {
                return $query->where('parent_id', $request->parent_id);
            })
            ->where('user_id', Auth::id())
            ->count();

        return response()->json([
            "task_count" => $task_count,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|integer',
            'is_checked' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:tasks,id,user_id,' . Auth::id(),
            'project_id' => 'nullable|exists:projects,id,user_id,' . Auth::id(),
            'sub_tasks' => 'nullable|array',
        ]);

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'priority' => $request->priority,
            'is_checked' => $request->is_checked ?? false,
            'parent_id' => $request->parent_id,
            'project_id' => $request->project_id,
            'user_id' => Auth::id(),
        ]);

        if ($request->has('sub_tasks')) {
            $this->_insert_recursive($task, $request->sub_tasks);
        }

        $task = $task->load('sub_tasks');

        return response()->json($task);
    }

    private function _insert_recursive(Task $task, array $sub_tasks)
    {
        foreach ($sub_tasks as $sub_task) {
            $sub_task['parent_id'] = $task->id;
            $sub_task['user_id'] = Auth::id();
            $new_task = Task::create($sub_task);
            if (isset($sub_task['sub_tasks'])) {
                $this->_insert_recursive($new_task, $sub_task['sub_tasks']);
            }
        }
    }

    public function show(Task $task)
    {
        if (!$task->user_id === Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->load('sub_tasks');

        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        $task->update($request->all());

        $task->load('sub_tasks');
        return response()->json($task);
    }

    public function destroy(Task $task)
    {
        if ($task->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->delete();
        return response()->json(null, 204);
    }
}
