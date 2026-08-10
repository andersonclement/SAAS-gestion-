@extends('layouts.app')

@section('title', __('Équipe'))

@section('content')
    <h1>{{ __('Équipe') }}</h1>

    <div class="card">
        @if ($utilisateurs->isEmpty())
            <p>{{ __('Aucun utilisateur.') }}</p>
        @else
            <table>
                <thead><tr><th>{{ __('Nom') }}</th><th>{{ __('E-mail') }}</th><th>{{ __('Rôle') }}</th><th>{{ __('Boutique') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($utilisateurs as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->role->label() }}</td>
                            <td>{{ $u->boutique?->nom ?? '—' }}</td>
                            <td>
                                @can('delete', $u)
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('{{ __('Supprimer ce compte ?') }}');" style="display:inline">
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
        @endif

        @can('create', App\Models\User::class)
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('users.create') }}">+ {{ __('Nouveau compte') }}</a></p>
        @endcan
    </div>
@endsection
