<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountController extends Controller
{
    // GET /account (editar)
    public function edit(Request $request)
    {
        return Inertia::render('Auth/EditAccount', [
            'title' => 'Editar conta',
            // você já tem auth.user via middleware Inertia; enviar é opcional
            //'user'  => $request->user()->only('name', 'email'),
        ]);
    }

    // PUT /account (atualizar)
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'email'    => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        return back()->with('success', 'Dados atualizados com sucesso.');
        //return back()->with('success', 'Conta atualizada com sucesso.');
    }
}
