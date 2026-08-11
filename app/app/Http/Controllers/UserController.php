<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Boutique;
use App\Models\User;
use App\Support\Journal;
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

        $user = User::create([
            'tenant_id' => Auth::user()->tenant_id,
            'boutique_id' => $validated['boutique_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        Journal::enregistrer('utilisateur.cree', __('Compte créé pour :nom (:role).', [
            'nom' => $user->name,
            'role' => $user->role->label(),
        ]), $user->boutique_id);

        return redirect()->route('users.index')->with('status', __('Compte créé avec succès.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $nom = $user->name;
        $boutiqueId = $user->boutique_id;

        $user->delete();

        Journal::enregistrer('utilisateur.supprime', __('Compte de :nom supprimé.', ['nom' => $nom]), $boutiqueId);

        return redirect()->route('users.index')->with('status', __('Compte supprimé.'));
    }
}
