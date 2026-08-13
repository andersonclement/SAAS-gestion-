<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Boutique;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $recherche = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');
        $statut = (string) $request->query('statut', '');

        $utilisateurs = User::where('tenant_id', Auth::user()->tenant_id)
            ->with('boutique')
            ->when($recherche !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%")
            ))
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($statut === 'actif', fn ($query) => $query->where('actif', true))
            ->when($statut === 'inactif', fn ($query) => $query->where('actif', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', compact('utilisateurs', 'recherche', 'role', 'statut'));
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

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $boutiques = Boutique::orderBy('nom')->get();

        return view('users.edit', compact('user', 'boutiques'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $modifications = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'alertes_email' => $validated['alertes_email'] ?? false,
        ];

        // Le rôle et la boutique n'existent dans la requête que pour les
        // sous-comptes : la fiche du patron ne les propose pas.
        if (! $user->isPatron()) {
            $modifications['role'] = $validated['role'];
            $modifications['boutique_id'] = $validated['boutique_id'] ?? null;
        }

        if (! empty($validated['password'])) {
            $modifications['password'] = Hash::make($validated['password']);
        }

        $user->update($modifications);

        Journal::enregistrer('utilisateur.modifie', __('Compte de :nom modifié (:role).', [
            'nom' => $user->name,
            'role' => $user->role->label(),
        ]), $user->boutique_id);

        return redirect()->route('users.index')->with('status', __('Compte mis à jour.'));
    }

    /**
     * Active ou désactive un compte. Préférable à la suppression : un vendeur
     * parti reste rattaché à ses ventes passées, la traçabilité est conservée,
     * et le compte peut être réactivé au retour de la personne.
     */
    public function basculerActivation(User $user): RedirectResponse
    {
        $this->authorize('basculerActivation', $user);

        $user->update(['actif' => ! $user->actif]);

        Journal::enregistrer(
            $user->actif ? 'utilisateur.active' : 'utilisateur.desactive',
            $user->actif
                ? __('Compte de :nom réactivé.', ['nom' => $user->name])
                : __('Compte de :nom désactivé.', ['nom' => $user->name]),
            $user->boutique_id,
        );

        return redirect()->route('users.index')->with('status', $user->actif
            ? __('Compte réactivé.')
            : __('Compte désactivé : la personne ne peut plus se connecter.'));
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
