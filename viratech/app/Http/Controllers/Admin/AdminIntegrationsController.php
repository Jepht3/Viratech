<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AdminSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Paramètres de l'administrateur : FlexPay, emails (Resend), notifications push (Google / Firebase). */
class AdminIntegrationsController extends Controller
{
    public function show(AdminSettings $settings)
    {
        return view('admin.settings', ['s' => $settings->view()]);
    }

    public function save(Request $request, AdminSettings $settings)
    {
        $request->validate([
            'flexpay_environment' => 'nullable|in:live,sandbox', 'mail_from_address' => 'nullable|email',
            'push_fcm_service_account' => ['nullable', 'string', function ($attr, $value, $fail) {
                if (filled($value) && ! is_array(json_decode($value, true))) {
                    $fail('Le fichier du compte de service doit être le contenu JSON téléchargé depuis Google.');
                }
            }],
        ]);
        $settings->save($request->except(['_token']));
        AuditLog::record($request->user(), 'settings.updated', null, null, ['sections' => array_keys(array_filter($request->only(['flexpay_merchant', 'mail_from_address', 'push_fcm_project_id'])))]);

        return back()->with('ok', 'Paramètres enregistrés.');
    }

    public function testMail(Request $request)
    {
        try {
            Mail::raw('Ceci est un email de test envoyé depuis Viratech. Si vous le lisez, l\'envoi des emails fonctionne.', fn ($m) => $m->to($request->user()->email)->subject('Viratech · email de test'));
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Échec de l\'envoi : '.$e->getMessage()]);
        }

        return back()->with('ok', 'Email de test envoyé à '.$request->user()->email.'.');
    }
}