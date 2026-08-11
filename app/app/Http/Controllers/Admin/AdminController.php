<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Gestion des comptes superadmin eux-mêmes : la plateforme n'a pas
 * d'inscription publique pour ce rôle, seul un admin déjà connecté peut en
 * créer un autre.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        $admins = Admin::orderBy('name')->get();

        return view('admin.admins.index', compact('admins'));
    }

    public function create(): View
    {
        return view('admin.admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.admins.index')->with('status', __('Administrateur créé avec succès.'));
    }

    public function destroy(Admin $admin): RedirectResponse
    {
        abort_if($admin->id === Auth::guard('admin')->id(), 422, __('Vous ne pouvez pas supprimer votre propre compte.'));

        $admin->delete();

        return redirect()->route('admin.admins.index')->with('status', __('Administrateur supprimé.'));
    }
}
