@extends('layouts.admin')

@section('title', __('Ventes') . ' — ' . $tenant->nom)

@section('content')
    <p><a href="{{ route('admin.tenants.show', $tenant) }}">&larr; {{ $tenant->nom }}</a></p>
    <h1 style="margin-top:0;">{{ __('Ventes') }} — {{ $tenant->nom }}</h1>

    <div class="card">
        @if ($ventes->isEmpty())
            <p>{{ __('Aucune vente pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Numéro') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Vendeur') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Payé') }}</th>
                        <th>{{ __('Solde dû') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ventes as $vente)
                        <tr>
                            <td>{{ $vente->numero }}</td>
                            <td>{{ $vente->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $vente->boutique->nom }}</td>
                            <td>{{ $vente->vendeur->name }}</td>
                            <td>{{ $vente->client?->nom ?? __('Client de passage') }}</td>
                            <td>{{ number_format($vente->montantTotal(), 0, ',', ' ') }} FCFA</td>
                            <td>{{ number_format($vente->montantPaye(), 0, ',', ' ') }} FCFA</td>
                            <td>
                                @if ($vente->montantDu() > 0)
                                    <span class="badge" style="background:#fdecea;color:#7a1f1f;">{{ number_format($vente->montantDu(), 0, ',', ' ') }} FCFA</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:1rem;">{{ $ventes->links() }}</div>
        @endif
    </div>
@endsection
