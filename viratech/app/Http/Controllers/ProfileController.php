<?php

namespace App\Http\Controllers;

use App\Models\KycSubmission;
use App\Services\AvatarService;
use App\Services\KycService;
use App\Services\PhoneVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Profil : photo, téléphone vérifié, vérification d'identité, plafond. */
class ProfileController extends Controller
{
    public function show(Request $request, KycService $kyc)
    {
        $user = $request->user();

        return view('profile', [
            'user' => $user,
            'limit' => $user->role === 'client' ? $user->limitInfo() : null,
            'submission' => $user->kycSubmissions()->latest('id')->first(),
            'challenge' => ($user->role === 'client' && $user->phone_verified_at && (int) $user->kyc_level < 2) ? $kyc->challenge($user) : null,
            'idTypes' => KycSubmission::ID_TYPES,
        ]);
    }

    public function avatar(Request $request, AvatarService $avatars)
    {
        $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:6144']);
        try {
            $avatars->store($request->user(), $request->file('photo'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        return back()->with('ok', 'Photo de profil enregistrée.');
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
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('ok', 'Code envoyé par SMS.'.($dev ? ' (mode test : '.$dev.')' : ''));
    }

    public function verifyPhone(Request $request, PhoneVerifier $phone)
    {
        $data = $request->validate(['code' => 'required|string|max:10']);
        try {
            $phone->verify($request->user(), $data['code']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('ok', 'Téléphone vérifié. Vous pouvez faire des échanges.');
    }

    public function newChallenge(Request $request, KycService $kyc)
    {
        $request->user()->hasMany(\App\Models\KycChallenge::class)->whereNull('used_at')->update(['expires_at' => now()]);
        $kyc->challenge($request->user());

        return back()->with('ok', 'Nouveau code de sécurité généré.');
    }

    public function submitKyc(Request $request, KycService $kyc)
    {
        $max = (int) config('viratech.kyc.max_file_kb');
        $data = $request->validate([
            'id_type' => 'required|string',
            'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.$max,
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.$max,
            'id_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.$max,
        ], ['selfie.required' => 'Ajoutez votre selfie avec la pièce en main.', 'id_front.required' => 'Ajoutez la photo de votre pièce.']);

        try {
            $kyc->submit($request->user(), $data['id_type'], $request->file('selfie'), $request->file('id_front'), $request->file('id_back'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['selfie' => $e->getMessage()]);
        }

        return back()->with('ok', 'Dossier envoyé. Vous serez prévenu dès sa vérification.');
    }

    /** Photo de profil : visible par le propriétaire et par l'équipe. */
    public function avatarFile(Request $request, int $id)
    {
        abort_unless($request->user()->id === $id || $request->user()->isStaff(), 404);
        $user = \App\Models\User::findOrFail($id);
        abort_unless($user->avatar_path && Storage::exists($user->avatar_path), 404);

        return Storage::response($user->avatar_path);
    }
}