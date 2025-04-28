<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFollow extends Model
{
    use SoftDeletes;

    protected $table = 'users_followers';
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = ['user_id', 'follower_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public static function follow($userId, $followerId)
    {

        $follow = self::withTrashed()
            ->where('user_id', $userId)
            ->where('follower_id', $followerId)
            ->first();

        if ($follow) {

            if ($follow->trashed()) {
                $follow->restore();
            }
            return $follow;
        }

        return self::create([
            'user_id' => $userId,
            'follower_id' => $followerId
        ]);
    }

    public static function unfollow($userId, $followerId)
    {
        return (bool) self::where('user_id', $userId)
            ->where('follower_id', $followerId)
            ->delete();
    }

    public static function isFollowing($userId, $followerId)
    {
        return self::where('user_id', $userId)
            ->where('follower_id', $followerId)
            ->exists();
    }
}
