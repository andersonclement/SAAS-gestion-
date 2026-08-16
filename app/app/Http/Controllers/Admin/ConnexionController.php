<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TentativeConnexion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Surveillance du trafic de connexion de la plateforme : toutes les
 * tentatives (réussies ou non), tenant comme superadmin, avec IP et
 * appareil — au-delà du Journal d'activité qui ne trace que les actions
 * d'un utilisateur déjà authentifié.
 */
class ConnexionController extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->query('statut');
        $recherche = trim((string) $request->query('q'));

        $tentatives = TentativeConnexion::with(['tenant', 'user'])
            ->when($statut === 'reussie', fn ($query) => $query->where('reussie', true))
            ->when($statut === 'echouee', fn ($query) => $query->where('reussie', false))
            ->when($recherche !== '', fn ($query) => $query->where('email', 'like', "%{$recherche}%"))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.connexions.index', compact('tentatives', 'statut', 'recherche'));
    }
}
