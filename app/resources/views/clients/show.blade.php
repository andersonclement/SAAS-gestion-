@extends('layouts.app')

@section('title', $client->nom)

@section('content')
    <h1>{{ $client->nom }}</h1>

    <div class="card">
        <p><strong>{{ __('Type') }} :</strong> {{ $client->type->label() }}</p>
        <p><strong>{{ __('Téléphone') }} :</strong> {{ $client->telephone ?? '—' }}</p>
        <p><strong>{{ __('E-mail') }} :</strong> {{ $client->email ?? '—' }}</p>
        <p><strong>{{ __('Plafond de crédit') }} :</strong> {{ $client->plafond_credit !== null ? number_format($client->plafond_credit, 0, ',', ' ').' FCFA' : __('Non autorisé') }}</p>
        <p><strong>{{ __('Solde dû') }} :</strong> {{ number_format($client->soldeDette(), 0, ',', ' ') }} FCFA</p>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Échéancier des ventes à crédit') }}</h2>
        @if ($ventesACredit->isEmpty())
            <p>{{ __('Aucune vente à crédit en cours.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Numéro') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Échéance') }}</th>
                        <th>{{ __('Montant dû') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ventesACredit as $vente)
                        <tr>
                            <td>{{ $vente->numero }}</td>
                            <td>{{ $vente->boutique->nom }}</td>
                            <td>
                                {{ $vente->date_echeance->format('d/m/Y') }}
                                @if ($vente->estEnRetard())
                                    <span class="badge" style="background:#fdecea;color:#611a15;">{{ __('En retard') }}</span>
                                @endif
                            </td>
                            <td>{{ number_format($vente->montantDu(), 0, ',', ' ') }} FCFA</td>
                            <td><a href="{{ route('ventes.show', $vente) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
