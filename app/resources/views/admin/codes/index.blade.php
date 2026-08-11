@extends('layouts.admin')

@section('title', __("Codes d'activation"))

@section('content')
    <h1 style="margin-top:0;">{{ __("Codes d'activation") }}</h1>

    <p>
        <a class="btn" href="{{ route('admin.codes.create') }}">+ {{ __('Générer un code') }}</a>
    </p>

    <div class="card">
        <div style="margin-bottom:1rem;">
            <a href="{{ route('admin.codes.index') }}" style="margin-right:1rem;font-weight:{{ $statut ? 400 : 700 }};">{{ __('Tous') }}</a>
            @foreach (\App\Enums\StatutCodeActivation::cases() as $s)
                <a href="{{ route('admin.codes.index', ['statut' => $s->value]) }}" style="margin-right:1rem;font-weight:{{ $statut === $s->value ? 700 : 400 }};">{{ $s->label() }}</a>
            @endforeach
        </div>

        @if ($codes->isEmpty())
            <p>{{ __('Aucun code trouvé.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Formule') }}</th>
                        <th>{{ __('Durée') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Généré par') }}</th>
                        <th>{{ __('Généré le') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($codes as $code)
                        <tr>
                            <td><code class="code-pill">{{ $code->code }}</code></td>
                            <td>{{ $code->plan->label() }}</td>
                            <td>{{ __(':n mois', ['n' => $code->duree_mois]) }}</td>
                            <td>{{ $code->statut->label() }}</td>
                            <td>{{ $code->tenant?->nom ?? __('— disponible —') }}</td>
                            <td>{{ $code->generePar->name }}</td>
                            <td>{{ $code->created_at->format('d/m/Y') }}</td>
                            <td>
                                @if ($code->estUtilisable())
                                    <form method="POST" action="{{ route('admin.codes.revoquer', $code) }}" onsubmit="return confirm('{{ __('Révoquer ce code ?') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8rem;">{{ __('Révoquer') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:1rem;">{{ $codes->links() }}</div>
        @endif
    </div>
@endsection
