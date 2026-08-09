<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Boutique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $utilisateurs = User::where('tenant_id', Auth::user()->tenant_id)
            ->with('boutique')
            ->orderBy('name')
            ->get();

        return view('users.index', compact('utilisateurs'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $boutiques = Boutique::orderBy('nom')->get();

        return view('users.create', compact('boutiques'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'tenant_id' => Auth::user()->tenant_id,
            'boutique_id' => $validated['boutique_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('status', 'Compte créé avec succès.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Compte supprimé.');
    }
}
