<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::query()
            ->withCount([
                'tasks' => function ($query) {
                    $query->where('parent_id', null);
                }
            ])
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project = Project::create([
            'name' => $request->name,
            'user_id' => Auth::id(),
        ]);

        return response()->json($project);
    }

    public function show(Project $project)
    {
        if (!$project->user_id === Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($project);
    }

    public function update(Request $request, Project $project)
    {
        $project
            ->fill($request->all())
            ->save();
        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $project->delete();
        return response()->json(null, 204);
    }
}
