<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    protected $fillable = ['user_id'];

    public function shareable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
}
