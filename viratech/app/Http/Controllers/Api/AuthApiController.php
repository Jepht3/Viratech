<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthApiController extends Controller
{
    /** `app` = client | admin : l'application admin n'accepte que le personnel, l'application client que les clients. */
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required', 'app' => 'required|in:client,admin', 'device' => 'nullable|string|max:80']);

        $user = User::where('email', $data['email'])->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email ou mot de passe incorrect.'], 422);
        }

        if (($data['app'] === 'admin') !== $user->isStaff()) {
            return response()->json(['message' => $data['app'] === 'admin' ? 'Cette application est réservée à l\'équipe Viratech.' : 'Utilisez l\'application Viratech Admin.'], 403);
        }

        return ['token' => $user->createToken($data['device'] ?? $data['app'], [$data['app']])->plainTextToken, 'user' => Present::user($user)];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|string|max:30', 'password' => ['required', Password::min(8)], 'device' => 'nullable|string|max:80',
        ]);
        $user = User::create([...collect($data)->except('device')->all(), 'role' => 'client']);
        try {
            app(\App\Services\EmailVerifier::class)->send($user);
        } catch (\Throwable $e) {
            report($e);
        }

        return ['token' => $user->createToken($data['device'] ?? 'client', ['client'])->plainTextToken, 'user' => Present::user($user)];
    }

    public function me(Request $request)
    {
        return ['user' => Present::user($request->user()), 'unread_notifications' => $request->user()->unreadNotifications()->count()];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ['ok' => true];
    }

    public function preferences(Request $request)
    {
        $request->user()->update(['notify_email' => $request->boolean('notify_email'), 'notify_push' => $request->boolean('notify_push')]);

        return ['user' => Present::user($request->user()->fresh())];
    }
}
