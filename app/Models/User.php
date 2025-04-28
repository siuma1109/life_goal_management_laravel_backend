<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function followers()
    {
        // I am user id
        return $this->belongsToMany(User::class, 'users_followers', 'user_id', 'follower_id');
    }

    public function following()
    {
        // I am follower id
        return $this->belongsToMany(User::class, 'users_followers', 'follower_id', 'user_id');
    }

    public function chat_rooms()
    {
        return $this->belongsToMany(Chatroom::class, 'chatroom_users')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function getMutualFollowersIds()
    {
        $followingIds = $this->following()->pluck('users.id')->toArray();
        $followerIds = $this->followers()->pluck('users.id')->toArray();

        return array_intersect($followingIds, $followerIds);
    }

    public function canChatWith(User $user)
    {
        return $this->following()->where('users.id', $user->id)->exists() &&
               $user->following()->where('users.id', $this->id)->exists();
    }

}
