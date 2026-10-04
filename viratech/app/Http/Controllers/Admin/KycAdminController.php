<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use App\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Examen des dossiers d'identité par l'équipe (opérateurs et administrateurs). */
class KycAdminController extends Controller
{
    public function index()
    {
        return view('admin.verifications', ['pending' => KycSubmission::with('user')->where('status', 'pending')->oldest()->get(), 'done' => KycSubmission::with('user', 'reviewer')->where('status', '!=', 'pending')->latest('reviewed_at')->limit(30)->get()]);
    }

    public function show(KycSubmission $submission)
    {
        $submission->load('user');
        $others = KycSubmission::where('user_id', '!=', $submission->user_id)->where(fn ($q) => $q->where('selfie_hash', $submission->selfie_hash)->orWhere('id_front_hash', $submission->id_front_hash))->with('user')->get();

        return view('admin.verification', ['s' => $submission, 'others' => $others]);
    }

    public function file(KycSubmission $submission, string $type)
    {
        $path = ['selfie' => $submission->selfie_path, 'front' => $submission->id_front_path, 'back' => $submission->id_back_path][$type] ?? null;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path);
    }

    public function approve(Request $request, KycSubmission $submission, KycService $kyc)
    {
        try {
            $kyc->approve($submission, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect('/admin/verifications')->with('ok', 'Identité vérifiée. Le plafond du client est mis à jour.');
    }

    public function reject(Request $request, KycSubmission $submission, KycService $kyc)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        try {
            $kyc->reject($submission, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect('/admin/verifications')->with('ok', 'Dossier refusé, le client est prévenu.');
    }
}