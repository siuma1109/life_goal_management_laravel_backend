<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'priority',
        'is_checked',
        'parent_id',
        'project_id',
        'user_id',
    ];
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
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

    public function feeds()
    {
        return $this->morphMany(Feed::class, 'feedable');
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function shares()
    {
        return $this->morphMany(Share::class, 'shareable');
    }
}
