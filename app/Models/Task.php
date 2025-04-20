<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'description', 'due_date', 'priority', 'is_checked', 'parent_id', 'project_id', 'user_id'];

    public function parent()
    {
        return $this->belongsTo(Task::class);
    }

    public function subTasks()
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function children()
    {
        return $this->subTasks()->with('children');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
