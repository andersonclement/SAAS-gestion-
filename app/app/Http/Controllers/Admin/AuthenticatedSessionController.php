<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LimiteurConnexions;
use App\Support\SuiviConnexions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        LimiteurConnexions::verifier($credentials['email'], 'admin');

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            LimiteurConnexions::enregistrerEchec($credentials['email'], 'admin');
            SuiviConnexions::enregistrer($credentials['email'], false, 'admin');

            throw ValidationException::withMessages([
                'email' => __('Identifiants incorrects.'),
            ]);
        }

        LimiteurConnexions::reinitialiser($credentials['email'], 'admin');
        $request->session()->regenerate();

        SuiviConnexions::enregistrer($credentials['email'], true, 'admin');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
