@extends('layouts.app')

@section('title', __('Fournisseurs'))

@section('content')
    <h1>{{ __('Fournisseurs') }}</h1>

    <div class="card">
        @if ($fournisseurs->isEmpty())
            <p>{{ __('Aucun fournisseur pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Téléphone') }}</th><th>{{ __('Adresse') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($fournisseurs as $fournisseur)
                        <tr>
                            <td>{{ $fournisseur->nom }}</td>
                            <td>{{ $fournisseur->contact ?? '—' }}</td>
                            <td>{{ $fournisseur->telephone ?? '—' }}</td>
                            <td>{{ $fournisseur->adresse ?? '—' }}</td>
                            <td>
                                @if (auth()->user()->isPatron() || auth()->user()->isGerant())
                                    <a href="{{ route('fournisseurs.edit', $fournisseur) }}">{{ __('Modifier') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (auth()->user()->isPatron() || auth()->user()->isGerant())
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('fournisseurs.create') }}">+ {{ __('Nouveau fournisseur') }}</a></p>
        @endif
    </div>
@endsection
