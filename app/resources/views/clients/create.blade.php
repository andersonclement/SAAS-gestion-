@extends('layouts.app')

@section('title', __('Nouveau client'))

@section('content')
    <h1>{{ __('Nouveau client') }}</h1>

    <div class="card" style="max-width:480px;">
        <form method="POST" action="{{ route('clients.store') }}">
            @csrf

            <div class="field">
                <label for="nom">{{ __('Nom') }}</label>
                <input id="nom" type="text" name="nom" value="{{ old('nom') }}" required autofocus>
            </div>

            <div class="field">
                <label for="type">{{ __('Type') }}</label>
                <select id="type" name="type" required>
                    @foreach (\App\Enums\TypeClient::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="telephone">{{ __('Téléphone') }}</label>
                <input id="telephone" type="text" name="telephone" value="{{ old('telephone') }}">
            </div>

            <div class="field">
                <label for="email">{{ __('E-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}">
            </div>

            <div class="field">
                <label for="adresse">{{ __('Adresse') }}</label>
                <input id="adresse" type="text" name="adresse" value="{{ old('adresse') }}">
            </div>

            <button class="btn" type="submit">{{ __('Créer le client') }}</button>
        </form>
    </div>
@endsection
