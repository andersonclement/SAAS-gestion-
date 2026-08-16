@extends('layouts.app')

@section('title', __('Équipe'))

@section('content')
    <h1>{{ __('Équipe') }}</h1>

    <div class="card">
        <form method="GET" class="filtres">
            <div class="field" style="margin-bottom:0;flex:1;">
                <label for="q">{{ __('Rechercher') }}</label>
                <input id="q" type="text" name="q" value="{{ $recherche }}" placeholder="{{ __('Nom ou e-mail') }}">
            </div>

            <div class="field" style="margin-bottom:0;">
                <label for="role">{{ __('Rôle') }}</label>
                <select id="role" name="role">
                    <option value="">— {{ __('Tous') }} —</option>
                    @foreach (\App\Enums\UserRole::cases() as $cas)
                        <option value="{{ $cas->value }}" @selected($role === $cas->value)>{{ $cas->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" style="margin-bottom:0;">
                <label for="statut">{{ __('Statut') }}</label>
                <select id="statut" name="statut">
                    <option value="">— {{ __('Tous') }} —</option>
                    <option value="actif" @selected($statut === 'actif')>{{ __('Actifs') }}</option>
                    <option value="inactif" @selected($statut === 'inactif')>{{ __('Désactivés') }}</option>
                </select>
            </div>

            <button class="btn" type="submit">{{ __('Filtrer') }}</button>
        </form>
    </div>

    <div class="card">
        @if ($utilisateurs->isEmpty())
            <p>{{ __('Aucun utilisateur.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('E-mail') }}</th>
                        <th>{{ __('Rôle') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Alertes e-mail') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($utilisateurs as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->role->label() }}</td>
                            <td>{{ $u->boutique?->nom ?? '—' }}</td>
                            <td>
                                @if ($u->actif)
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('Actif') }}</span>
                                @else
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ __('Désactivé') }}</span>
                                @endif
                            </td>
                            <td>
                                @if (in_array($u->role, [\App\Enums\UserRole::Patron, \App\Enums\UserRole::Gerant], true))
                                    {{ $u->alertes_email ? __('Oui') : __('Non') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                @can('update', $u)
                                    <a href="{{ route('users.edit', $u) }}">{{ __('Modifier') }}</a>
                                @endcan

                                @can('basculerActivation', $u)
                                    <form method="POST" action="{{ route('users.activation', $u) }}" style="display:inline"
                                          data-confirm="{{ $u->actif ? __('Désactiver ce compte ? La personne ne pourra plus se connecter.') : __('Réactiver ce compte ?') }}">
                                        @csrf
                                        <button class="btn" type="submit" style="background:{{ $u->actif ? '#8a5a00' : '#14532d' }};">
                                            {{ $u->actif ? __('Désactiver') : __('Réactiver') }}
                                        </button>
                                    </form>
                                @endcan

                                @can('delete', $u)
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" style="display:inline"
                                          data-confirm="{{ __('Supprimer définitivement ce compte ? Préférez la désactivation pour conserver la traçabilité.') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn" type="submit" style="background:#7a1f1f;">{{ __('Supprimer') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem;">{{ $utilisateurs->links() }}</div>
        @endif

        @can('create', App\Models\User::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('users.create') }}">+ {{ __('Nouveau compte') }}</a></p>
        @endcan
    </div>
@endsection
