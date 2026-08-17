<?php

namespace App\Http\Controllers;

use App\Models\NotificationInterne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Centre de notifications : la pile de ce qui m'a été signalé et que je n'ai
 * pas encore traité.
 *
 * Chacun ne voit que ce qui lui est adressé. Un gérant reçoit les alertes de sa
 * boutique, le patron celles de toutes les siennes, le comptable les créances.
 * C'est le générateur qui a tranché à l'écriture — ici, on se contente de lire
 * ce qui porte son identifiant.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $utilisateur = Auth::user();

        // Par défaut on montre ce qui reste à traiter ; l'historique est à un
        // clic pour retrouver ce qu'on a déjà vu.
        $filtre = $request->query('filtre') === 'toutes' ? 'toutes' : 'a_traiter';

        $notifications = NotificationInterne::query()
            ->pour($utilisateur)
            ->with('boutique')
            ->when($filtre === 'a_traiter', fn ($query) => $query->ouvertes())
            ->parUrgence()
            ->paginate(30)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filtre' => $filtre,
            'nombreATraiter' => NotificationInterne::pour($utilisateur)->ouvertes()->nonLues()->count(),
        ]);
    }

    /**
     * Marque une notification comme lue et ouvre l'écran où agir.
     *
     * Lire n'est pas résoudre : la notification reste ouverte, et reviendra en
     * rappel si la situation dure. C'est ce qui empêche de faire disparaître un
     * problème d'un simple clic.
     */
    public function lire(NotificationInterne $notification): RedirectResponse
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        $notification->marquerLue();

        return $notification->lien
            ? redirect($notification->lien)
            : back();
    }

    public function toutLire(): RedirectResponse
    {
        NotificationInterne::pour(Auth::user())
            ->ouvertes()
            ->nonLues()
            ->update(['lu_le' => now()]);

        return back()->with('status', __('Toutes les notifications ont été marquées comme lues.'));
    }
}
