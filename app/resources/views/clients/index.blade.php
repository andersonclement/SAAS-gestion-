@extends('layouts.app')

@section('title', __('Clients'))

@section('content')
    <h1>{{ __('Clients') }}</h1>

    <div class="card">
        @if ($clients->isEmpty())
            <p>{{ __('Aucun client pour le moment.') }}</p>
        @else
            <table>
                <thead>
                    <tr><th>{{ __('Nom') }}</th><th>{{ __('Type') }}</th><th>{{ __('Téléphone') }}</th><th>{{ __('E-mail') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr>
                            <td>{{ $client->nom }}</td>
                            <td>{{ $client->type->label() }}</td>
                            <td>{{ $client->telephone ?? '—' }}</td>
                            <td>{{ $client->email ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (! auth()->user()->isComptable())
            <p style="margin-top:1rem;"><a class="btn" href="{{ route('clients.create') }}">+ {{ __('Nouveau client') }}</a></p>
        @endif
    </div>
@endsection
