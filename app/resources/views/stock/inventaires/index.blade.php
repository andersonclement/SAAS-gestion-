@extends('layouts.app')

@section('title', __('Inventaires'))

@section('content')
    <h1>{{ __('Inventaires') }}</h1>

    <div class="card">
        @if ($inventaires->isEmpty())
            <p>{{ __('Aucun inventaire pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Boutique') }}</th><th>{{ __('Réalisé par') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($inventaires as $inventaire)
                        <tr>
                            <td>{{ $inventaire->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $inventaire->boutique->nom }}</td>
                            <td>{{ $inventaire->creePar->name }}</td>
                            <td><a href="{{ route('stock.inventaires.show', $inventaire) }}">{{ __('Voir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p style="margin-top:1rem;"><a class="btn" href="{{ route('stock.inventaires.create') }}">+ {{ __('Nouvel inventaire') }}</a></p>
    </div>
@endsection
