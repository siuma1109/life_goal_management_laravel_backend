Broadcast::channel('chat.{chatroomId}', function ($user, $chatroomId) {
    return $user->chat_rooms()->where('chat_rooms.id', $chatroomId)->exists();
});
