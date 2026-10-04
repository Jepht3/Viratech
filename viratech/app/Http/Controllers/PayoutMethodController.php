<?php

namespace App\Http\Controllers;

use App\Models\PayoutMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutMethodController extends Controller
{
    public function index()
    {
        return view('client.methods', ['methods' => auth()->user()->payoutMethods()->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(PayoutMethod::KINDS))],
            'account_value' => 'required|string|max:120',
            'holder_name' => 'required|string|max:120',
            'label' => 'nullable|string|max:60',
        ]);

        if ($data['kind'] === 'paypal') {
            $request->validate(['account_value' => 'email']);
        }

        // Le nom du bénéficiaire doit correspondre à l'identité du client (contrôle de sécurité vérifié par l'opérateur).
        $request->user()->payoutMethods()->create($data);

        return back()->with('ok', 'Moyen de réception ajouté. Il sera vérifié lors de votre première opération.');
    }

    public function destroy(Request $request, PayoutMethod $method)
    {
        abort_unless($method->user_id === $request->user()->id, 404);
        $method->delete();

        return back()->with('ok', 'Moyen de réception supprimé.');
    }
}
