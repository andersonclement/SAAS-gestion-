<?php

namespace App\Http\Controllers;

use App\Enums\PorteeCodeAcces;
use App\Enums\UserRole;
use App\Http\Requests\StoreCodeAccesRequest;
use App\Models\CodeAcces;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Délivrance et suivi, côté patron, des codes d'accès confiés aux gérants.
 */
class CodeAccesController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CodeAcces::class);

        $codes = CodeAcces::with(['destinataire', 'boutique'])
            ->withCount('operations')
            ->latest()
            ->paginate(25);

        return view('codes-acces.index', [
            'codes' => $codes,
            'gerants' => $this->gerants(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CodeAcces::class);

        return view('codes-acces.create', [
            'gerants' => $this->gerants(),
            'portees' => PorteeCodeAcces::cases(),
        ]);
    }

    public function store(StoreCodeAccesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $gerant = User::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($validated['destinataire_user_id']);

        $code = CodeAcces::create([
            'boutique_id' => $gerant->boutique_id,
            'portee' => $validated['portee'],
            'genere_par_user_id' => $request->user()->id,
            'destinataire_user_id' => $gerant->id,
            'expire_le' => now()->addHours((int) $validated['duree_heures']),
            'motif' => $validated['motif'] ?? null,
        ]);

        Journal::enregistrer('code_acces.delivre', __(
            "Code d'accès :code délivré à :gerant (:portee), valable jusqu'au :echeance.",
            [
                'code' => $code->code,
                'gerant' => $gerant->name,
                'portee' => $code->portee->label(),
                'echeance' => $code->expire_le->format('d/m/Y H:i'),
            ]
        ), $code->boutique_id);

        return redirect()->route('codes-acces.show', $code)
            ->with('status', __('Code :code créé. Communiquez-le à :gerant.', [
                'code' => $code->code,
                'gerant' => $gerant->name,
            ]));
    }

    public function show(CodeAcces $codeAcces): View
    {
        $this->authorize('view', $codeAcces);

        $codeAcces->load(['destinataire', 'boutique', 'generePar']);

        return view('codes-acces.show', [
            'code' => $codeAcces,
            'operations' => $codeAcces->operations()->with('boutique')->paginate(30),
        ]);
    }

    /**
     * Coupe l'accès sans attendre l'échéance. Le contrôle est refait à chaque
     * requête du gérant : sa session privilégiée se ferme aussitôt.
     */
    public function revoquer(CodeAcces $codeAcces): RedirectResponse
    {
        $this->authorize('revoquer', $codeAcces);

        $codeAcces->update(['revoque_le' => now()]);

        Journal::enregistrer('code_acces.revoque', __("Code d'accès :code révoqué.", [
            'code' => $codeAcces->code,
        ]), $codeAcces->boutique_id);

        return redirect()->route('codes-acces.show', $codeAcces)
            ->with('status', __('Code révoqué.'));
    }

    /**
     * @return Collection<int, User>
     */
    private function gerants()
    {
        return User::where('tenant_id', Auth::user()->tenant_id)
            ->where('role', UserRole::Gerant)
            ->where('actif', true)
            ->with('boutique')
            ->orderBy('name')
            ->get();
    }
}
