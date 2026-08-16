<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Récapitulatif quotidien des alertes de stock envoyé aux responsables.
 *
 * Le message est volontairement autoportant : un gérant qui le lit sur son
 * téléphone doit savoir quoi réapprovisionner sans avoir à ouvrir
 * l'application.
 */
class RecapitulatifAlertes extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{ruptures: Collection, stockFaible: Collection, peremptionProche: Collection, perimes: Collection, creancesEnRetard: Collection}  $alertes
     */
    public function __construct(
        public User $destinataire,
        public array $alertes,
        public string $perimetre,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':nombre alerte(s) de stock — :perimetre', [
                'nombre' => static::compter($this->alertes),
                'perimetre' => $this->perimetre,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alertes',
            with: [
                'total' => static::compter($this->alertes),
            ],
        );
    }

    /**
     * @param  array<string, Collection>  $alertes
     */
    public static function compter(array $alertes): int
    {
        return array_sum(array_map(fn (Collection $liste) => $liste->count(), $alertes));
    }
}
