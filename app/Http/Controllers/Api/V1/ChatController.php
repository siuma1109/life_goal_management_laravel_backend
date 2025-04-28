<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chatroom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get all chat_rooms for current user
     */
    public function getChatRooms(Request $request)
    {
        $user = $request->user();
        $chat_rooms = $user->chat_rooms()
            ->with(['users' => function($q) use ($user) {
                $q->where('users.id', '!=', $user->id);
            }, 'lastMessage'])
            ->withCount(['messages as unread_count' => function($q) use ($user) {
                $pivot = $user->chat_rooms()->where('chatroom_id', DB::raw('chat_rooms.id'))->first()->pivot;
                $lastRead = $pivot ? $pivot->last_read_at : null;
                if ($lastRead) {
                    $q->where('created_at', '>', $lastRead);
                }
            }])
            ->get();

        return response()->json([
            'data' => $chat_rooms
        ]);
    }

    /**
     * Create a new chatroom with a user
     */
    public function createChatroom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentUser = $request->user();
        $otherUser = User::findOrFail($request->user_id);

        // Check if they mutually follow each other
        if (!$currentUser->canChatWith($otherUser)) {
            return response()->json([
                'message' => 'You can only chat with users who mutually follow you'
            ], 403);
        }

        // Check if chatroom already exists
        $existingChatroom = $currentUser->chat_rooms()
            ->whereHas('users', function($q) use ($otherUser) {
                $q->where('users.id', $otherUser->id);
            })
            ->first();

        if ($existingChatroom) {
            return response()->json([
                'message' => 'Chatroom already exists',
                'data' => $existingChatroom
            ]);
        }

        $chatroom = Chatroom::createChatroom([$currentUser->id, $otherUser->id]);

        return response()->json([
            'message' => 'Chatroom created successfully',
            'data' => $chatroom->load('users')
        ], 201);
    }

    /**
     * Get messages for a chatroom
     */
    public function getMessages(Request $request, Chatroom $chatroom)
    {
        $user = Auth::user();

        // Check if user belongs to this chatroom
        if (!$chatroom->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You do not have access to this chatroom'
            ], 403);
        }

        // Update last read timestamp
        $chatroom->users()->updateExistingPivot($user->id, [
            'last_read_at' => now()
        ]);

        $messages = $chatroom->messages()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $messages
        ]);
    }

    /**
     * Send a message to a chatroom
     */
    public function sendMessage(Request $request, Chatroom $chatroom)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'type' => 'sometimes|string|in:text,image,file',
            'meta_data' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Check if user belongs to this chatroom
        if (!$chatroom->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You do not have access to this chatroom'
            ], 403);
        }

        $message = new Message([
            'content' => $request->content,
            'user_id' => $user->id,
            'type' => $request->type ?? 'text',
            'meta_data' => $request->meta_data
        ]);

        $chatroom->messages()->save($message);

        // Update last read timestamp for sender
        $chatroom->users()->updateExistingPivot($user->id, [
            'last_read_at' => now()
        ]);

        // Broadcast message to chatroom
        broadcast(new MessageSent($message->load('user'), $user))->toOthers();

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => $message->load('user')
        ], 201);
    }

    /**
     * Delete a message
     */
    public function deleteMessage(Request $request, Message $message)
    {
        $user = Auth::user();

        // Check if user owns this message
        if ($message->user_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this message'
            ], 403);
        }

        $message->update(['is_deleted' => true]);

        // Broadcast message deletion to chatroom
        broadcast(new MessageDeleted($message, $user))->toOthers();

        return response()->json([
            'message' => 'Message deleted successfully'
        ]);
    }
}
