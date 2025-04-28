<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chatroom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'chatroom_users')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    public static function createChatroom($users)
    {
        $chatroom = self::create();
        $chatroom->users()->attach($users, [
            'last_read_at' => now()
        ]);

        return $chatroom;
    }

    public function getUnreadMessageCount($userId)
    {
        $lastRead = $this->users()
            ->where('user_id', $userId)
            ->first()
            ->pivot
            ->last_read_at;

        return $lastRead ? $this->messages()->where('created_at', '>', $lastRead)->count() : $this->messages()->count();
    }
}
