{{-- Coordonnées du service client, affichées partout où un utilisateur
     bloqué doit pouvoir joindre quelqu'un sans quitter l'application. --}}
@if (config('support.telephones'))
    <p class="service-client">
        {{ __('Un problème ? Service client') }} :
        @foreach (config('support.telephones') as $numero)
            <a href="tel:{{ preg_replace('/\s+/', '', $numero) }}">{{ $numero }}</a>@if (! $loop->last) / @endif
        @endforeach
        @if (config('support.email'))
            — <a href="mailto:{{ config('support.email') }}">{{ config('support.email') }}</a>
        @endif
    </p>
@endif
