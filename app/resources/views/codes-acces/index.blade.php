@extends('layouts.app')

@section('title', __("Codes d'accès"))

@section('content')
    <h1>{{ __("Codes d'accès") }}</h1>
    <p style="color:#56606b;">
        {{ __("Un code d'accès autorise un gérant, pour une durée limitée et sur sa seule boutique, à ajouter des produits ou à approvisionner le stock. Toutes ses opérations restent rattachées au code.") }}
    </p>

    <p><a class="btn" href="{{ route('codes-acces.create') }}">+ {{ __('Délivrer un code') }}</a></p>

    <div class="card">
        @if ($codes->isEmpty())
            <p>{{ __("Aucun code d'accès délivré pour le moment.") }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Gérant') }}</th>
                        <th>{{ __('Boutique') }}</th>
                        <th>{{ __('Portée') }}</th>
                        <th>{{ __('Échéance') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Opérations') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($codes as $code)
                        <tr>
                            <td><code>{{ $code->code }}</code></td>
                            <td>{{ $code->destinataire?->name ?? '—' }}</td>
                            <td>{{ $code->boutique?->nom ?? '—' }}</td>
                            <td>{{ $code->portee->label() }}</td>
                            <td>{{ $code->expire_le->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($code->estValide())
                                    <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ $code->statut() }}</span>
                                @else
                                    <span class="badge" style="background:#f1f3f5;color:#56606b;">{{ $code->statut() }}</span>
                                @endif
                            </td>
                            <td>{{ $code->operations_count }}</td>
                            <td><a href="{{ route('codes-acces.show', $code) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem;">{{ $codes->links() }}</div>
        @endif
    </div>
@endsection
