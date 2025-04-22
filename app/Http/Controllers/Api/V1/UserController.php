<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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
}
