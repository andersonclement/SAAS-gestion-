@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <div class="card" style="max-width:420px;margin:3rem auto;">
        <h1 style="margin-top:0;">Connexion</h1>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <input id="password" type="password" name="password" required>
            </div>

            <button class="btn" type="submit">Se connecter</button>
        </form>

        <p style="margin-top:1.5rem;font-size:.9rem;">
            Vous êtes un nouveau patron ? <a href="{{ route('register') }}">Créer votre espace</a>
        </p>
    </div>
@endsection
