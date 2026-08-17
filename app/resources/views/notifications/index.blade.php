@extends('layouts.app')

@section('title', __('Notifications'))

@section('content')
    <h1>{{ __('Notifications') }}</h1>

    <div class="card">
        <p style="margin-top:0;color:#555;">
            {{ __("Ce qui vous a été signalé et reste à traiter. Une notification lue mais dont la situation persiste vous revient en rappel ; elle se referme d'elle-même dès que le problème est réglé.") }}
        </p>

        <p>
            <a class="btn" style="background:{{ $filtre === 'a_traiter' ? '#1e4620' : '#555' }};"
               href="{{ route('notifications.index') }}">{{ __('À traiter') }} ({{ $nombreATraiter }})</a>
            <a class="btn" style="background:{{ $filtre === 'toutes' ? '#1e4620' : '#555' }};"
               href="{{ route('notifications.index', ['filtre' => 'toutes']) }}">{{ __('Tout l\'historique') }}</a>

            @if ($nombreATraiter > 0)
                <form method="POST" action="{{ route('notifications.tout-lire') }}" style="display:inline;">
                    @csrf
                    <button class="btn" type="submit" style="background:#555;">{{ __('Tout marquer comme lu') }}</button>
                </form>
            @endif
        </p>
    </div>

    @if ($notifications->isEmpty())
        <div class="card">
            <p style="margin:0;">
                {{ $filtre === 'a_traiter'
                    ? __('Rien à traiter. Tout est en ordre sur votre périmètre.')
                    : __('Aucune notification enregistrée pour le moment.') }}
            </p>
        </div>
    @else
        @foreach ($notifications as $notification)
            <div class="card" style="border-left:4px solid {{ $notification->importance->couleur() }};{{ $notification->estLue() ? 'opacity:.72;' : '' }}">
                <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <strong>{{ $notification->titre }}</strong>
                        <span class="badge" style="background:{{ $notification->importance->couleur() }};color:#fff;">
                            {{ $notification->type->label() }}
                        </span>
                        @if ($notification->estRappel())
                            {{-- Le compteur de rappels dit ce que la date ne dit pas :
                                 depuis combien de fois ce problème est signalé sans suite. --}}
                            <span class="badge" style="background:#7a1f1f;color:#fff;">
                                {{ trans_choice('{1}1 rappel|[2,*]:count rappels', $notification->nombre_rappels) }}
                            </span>
                        @endif
                        @if ($notification->resolue_le)
                            <span class="badge" style="background:#e6f4ea;color:#1e4620;">{{ __('réglé') }}</span>
                        @endif
                        <p style="margin:.4rem 0 0;">{{ $notification->message }}</p>
                        <small style="color:#555;">
                            {{ $notification->boutique?->nom ?? __('Compte') }} —
                            {{ $notification->updated_at->format('d/m/Y H:i') }}
                        </small>
                    </div>

                    @if (! $notification->estLue())
                        <form method="POST" action="{{ route('notifications.lire', $notification) }}">
                            @csrf
                            <button class="btn" type="submit">{{ $notification->lien ? __('Voir et traiter') : __('Marquer comme lu') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach

        {{ $notifications->links() }}
    @endif
@endsection
