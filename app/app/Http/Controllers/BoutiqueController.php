<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreBoutiqueRequest;
use App\Http\Requests\UpdateBoutiqueRequest;
use App\Models\Boutique;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class BoutiqueController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Boutique::class);

        $user = Auth::user();

        $boutiques = $user->effectiveBoutiqueId()
            ? Boutique::withCount('utilisateurs')->whereKey($user->effectiveBoutiqueId())->get()
            : Boutique::withCount('utilisateurs')->orderBy('nom')->get();

        return view('boutiques.index', compact('boutiques'));
    }

    public function create(): View
    {
        $this->authorize('create', Boutique::class);

        return view('boutiques.create', [
            'gerantsDisponibles' => $this->gerantsSansBoutique(),
        ]);
    }

    /**
     * Crée la boutique et, dans la même opération, lui rattache son gérant :
     * c'est ce rattachement qui donne au gérant l'accès à cette boutique et
     * à elle seule.
     */
    public function store(StoreBoutiqueRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $boutique = DB::transaction(function () use ($validated, $request) {
            $boutique = Boutique::create([
                'nom' => $validated['nom'],
                'adresse' => $validated['adresse'],
                'telephone' => $validated['telephone'] ?? null,
            ]);

            Journal::enregistrer('boutique.creee', __('Boutique :nom créée (:adresse).', [
                'nom' => $boutique->nom,
                'adresse' => $boutique->adresse,
            ]), $boutique->id);

            $this->rattacherGerant($boutique, $validated, $request);

            return $boutique;
        });

        return redirect()->route('boutiques.show', $boutique)->with('status', __('Boutique créée avec succès.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function rattacherGerant(Boutique $boutique, array $validated, StoreBoutiqueRequest $request): void
    {
        if (! empty($validated['gerant_id'])) {
            $gerant = User::where('tenant_id', $request->user()->tenant_id)->findOrFail($validated['gerant_id']);
            $gerant->update(['boutique_id' => $boutique->id]);
        } elseif (! empty($validated['gerant_nom'])) {
            $gerant = User::create([
                'tenant_id' => $request->user()->tenant_id,
                'boutique_id' => $boutique->id,
                'name' => $validated['gerant_nom'],
                'email' => $validated['gerant_email'],
                'password' => Hash::make($validated['gerant_mot_de_passe']),
                'role' => UserRole::Gerant,
            ]);
        } else {
            return;
        }

        Journal::enregistrer('boutique.gerant_rattache', __('Gérant :nom rattaché à la boutique :boutique.', [
            'nom' => $gerant->name,
            'boutique' => $boutique->nom,
        ]), $boutique->id);
    }

    /**
     * Gérants du tenant qui n'ont pas encore de boutique : les seuls que l'on
     * puisse rattacher sans retirer à une autre boutique son responsable.
     *
     * @return Collection<int, User>
     */
    private function gerantsSansBoutique()
    {
        return User::where('tenant_id', Auth::user()->tenant_id)
            ->where('role', UserRole::Gerant)
            ->whereNull('boutique_id')
            ->orderBy('name')
            ->get();
    }

    public function show(Boutique $boutique): View
    {
        $this->authorize('view', $boutique);

        $boutique->load('utilisateurs');

        return view('boutiques.show', compact('boutique'));
    }

    public function edit(Boutique $boutique): View
    {
        $this->authorize('update', $boutique);

        return view('boutiques.edit', compact('boutique'));
    }

    public function update(UpdateBoutiqueRequest $request, Boutique $boutique): RedirectResponse
    {
        $boutique->update($request->validated());

        Journal::enregistrer('boutique.modifiee', __('Boutique :nom modifiée.', ['nom' => $boutique->nom]), $boutique->id);

        return redirect()->route('boutiques.show', $boutique)->with('status', __('Boutique mise à jour.'));
    }
}
