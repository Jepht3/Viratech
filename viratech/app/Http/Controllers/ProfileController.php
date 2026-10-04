<?php

namespace App\Http\Controllers;

use App\Models\KycSubmission;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerifier;
use App\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Profil : photo, email vérifié, vérification d'identité (pièce puis selfie avec la pièce), plafond. */
class ProfileController extends Controller
{
    public function show(Request $request, KycService $kyc)
    {
        $user = $request->user();
        $stage = $user->role === 'client' ? $kyc->stage($user) : 'none';

        return view('profile', [
            'user' => $user,
            'limit' => $user->role === 'client' ? $user->limitInfo() : null,
            'stage' => $stage,
            'submission' => $user->kycSubmissions()->latest('id')->first(),
            'challenge' => $stage === 'document' ? $kyc->challenge($user) : null,
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

    public function sendEmailCode(Request $request, EmailVerifier $email)
    {
        try {
            $dev = $email->send($request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['email_code' => $e->getMessage()]);
        }

        return back()->with('ok', 'Code envoyé à '.$request->user()->email.'.'.($dev ? ' (mode test : '.$dev.')' : ''));
    }

    public function verifyEmail(Request $request, EmailVerifier $email)
    {
        $data = $request->validate(['code' => 'required|string|max:10']);
        try {
            $email->verify($request->user(), $data['code']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['email_code' => $e->getMessage()]);
        }

        return back()->with('ok', 'Email vérifié. Vous pouvez faire des échanges (150 $ par mois tant que votre identité n\'est pas vérifiée).');
    }

    /** Étape 1 : la pièce d'identité, photographiée avec la caméra. */
    public function kycDocument(Request $request, KycService $kyc)
    {
        $max = (int) config('viratech.kyc.max_file_kb');
        $data = $request->validate([
            'id_type' => 'required|string',
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.$max,
            'id_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.$max,
        ], ['id_front.required' => 'Prenez la photo de votre pièce avec la caméra.']);
        try {
            $kyc->submitDocument($request->user(), $data['id_type'], $request->file('id_front'), $request->file('id_back'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['id_front' => $e->getMessage()]);
        }

        return back()->with('ok', 'Pièce reçue. Dernière étape : une photo de vous tenant cette pièce.');
    }

    public function newChallenge(Request $request, KycService $kyc)
    {
        $kyc->newChallenge($request->user());

        return back()->with('ok', 'Nouveau code de sécurité généré.');
    }

    /** Étape 2 : le selfie avec la pièce en main et le code écrit sur papier. */
    public function kycSelfie(Request $request, KycService $kyc)
    {
        $data = $request->validate(['selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.(int) config('viratech.kyc.max_file_kb')], ['selfie.required' => 'Prenez le selfie avec la caméra.']);
        try {
            $kyc->submitSelfie($request->user(), $request->file('selfie'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['selfie' => $e->getMessage()]);
        }

        return back()->with('ok', 'Dossier envoyé. Vous serez prévenu dès sa vérification.');
    }

    /** Photo de profil : visible par le propriétaire et par l'équipe. */
    public function avatarFile(Request $request, int $id)
    {
        abort_unless($request->user()->id === $id || $request->user()->isStaff(), 404);
        $user = User::findOrFail($id);
        abort_unless($user->avatar_path && Storage::exists($user->avatar_path), 404);

        return Storage::response($user->avatar_path);
    }
}