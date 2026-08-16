<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Inscription d'un nouveau patron : crée son espace tenant indépendant
 * (§4.1) et le compte utilisateur associé, avec le rôle "patron".
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $code = $request->codeActivation();

        $user = DB::transaction(function () use ($validated, $code) {
            $tenant = Tenant::create([
                'nom' => $validated['entreprise'],
                'email_contact' => $validated['email'],
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::Patron,
            ]);

            $code->activerPour($tenant, $user);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
