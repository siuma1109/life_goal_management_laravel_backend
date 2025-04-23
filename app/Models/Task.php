<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'description', 'due_date', 'priority', 'is_checked', 'parent_id', 'project_id', 'user_id'];
    protected $casts = [
        'due_date' => 'date',
        'priority' => 'integer',
        'is_checked' => 'boolean',
        'parent_id' => 'integer',
        'project_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(Task::class);
    }

    public function children()
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function sub_tasks()
    {
        return $this->children()->with('sub_tasks');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
