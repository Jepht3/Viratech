<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyAccount;
use App\Models\Corridor;
use App\Models\User;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function fees()
    {
        return view('admin.fees', ['corridors' => Corridor::with('tiers')->orderBy('sort')->get()]);
    }

    /** Modifie minimum, frais fixe, délais et paliers de pourcentage d'un échange. Tout est journalisé. */
    public function updateFees(Request $request, Corridor $corridor)
    {
        $data = $request->validate([
            'min_amount' => 'required|numeric|min:0',
            'fixed_fee' => 'required|numeric|min:0',
            'eta_min_minutes' => 'required|integer|min:1',
            'eta_max_minutes' => 'required|integer|gte:eta_min_minutes',
            'is_active' => 'nullable|boolean',
            'hold_days' => 'nullable|numeric|min:0|max:180',
            'tiers' => 'required|array',
            'tiers.*.min_amount' => 'nullable|numeric|min:0',
            'tiers.*.percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $tiers = collect($data['tiers'])->filter(fn ($t) => isset($t['min_amount'], $t['percent']) && $t['min_amount'] !== '' && $t['percent'] !== '')->values();
        if ($tiers->isEmpty()) {
            return back()->withErrors(['tiers' => 'Définissez au moins un palier de pourcentage.']);
        }

        $old = ['min' => $corridor->min_amount, 'fixed' => $corridor->fixed_fee, 'tiers' => $corridor->tiers->map->only('min_amount', 'percent')->all()];

        $corridor->update([
            'min_amount' => $data['min_amount'], 'fixed_fee' => $data['fixed_fee'],
            'eta_min_minutes' => $data['eta_min_minutes'], 'eta_max_minutes' => $data['eta_max_minutes'],
            'hold_minutes' => isset($data['hold_days']) ? (int) round($data['hold_days'] * 1440) : $corridor->hold_minutes,
            'is_active' => $corridor->coming_soon ? false : $request->boolean('is_active'),
        ]);
        $corridor->tiers()->delete();
        foreach ($tiers as $t) {
            $corridor->tiers()->create(['min_amount' => $t['min_amount'], 'percent' => $t['percent']]);
        }

        AuditLog::record($request->user(), 'fees.updated', $corridor, $old, $data);

        return back()->with('ok', 'Barème « '.$corridor->label.' » enregistré. Il s\'applique aux nouvelles commandes.');
    }

    public function accounts()
    {
        return view('admin.accounts', ['accounts' => CompanyAccount::orderBy('id')->get()]);
    }

    public function updateAccount(Request $request, CompanyAccount $account)
    {
        $data = $request->validate(['account_value' => 'required|string|max:190', 'holder_name' => 'nullable|string|max:120', 'label' => 'required|string|max:80']);
        $old = $account->only('account_value', 'holder_name', 'label');
        $account->update($data + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record($request->user(), 'company_account.updated', $account, $old, $data);

        return back()->with('ok', 'Compte de réception enregistré.');
    }

    public function clients()
    {
        return view('admin.clients', ['clients' => User::where('role', 'client')->withCount('orders')->latest()->paginate(30)]);
    }

    public function setKyc(Request $request, User $user)
    {
        $data = $request->validate(['kyc_level' => 'required|integer|between:0,3']);
        $old = ['kyc_level' => $user->kyc_level];
        $user->update($data);
        AuditLog::record($request->user(), 'kyc.updated', $user, $old, $data);

        return back()->with('ok', 'Niveau de vérification mis à jour.');
    }

    public function audit()
    {
        return view('admin.audit', ['logs' => AuditLog::latest('id')->paginate(50)]);
    }
}

