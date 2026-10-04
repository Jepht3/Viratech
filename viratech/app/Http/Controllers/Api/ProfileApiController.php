<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\KycChallenge;
use App\Models\KycSubmission;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\KycService;
use App\Services\PhoneVerifier;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Profil (photo, téléphone, vérification d'identité) et jetons de notification, pour les deux applications. */
class ProfileApiController extends Controller
{
    public function show(Request $request, KycService $kyc)
    {
        $user = $request->user();
        $challenge = ($user->role === 'client' && $user->phone_verified_at && (int) $user->kyc_level < 2) ? $kyc->challenge($user) : null;

        return [
            'user' => Present::user($user),
            'challenge' => $challenge ? ['code' => $challenge->code, 'expires_at' => $challenge->expires_at->toIso8601String()] : null,
            'id_types' => KycSubmission::ID_TYPES,
        ];
    }

    public function avatar(Request $request, AvatarService $avatars)
    {
        $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:6144']);
        try {
            $avatars->store($request->user(), $request->file('photo'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['user' => Present::user($request->user()->fresh())];
    }

    public function avatarFile(Request $request, int $id)
    {
        abort_unless($request->user()->id === $id || $request->user()->isStaff(), 404);
        $user = User::findOrFail($id);
        abort_unless($user->avatar_path && Storage::exists($user->avatar_path), 404);

        return Storage::response($user->avatar_path);
    }

    public function sendPhoneCode(Request $request, PhoneVerifier $phone)
    {
        $request->validate(['phone' => 'nullable|string|max:30']);
        if ($request->filled('phone') && ! $request->user()->phone_verified_at) {
            $request->user()->update(['phone' => $request->input('phone')]);
        }
        try {
            $dev = $phone->send($request->user()->fresh());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['ok' => true] + ($dev ? ['dev_code' => $dev] : []);
    }

    public function verifyPhone(Request $request, PhoneVerifier $phone)
    {
        $data = $request->validate(['code' => 'required|string|max:10']);
        try {
            $phone->verify($request->user(), $data['code']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['user' => Present::user($request->user()->fresh())];
    }

    public function newChallenge(Request $request, KycService $kyc)
    {
        KycChallenge::where('user_id', $request->user()->id)->whereNull('used_at')->update(['expires_at' => now()]);
        $c = $kyc->challenge($request->user());

        return ['code' => $c->code, 'expires_at' => $c->expires_at->toIso8601String()];
    }

    public function submitKyc(Request $request, KycService $kyc)
    {
        $max = (int) config('viratech.kyc.max_file_kb');
        $data = $request->validate([
            'id_type' => 'required|string',
            'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.$max,
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.$max,
            'id_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.$max,
        ]);
        try {
            $kyc->submit($request->user(), $data['id_type'], $request->file('selfie'), $request->file('id_front'), $request->file('id_back'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['user' => Present::user($request->user()->fresh())];
    }

    /** Enregistre le jeton Firebase du téléphone pour recevoir les notifications dans la barre Android. */
    public function registerDevice(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:500', 'platform' => 'nullable|string|max:12']);
        DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? 'android', 'app' => $request->user()->isStaff() ? 'admin' : 'client']);

        return ['ok' => true];
    }
}