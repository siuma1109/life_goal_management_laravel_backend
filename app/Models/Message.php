<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chatroom_id',
        'user_id',
        'content',
        'type',
        'meta_data',
        'is_deleted',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_deleted' => 'boolean',
    ];

    public function chatroom()
    {
        return $this->belongsTo(Chatroom::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
