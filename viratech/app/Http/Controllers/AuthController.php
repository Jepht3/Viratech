<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt([...$data, 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email ou mot de passe incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect(Auth::user()->isStaff() ? '/admin' : '/tableau-de-bord');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([...$data, 'role' => 'client']); // le rôle n'est jamais pris depuis la requête
        Auth::login($user);
        $request->session()->regenerate();

        try {
            app(\App\Services\EmailVerifier::class)->send($user);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect('/profil')->with('ok', 'Compte créé. Entrez le code reçu par email pour pouvoir faire des échanges.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }
}
