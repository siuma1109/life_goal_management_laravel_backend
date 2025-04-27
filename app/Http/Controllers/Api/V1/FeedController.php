<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feed;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        $feeds = Feed::with('user')->paginate($request->per_page ?? 10);
        return response()->json($feeds);
    }
}
