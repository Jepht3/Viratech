<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use App\Models\Order;
use App\Services\AdminSettings;
use App\Services\KycService;
use App\Services\OrderWorkflow;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Vérifications d'identité, versement FlexPay, délai de sécurité et paramètres, pour l'application Viratech Admin. */
class AdminExtrasApiController extends Controller
{
    public function __construct(private OrderWorkflow $workflow) {}

    // ── Vérifications d'identité ──

    public function kycList()
    {
        $row = fn (KycSubmission $s) => ['id' => $s->id, 'status' => $s->status, 'status_label' => $s->statusLabel(), 'id_type' => $s->idTypeLabel(), 'submitted_at' => $s->created_at->toIso8601String(), 'client' => ['id' => $s->user->id, 'name' => $s->user->name, 'phone' => $s->user->phone], 'flags' => $s->flags ?? []];

        return [
            'pending' => KycSubmission::with('user')->where('status', 'pending')->oldest()->get()->map($row)->values(),
            'done' => KycSubmission::with('user')->where('status', '!=', 'pending')->latest('reviewed_at')->limit(30)->get()->map($row)->values(),
        ];
    }

    public function kycShow(KycSubmission $submission)
    {
        $submission->load('user');
        $dup = KycSubmission::where('user_id', '!=', $submission->user_id)->where(fn ($q) => $q->where('selfie_hash', $submission->selfie_hash)->orWhere('id_front_hash', $submission->id_front_hash))->with('user')->get();

        return [
            'id' => $submission->id, 'status' => $submission->status, 'status_label' => $submission->statusLabel(), 'id_type' => $submission->idTypeLabel(),
            'challenge_code' => $submission->challenge_code, 'flags' => $submission->flags ?? [], 'has_back' => (bool) $submission->id_back_path,
            'submitted_at' => $submission->created_at->toIso8601String(), 'rejection_reason' => $submission->rejection_reason,
            'client' => Present::user($submission->user), 'same_photos_other_accounts' => $dup->map(fn ($d) => $d->user->name)->values(),
        ];
    }

    public function kycFile(KycSubmission $submission, string $type)
    {
        $path = ['selfie' => $submission->selfie_path, 'front' => $submission->id_front_path, 'back' => $submission->id_back_path][$type] ?? null;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path);
    }

    public function kycApprove(Request $request, KycSubmission $submission, KycService $kyc)
    {
        try {
            $kyc->approve($submission, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['ok' => true];
    }

    public function kycReject(Request $request, KycSubmission $submission, KycService $kyc)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        try {
            $kyc->reject($submission, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['ok' => true];
    }

    // ── Versement FlexPay et délai de sécurité ──

    public function flexpayPayout(Request $request, string $reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        try {
            $order = $this->workflow->payoutViaFlexpay($order, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order, true, true);
    }

    public function simulatePayout(string $reference)
    {
        abort_unless(config('viratech.simulate_paypal'), 404);
        $order = $this->workflow->confirmFlexpayPayout(Order::where('reference', $reference)->firstOrFail(), trustSimulation: true);

        return Present::order($order, true, true);
    }

    public function releaseHold(Request $request, string $reference)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $order = Order::where('reference', $reference)->firstOrFail();
        try {
            $this->workflow->releaseHold($order, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order->fresh(), true, true);
    }

    // ── Paramètres (administrateur) ──

    public function settings(AdminSettings $settings)
    {
        return $settings->view();
    }

    public function saveSettings(Request $request, AdminSettings $settings)
    {
        $request->validate(['flexpay_environment' => 'nullable|in:live,sandbox', 'mail_from_address' => 'nullable|email']);
        $settings->save($request->all());

        return $settings->view();
    }

    public function testMail(Request $request)
    {
        try {
            Mail::raw('Email de test Viratech : l\'envoi des emails fonctionne.', fn ($m) => $m->to($request->user()->email)->subject('Viratech · email de test'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Échec de l\'envoi : '.$e->getMessage()], 422);
        }

        return ['ok' => true];
    }
}