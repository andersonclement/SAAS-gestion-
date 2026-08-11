<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Support\CentreAlertes;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AlerteController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $boutiqueIds = $user->effectiveBoutiqueId()
            ? collect([$user->effectiveBoutiqueId()])
            : Boutique::pluck('id');

        return view('alertes.index', CentreAlertes::pour($boutiqueIds));
    }
}
