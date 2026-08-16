<?php

namespace App\Http\Controllers;

use App\Models\CodeAcces;
use App\Support\AccesPrivilegie;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Saisie, par le gérant, du code d'accès que lui a remis son patron : elle
 * ouvre pour la durée du code la possibilité de créer des produits ou
 * d'approvisionner le stock de sa boutique.
 */
class AccesPrivilegieController extends Controller
{
    public function create(): View
    {
        return view('acces-privilegie.create', [
            'codeActif' => AccesPrivilegie::actif(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = Auth::user();

        $code = CodeAcces::withoutGlobalScopes()
            ->where('code', strtoupper(trim($validated['code'])))
            ->first();

        // Message unique quelle que soit la raison du refus : un code inconnu,
        // expiré ou destiné à quelqu'un d'autre ne doivent pas se distinguer,
        // sans quoi la réponse devient un oracle pour deviner des codes.
        if ($code === null || ! $code->utilisablePar($user)) {
            throw ValidationException::withMessages([
                'code' => __("Ce code d'accès est invalide, expiré ou ne vous est pas destiné."),
            ]);
        }

        AccesPrivilegie::ouvrir($code);

        $code->active_le ??= now();
        $code->save();

        Journal::enregistrer('code_acces.ouvert', __(
            'Accès ouvert avec le code :code (:portee).',
            ['code' => $code->code, 'portee' => $code->portee->label()]
        ), $code->boutique_id);

        return redirect()->route('dashboard')->with('status', __(
            "Accès ouvert jusqu'au :echeance. Toutes vos opérations seront rattachées à ce code.",
            ['echeance' => $code->expire_le->format('d/m/Y H:i')]
        ));
    }

    public function destroy(): RedirectResponse
    {
        $code = AccesPrivilegie::actif();

        if ($code !== null) {
            Journal::enregistrer('code_acces.ferme', __('Accès refermé (code :code).', [
                'code' => $code->code,
            ]), $code->boutique_id);
        }

        AccesPrivilegie::fermer();

        return redirect()->route('dashboard')->with('status', __('Accès refermé.'));
    }
}
