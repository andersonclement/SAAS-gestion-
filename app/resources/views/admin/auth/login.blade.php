@extends('layouts.admin')

@section('title', __('Connexion superadmin'))

@section('content')
    <div class="card" style="max-width:400px;">
        <h1 style="margin-top:0;font-size:1.3rem;">{{ config('app.name') }} — {{ __('Espace superadmin') }}</h1>

        @if ($errors->any())
            <div class="errors">
                <ul style="margin:0;padding-left:1.2rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('Adresse e-mail') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">{{ __('Mot de passe') }}</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button class="btn" type="submit" style="width:100%;">{{ __('Se connecter') }}</button>
        </form>
    </div>
@endsection
