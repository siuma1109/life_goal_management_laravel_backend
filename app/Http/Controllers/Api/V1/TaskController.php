<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Notifications\UserCommentedATask;
use App\Services\AiService;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::query()
            ->with(['sub_tasks', 'user'])
            ->withCount([
                'likes',
                'comments',
                'shares',
                'likes as is_liked' => function ($query) {
                    $query->where('user_id', Auth::id());
                },
            ])
            ->when($request->type == 'inbox', function ($query) use ($request) {
                return $query->whereNull('project_id')
                    ->whereNull('parent_id');
            })
            ->when($request->type == 'completed', function ($query) use ($request) {
                return $query->where('is_checked', true);
            })
            ->when($request->has('project_id'), function ($query) use ($request) {
                return $query->where('project_id', $request->project_id)
                    ->whereNull('parent_id');
            })
            ->when($request->has('parent_id'), function ($query) use ($request) {
                return $query->where('parent_id', $request->parent_id);
            })
            ->where('user_id', $request->user_id ?: Auth::id())
            ->when($request->year && $request->month, function ($query) use ($request) {
                return $query->where(function ($query) use ($request) {
                    $query->where(function ($query) use ($request) {
                        $query->whereYear('start_date', $request->year)
                            ->whereMonth('start_date', $request->month);
                    })
                        ->orWhere(function ($query) use ($request) {
                            $query->whereYear('end_date', $request->year)
                                ->whereMonth('end_date', $request->month);
                        });
                });
            })
            ->when($request->has('date') && $request->date, function ($query) use ($request) {
                $startOfDay = Carbon::parse($request->date)->startOfDay();
                $endOfDay = Carbon::parse($request->date)->endOfDay();

                return $query->whereNull('parent_id')
                    ->where(function ($query) use ($startOfDay, $endOfDay) {
                        $query->where('start_date', '<=', $endOfDay->toDateTimeString())
                            ->where('end_date', '>=', $startOfDay->toDateTimeString());
                    });
            })
            ->orderBy('is_checked', 'asc')
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'desc')
            //->where('is_checked', false)
            ->paginate($request->per_page ?? 10);

        return response()->json($tasks);
    }

    public function tasks_count_by_date(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);

        $startOfMonth = Carbon::createFromDate($request->year, $request->month, 1)->startOfDay();
        $endOfMonth = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth()->endOfDay();

        $result = [];
        $currentDate = clone $startOfMonth;
        while ($currentDate <= $endOfMonth) {
            $dateString = $currentDate->format('Y-m-d');
            $result[$dateString] = [
                'date' => $dateString,
                'count' => 0,
                'ids' => [],
            ];
            $currentDate->addDay();
        }

        $tasks = Task::query()
            ->where('user_id', Auth::id())
            ->where('parent_id', null)
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->where('start_date', '<=', $endOfMonth)
                        ->where('end_date', '>=', $startOfMonth);
                });
            })
            ->get();

        foreach ($tasks as $task) {
            if (!$task->start_date || !$task->end_date) {
                continue;
            }

            $taskStart = Carbon::parse($task->start_date)->startOfDay();
            $taskEnd = Carbon::parse($task->end_date)->endOfDay();

            $periodStart = max($taskStart, $startOfMonth);
            $periodEnd = min($taskEnd, $endOfMonth);

            $current = clone $periodStart;

            while ($current <= $periodEnd) {
                $dateKey = $current->format('Y-m-d');
                if (isset($result[$dateKey])) {
                    $result[$dateKey]['count']++;
                    $result[$dateKey]['ids'][] = $task->id;
                }
                $current->addDay();
            }
        }

        $formattedResult = [];
        foreach ($result as $date => $row) {
            $formattedResult[] = [
                'date' => $date,
                'count' => $row['count'],
                'ids' => $row['ids'],
            ];
        }

        return response()->json($formattedResult);
    }

    public function getTasksListWithPagination(Request $request)
    {
        $tasks = Task::query()
            ->with([
                'sub_tasks' => function ($query) {
                    $query->orderBy('priority', 'asc');
                },
                'user'
            ])
            ->withCount([
                'likes',
                'comments',
                'shares',
                'likes as is_liked' => function ($query) {
                    $query->where('user_id', Auth::id());
                },
            ])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%');
            })
            ->when($request->has('date') && $request->date, function ($query) use ($request) {
                $startOfDay = Carbon::parse($request->date)->startOfDay();
                $endOfDay = Carbon::parse($request->date)->endOfDay();

                return $query->whereNull('parent_id')
                    ->where(function ($query) use ($startOfDay, $endOfDay) {
                        $query->where('start_date', '<=', $endOfDay->toDateTimeString())
                            ->where('end_date', '>=', $startOfDay->toDateTimeString());
                    });
            })
            ->where('user_id', Auth::id())
            ->where('parent_id', null)
            ->orderBy('is_checked', 'asc')
            ->orderBy('priority', 'asc')
            ->paginate($request->per_page ?? 10);
        return response()->json($tasks);
    }

    public function tasks_count(Request $request)
    {
        $query = Task::query()
            ->when($request->type == 'all_without_sub_tasks', function ($query) use ($request) {
                return $query->where('parent_id', null);
            })
            ->when($request->type == 'inbox', function ($query) use ($request) {
                return $query->where('parent_id', null)
                    ->where('project_id', null);
            })
            ->when($request->has('project_id'), function ($query) use ($request) {
                return $query->where('project_id', $request->project_id)
                    ->where('parent_id', null);
            })
            ->when($request->has('parent_id'), function ($query) use ($request) {
                return $query->where('parent_id', $request->parent_id);
            })
            ->where('user_id', $request->user_id ?: Auth::id());

        $tasks_count = $query->count();

        $finished_tasks_count = $query->where('is_checked', true)->count();

        return response()->json([
            "tasks_count" => $tasks_count,
            'finished_tasks_count' => $finished_tasks_count,
            "pending_tasks_count" => $tasks_count - $finished_tasks_count,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d H:i:s|required_unless:end_date,null|before:end_date',
            'end_date' => 'nullable|date_format:Y-m-d H:i:s|required_unless:start_date,null|after:start_date',
            'priority' => 'nullable|integer',
            'is_checked' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:tasks,id,user_id,' . Auth::id(),
            'project_id' => 'nullable|exists:projects,id,user_id,' . Auth::id(),
            'sub_tasks' => 'nullable|array',
        ]);

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'priority' => $request->priority ? $request->priority : 4,
            'is_checked' => $request->is_checked ?? false,
            'parent_id' => $request->parent_id,
            'project_id' => $request->project_id,
            'user_id' => Auth::id(),
        ]);

        $task->feeds()->create([
            'body' => 'Created a task',
            'user_id' => Auth::id(),
        ]);

        if ($request->has('sub_tasks')) {
            $this->_insert_recursive($task, $request->sub_tasks, $request->project_id);
        }

        $task = $task->load('sub_tasks');

        return response()->json($task);
    }

    private function _insert_recursive(Task $task, array $sub_tasks, $project_id)
    {
        foreach ($sub_tasks as $sub_task) {
            $sub_task['parent_id'] = $task->id;
            $sub_task['user_id'] = Auth::id();
            $sub_task['priority'] = isset($sub_task['priority']) && $sub_task['priority'] != null ? $sub_task['priority'] : 4;
            $sub_task['project_id'] = $project_id;
            $new_task = Task::create($sub_task);
            if (isset($sub_task['sub_tasks'])) {
                $this->_insert_recursive($new_task, $sub_task['sub_tasks'], $project_id);
            }
        }
    }

    public function show(Task $task)
    {
        if (!$task->user_id === Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->load(['sub_tasks', 'user'])
            ->loadCount([
                'likes',
                'comments',
                'shares',
                'likes as is_liked' => function ($query) {
                    $query->where('user_id', Auth::id());
                },
            ]);

        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d H:i:s|required_unless:end_date,null|before:end_date',
            'end_date' => 'nullable|date_format:Y-m-d H:i:s|required_unless:start_date,null|after:start_date',
            'priority' => 'nullable|integer',
            'is_checked' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:tasks,id,user_id,' . Auth::id(),
            'project_id' => 'nullable|exists:projects,id,user_id,' . Auth::id(),
            'sub_tasks' => 'nullable|array',
        ]);

        if ($task->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task
            ->fill($request->all())
            ->save();

        if ($request->is_checked) {
            $task->feeds()->create([
                'body' => 'Finished a task',
                'user_id' => Auth::id(),
            ]);
        }

        $task->load('sub_tasks');
        return response()->json($task);
    }

    public function destroy(Task $task)
    {
        if ($task->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $task->feeds()->delete();
        $task->delete();
        return response()->json(null, 204);
    }

    public function storeComment(Request $request, Task $task)
    {
        $user = $request->user();
        $request->validate([
            'body' => 'required|string',
        ]);

        $comment = $task->comments()->create([
            'body' => $request->body,
            'user_id' => $user->id,
        ]);

        $task->feeds()->create([
            'body' => 'Commented on a task',
            'user_id' => $user->id,
        ]);

        if ($task->user_id !== $user->id) {
            $task->user->notify(new UserCommentedATask($user, $task, $comment));
        }

        $comment->load('user');

        return response()->json($comment);
    }

    public function destroyComment(Task $task, Comment $comment)
    {
        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();
        return response()->json(null, 204);
    }

    public function updateComment(Request $request, Task $task, Comment $comment)
    {
        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->update($request->all());
        return response()->json($comment);
    }

    public function getComments(Request $request, Task $task)
    {
        $comments = $task->comments()->with('user')->latest()->paginate(
            $request->per_page ?? 10
        );
        return response()->json($comments);
    }

    public function exploreTasks(Request $request)
    {
        // scroll to fetch page without repeated
        $tasks = Task::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%');
            })
            ->with('user')
            ->withCount([
                'sub_tasks',
                'comments',
                'likes',
                'likes as is_liked' => function ($query) {
                    $query->where('user_id', Auth::id());
                },
            ])
            ->paginate($request->per_page ?? 10);

        return response()->json($tasks);
    }

    public function like(Request $request, Task $task)
    {
        $request->validate([
            'isLiked' => 'required|boolean',
        ]);

        $like = Like::where('likeable_id', $task->id)->where('user_id', Auth::id())->first();

        if (!$request->isLiked) {
            if ($like) {
                $like->delete();
            }
        } else {
            if (!$like) {
                $task->likes()->create([
                    'user_id' => Auth::id(),
                ]);
            }
        }

        return response()->json(null);
    }

    public function taskSuggestions(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $aiService = new AiService();
        $task_suggestions = $aiService->fetchAIDetails($request->prompt);
        return response()->json($task_suggestions);
    }
}
