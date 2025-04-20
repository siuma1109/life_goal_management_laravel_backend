<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::query()
            ->with('children')
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($tasks);
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
            'children' => 'nullable|array',
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

        if ($request->has('children')) {
            $this->_insert_recursive($task, $request->children);
        }

        $task = $task->load('children');

        return response()->json($task);
    }

    private function _insert_recursive(Task $task, array $children)
    {
        foreach ($children as $child) {
            $child['parent_id'] = $task->id;
            $child['user_id'] = Auth::id();
            $new_task = Task::create($child);
            if (isset($child['children'])) {
                $this->_insert_recursive($new_task, $child['children']);
            }
        }
    }

    public function show(Task $task)
    {
        if (!$task->user_id === Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->load('children');

        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        $task->update($request->all());

        $task->load('children');
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
