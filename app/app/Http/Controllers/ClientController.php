<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $clients = Client::orderBy('nom')->get();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        abort_if(auth()->user()->isComptable(), 403);

        return view('clients.create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        Client::create($request->validated());

        return redirect()->route('clients.index')->with('status', __('Client créé avec succès.'));
    }

    /**
     * Fiche client : plafond de crédit, solde de dette et échéancier des
     * ventes à crédit non soldées (§4.9).
     */
    public function show(Client $client): View
    {
        $ventesACredit = $client->ventes()
            ->with(['lignes', 'paiements', 'boutique'])
            ->whereNotNull('date_echeance')
            ->latest()
            ->get()
            ->filter(fn ($vente) => ! $vente->estSoldee());

        return view('clients.show', [
            'client' => $client,
            'ventesACredit' => $ventesACredit,
        ]);
    }
}
