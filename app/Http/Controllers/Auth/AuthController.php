<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Models\User;

class AuthController extends Controller
{
    // GET /login
    public function showLogin()
    {
        return Inertia::render('Auth/Login', [
            'title' => 'Login',
        ]);
    }

    // POST /login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        if (FacadesAuth::attempt($credentials, true)) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard')); // ajuste a rota destino
        }

        return back()->withErrors([
            'email' => 'Credenciais inválidas.',
        ])->onlyInput('email');
    }

    // GET /register
    public function showRegister()
    {
        return Inertia::render('Auth/Createuser', [
            'title' => 'Criar conta',
        ]);
    }

    // POST /register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required','string','max:255'],
            'email'                 => ['required','email','max:255','unique:users,email'],
            'password'              => ['required','string','min:8','confirmed'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        FacadesAuth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard')); // ajuste se quiser
    }

    // POST /logout
    public function logout(Request $request)
    {
        FacadesAuth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login.show');
    }


}
