<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\KycSubmission;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerifier;
use App\Services\KycService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Profil (photo, email, vérification d'identité) et jetons de notification, pour les deux applications. */
class ProfileApiController extends Controller
{
    public function show(Request $request, KycService $kyc)
    {
        $user = $request->user();
        $stage = $user->role === 'client' ? $kyc->stage($user) : 'none';
        $challenge = $stage === 'document' ? $kyc->challenge($user) : null;

        return [
            'user' => Present::user($user),
            'kyc_stage' => $stage,
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

    public function sendEmailCode(Request $request, EmailVerifier $email)
    {
        try {
            $dev = $email->send($request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['ok' => true] + ($dev ? ['dev_code' => $dev] : []);
    }

    public function verifyEmail(Request $request, EmailVerifier $email)
    {
        $data = $request->validate(['code' => 'required|string|max:10']);
        try {
            $email->verify($request->user(), $data['code']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['user' => Present::user($request->user()->fresh())];
    }

    /** Étape 1 : la pièce (carte d'électeur : avant et arrière ; passeport : page photo). */
    public function kycDocument(Request $request, KycService $kyc)
    {
        $max = (int) config('viratech.kyc.max_file_kb');
        $data = $request->validate([
            'id_type' => 'required|string', 'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.$max, 'id_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.$max,
        ]);
        try {
            $kyc->submitDocument($request->user(), $data['id_type'], $request->file('id_front'), $request->file('id_back'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->show($request, $kyc);
    }

    public function newChallenge(Request $request, KycService $kyc)
    {
        $c = $kyc->newChallenge($request->user());

        return ['code' => $c->code, 'expires_at' => $c->expires_at->toIso8601String()];
    }

    /** Étape 2 : le selfie avec la pièce en main et le code écrit sur papier. */
    public function kycSelfie(Request $request, KycService $kyc)
    {
        $request->validate(['selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.(int) config('viratech.kyc.max_file_kb')]);
        try {
            $kyc->submitSelfie($request->user(), $request->file('selfie'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->show($request, $kyc);
    }

    /** Enregistre le jeton Firebase du téléphone pour recevoir les notifications dans la barre Android. */
    public function registerDevice(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:500', 'platform' => 'nullable|string|max:12']);
        DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? 'android', 'app' => $request->user()->isStaff() ? 'admin' : 'client']);

        return ['ok' => true];
    }
}