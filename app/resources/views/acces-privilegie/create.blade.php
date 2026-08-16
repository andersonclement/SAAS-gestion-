@extends('layouts.app')

@section('title', __("Code d'accès"))

@section('content')
    <h1>{{ __("Code d'accès") }}</h1>

    @if ($codeActif)
        <div class="card" style="max-width:520px;">
            <p><strong>{{ __('Accès ouvert') }}</strong> — {{ $codeActif->portee->label() }}</p>
            <p>{{ $codeActif->portee->description() }}</p>
            <p style="color:#56606b;">
                {{ __("Valable jusqu'au :echeance. Vos opérations sont rattachées au code :code et visibles par votre patron.", [
                    'echeance' => $codeActif->expire_le->format('d/m/Y H:i'),
                    'code' => $codeActif->code,
                ]) }}
            </p>

            <form method="POST" action="{{ route('acces-privilegie.destroy') }}">
                @csrf
                @method('DELETE')
                <button class="btn" type="submit">{{ __("Refermer l'accès") }}</button>
            </form>
        </div>
    @else
        <div class="card" style="max-width:520px;">
            <p style="color:#56606b;">
                {{ __("Pour ajouter un produit ou approvisionner le stock, saisissez le code que votre patron vous a communiqué.") }}
            </p>

            <form method="POST" action="{{ route('acces-privilegie.store') }}">
                @csrf
                <div class="field">
                    <label for="code">{{ __("Code d'accès") }}</label>
                    <input id="code" type="text" name="code" placeholder="ACC-XXXX-XXXX" required autofocus autocomplete="off">
                </div>
                <button class="btn" type="submit">{{ __("Ouvrir l'accès") }}</button>
            </form>
        </div>
    @endif
@endsection
