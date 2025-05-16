<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feed;
use App\Models\Like;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $feeds = Feed::with('user')
            ->withCount([
                'likes',
                'comments',
                'shares',
                'likes as is_liked' => function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                },
            ])
            ->latest()
            ->paginate($request->per_page ?? 10);
        return response()->json($feeds);
    }

    public function like(Request $request, Feed $feed)
    {
        $request->validate([
            'isLiked' => 'required|boolean',
        ]);

        $like = Like::where('likeable_id', $feed->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$request->isLiked) {
            if ($like) {
                $like->delete();
            }
        } else {
            if (!$like) {
                $feed->likes()->create([
                    'user_id' => $request->user()->id,
                ]);
            }
        }
        return response()->json(null);
    }
    public function storeComment(Request $request, Feed $feed)
    {
        $request->validate([
            'body' => 'required|string',
        ]);

        $comment = $feed->comments()->create([
            'body' => $request->body,
            'user_id' => $request->user()->id,
        ]);

        $comment->load('user');

        return response()->json($comment);
    }
    public function getComments(Request $request, Feed $feed)
    {
        $comments = $feed->comments()->with('user')->paginate($request->per_page ?? 10);
        return response()->json($comments);
    }

}
