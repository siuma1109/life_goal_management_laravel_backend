<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            ...$user->toArray(),
            ...[
                'token' => $user->createToken('API TOKEN')->plainTextToken,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'current_password' => 'nullable|string|min:8',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validated['email'] == $user->email) {
            unset($validated['email']);
        }

        if (empty($validated['current_password'])) {
            unset($validated['current_password']);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        if (isset($validated['password']) && !isset($validated['current_password'])) {
            return response()->json([
                'message' => 'Current password is required',
                'errors' => [
                    'current_password' => ['The current password field is required.'],
                ],
            ], 422);
        }

        if (isset($validated['current_password']) && !Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => [
                    'current_password' => ['The current password field is incorrect.'],
                ],
            ], 422);
        }

        $user->fill($validated)->save();

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(null, 204);
    }

    public function getUsersList(Request $request)
    {
        $users = User::query()
            ->withCount([
                'followers' => function ($query) {
                    $query->where('deleted_at', null);
                },
                'following' => function ($query) {
                    $query->where('deleted_at', null);
                },
                'followers as is_followed' => function ($query) use ($request) {
                    $query->where('follower_id', $request->user()->id)
                        ->where('deleted_at', null);
                },
            ])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            })
            ->where('id', '!=', $request->user()->id)
            ->paginate(10);

        return response()->json($users);
    }

    public function getFollowers(Request $request, User $user)
    {
        $followers = $user->followers()
            ->where('deleted_at', null)
            //->where('id', '!=', $request->user()->id)
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            })
            ->paginate($request->per_page ?? 10);
        $followers->loadCount([
            'followers' => function ($query) use ($request) {
                $query->where('deleted_at', null);
            },
            'following' => function ($query) use ($request) {
                $query->where('deleted_at', null);
            },
            'followers as is_followed' => function ($query) use ($request) {
                $query->where('follower_id', $request->user()->id)
                    ->where('deleted_at', null);
            },
        ]);

        return response()->json($followers);
    }

    public function getFollowing(Request $request, User $user)
    {
        $following = $user->following()
            ->where('deleted_at', null)
            //->where('id', '!=', $request->user()->id)
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            })
            ->paginate($request->per_page ?? 10);
        $following->loadCount([
            'followers' => function ($query) use ($request) {
                $query->where('deleted_at', null);
            },
            'following' => function ($query) use ($request) {
                $query->where('deleted_at', null);
            },
            'followers as is_followed' => function ($query) use ($request) {
                $query->where('follower_id', $request->user()->id)
                    ->where('deleted_at', null);
            },
        ]);

        return response()->json($following);
    }

    public function getNotifications(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->when($request->has('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->paginate($request->per_page ?? 10);

        return response()->json($notifications);
    }

    public function getUnreadCount(Request $request)
    {
        $unreadCount = $request->user()->unreadNotifications()->count();
        return response()->json(['unread_count' => $unreadCount]);
    }

    public function markAsRead(Request $request)
    {
        $validated =$request->validate([
            'notification_id' => 'required|exists:notifications,id,notifiable_id,' . $request->user()->id,
        ]);

        $request->user()->unreadNotifications()->where('id', $validated['notification_id'])->update(['read_at' => now()]);
        return response()->json(null);
    }

    public function followUser(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot follow yourself',
                'success' => false
            ], 400);
        }

        $isFollowing = UserFollow::isFollowing($user->id, $request->user()->id);
        $status = 'unknown';

        if ($isFollowing) {
            UserFollow::unfollow($user->id, $request->user()->id);
            $status = 'unfollowed';
        } else {
            UserFollow::follow($user->id, $request->user()->id);
            $status = 'followed';
        }

        $followersCount = $user->followers()->count();
        $followingCount = $user->following()->count();

        return response()->json([
            'message' => $status === 'followed' ? 'Followed successfully' : 'Unfollowed successfully',
            'status' => $status,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
                'is_followed' => $status === 'followed'
            ],
            'success' => true
        ]);
    }
}
