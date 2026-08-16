@extends('layouts.app')

@section('title', __('Clients'))

@section('content')
    <div class="card">
        <form method="GET" class="filtres">
            <div class="field" style="margin-bottom:0;flex:1;">
                <label for="q">{{ __('Rechercher') }}</label>
                <input id="q" type="text" name="q" value="{{ $recherche }}" placeholder="{{ __('Nom ou téléphone') }}">
            </div>
            <button class="btn" type="submit">{{ __('Rechercher') }}</button>
        </form>
    </div>
    <h1>{{ __('Clients') }}</h1>

    <div class="card">
        @if ($clients->isEmpty())
            <p>{{ __('Aucun client pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Téléphone') }}</th>
                        <th>{{ __('Plafond de crédit') }}</th>
                        <th>{{ __('Solde dû') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr>
                            <td>{{ $client->nom }}</td>
                            <td>{{ $client->type->label() }}</td>
                            <td>{{ $client->telephone ?? '—' }}</td>
                            <td>{{ $client->plafond_credit !== null ? number_format($client->plafond_credit, 0, ',', ' ').' FCFA' : '—' }}</td>
                            <td>
                                @php($soldeDette = $client->soldeDette())
                                <span @if ($soldeDette > 0) style="color:#7a1f1f;font-weight:600;" @endif>
                                    {{ number_format($soldeDette, 0, ',', ' ') }} FCFA
                                </span>
                            </td>
                            <td><a href="{{ route('clients.show', $client) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div style="margin-top:1rem;">{{ $clients->links() }}</div>

        @if (! auth()->user()->isComptable())
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('clients.create') }}">+ {{ __('Nouveau client') }}</a></p>
        @endif
    </div>
@endsection
