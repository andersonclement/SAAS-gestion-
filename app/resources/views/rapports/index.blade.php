@extends('layouts.app')

@section('title', __('Rapports'))

@section('content')
    <h1>{{ __('Rapports') }}</h1>
    <p style="color:#555;margin-top:-.5rem;">{{ __('Exports CSV, ouvrables directement dans Excel ou LibreOffice.') }}</p>

    <div class="card">
        <p><a class="btn" href="{{ route('rapports.ventes') }}">{{ __('Exporter les ventes (CSV)') }}</a></p>
        <p><a class="btn" href="{{ route('rapports.achats') }}">{{ __('Exporter les achats (CSV)') }}</a></p>
        <p><a class="btn" href="{{ route('rapports.stock') }}">{{ __('Exporter le stock (CSV)') }}</a></p>
    </div>
@endsection
