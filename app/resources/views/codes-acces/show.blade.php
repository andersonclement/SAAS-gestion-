@extends('layouts.app')

@section('title', $code->code)

@section('content')
    <p><a href="{{ route('codes-acces.index') }}">&larr; {{ __("Codes d'accès") }}</a></p>
    <h1 style="margin-top:0;">{{ __("Code d'accès") }}</h1>

    <div class="card">
        <p style="font-size:1.5rem;font-weight:700;letter-spacing:.05em;margin:0 0 .5rem;">{{ $code->code }}</p>
        <p style="color:#56606b;margin-top:0;">{{ __('Communiquez ce code au gérant par SMS ou de vive voix ; il le saisit depuis son espace.') }}</p>

        <p><strong>{{ __('Gérant') }} :</strong> {{ $code->destinataire?->name ?? '—' }}</p>
        <p><strong>{{ __('Boutique') }} :</strong> {{ $code->boutique?->nom ?? '—' }}</p>
        <p><strong>{{ __('Portée') }} :</strong> {{ $code->portee->label() }} — {{ $code->portee->description() }}</p>
        <p><strong>{{ __('Échéance') }} :</strong> {{ $code->expire_le->format('d/m/Y H:i') }}</p>
        <p><strong>{{ __('Première utilisation') }} :</strong> {{ $code->active_le?->format('d/m/Y H:i') ?? __('Jamais utilisé') }}</p>
        <p><strong>{{ __('Délivré par') }} :</strong> {{ $code->generePar?->name ?? '—' }} — {{ $code->created_at->format('d/m/Y H:i') }}</p>
        @if ($code->motif)
            <p><strong>{{ __('Motif') }} :</strong> {{ $code->motif }}</p>
        @endif
        <p>
            <strong>{{ __('Statut') }} :</strong>
            @if ($code->estValide())
                <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ $code->statut() }}</span>
            @else
                <span class="badge" style="background:#f1f3f5;color:#56606b;">{{ $code->statut() }}</span>
            @endif
        </p>

        @can('revoquer', $code)
            <form method="POST" action="{{ route('codes-acces.revoquer', $code) }}"
                  data-confirm="{{ __("Révoquer ce code ? Le gérant perdra l'accès immédiatement.") }}">
                @csrf
                <button class="btn" type="submit" style="background:#7a1f1f;">{{ __('Révoquer le code') }}</button>
            </form>
        @endcan
    </div>

    <div class="card">
        <h2 style="margin-top:0;">{{ __('Opérations réalisées avec ce code') }}</h2>
        <p style="color:#56606b;font-size:.9rem;margin-top:-.5rem;">
            {{ __("Tout ce que le gérant a fait grâce à cette délégation, dans l'ordre.") }}
        </p>

        @if ($operations->isEmpty())
            <p>{{ __('Aucune opération pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Utilisateur') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Détail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($operations as $operation)
                        <tr>
                            <td>{{ $operation->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $operation->utilisateur_nom }}</td>
                            <td><span class="badge">{{ $operation->action }}</span></td>
                            <td>{{ $operation->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1rem;">{{ $operations->links() }}</div>
        @endif
    </div>
@endsection
