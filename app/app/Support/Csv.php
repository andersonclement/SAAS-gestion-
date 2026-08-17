<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lecture et écriture des fichiers CSV échangés avec les boutiques.
 *
 * Le tableur des utilisateurs est un Excel ou un LibreOffice en français, sous
 * Windows. C'est lui qui dicte les choix de ce fichier : séparateur
 * point-virgule, marque d'ordre des octets en tête, et à la lecture une
 * tolérance à ce que les mêmes logiciels produisent quand ils sont réglés
 * autrement.
 */
class Csv
{
    /**
     * Un tableur français attend le point-virgule : réglé sur la virgule, il
     * empile tout le fichier dans une seule colonne. Le lecteur, lui, accepte
     * les deux — un fichier reçu d'ailleurs s'ouvre quand même.
     */
    public const SEPARATEUR = ';';

    /**
     * @param  array<int, string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $nomFichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $flux = fopen('php://output', 'w');

            // Sans cette marque, Excel lit le fichier en Windows-1252 et
            // affiche « PÃ©remption » à la place de « Péremption ».
            fwrite($flux, "\xEF\xBB\xBF");

            fputcsv($flux, $entetes, self::SEPARATEUR, '"', '');

            foreach ($lignes as $ligne) {
                fputcsv($flux, $ligne, self::SEPARATEUR, '"', '');
            }

            fclose($flux);
        }, $nomFichier, ['Content-Type' => 'text/csv']);
    }

    /**
     * Lit un fichier et rend ses lignes non vides, associées à leurs en-têtes.
     *
     * Chaque ligne porte son numéro tel que le tableur l'affiche : c'est par ce
     * numéro que l'utilisateur retrouvera la saisie à corriger.
     *
     * @return array<int, array{numero: int, valeurs: array<string, string>}>
     */
    public static function lire(string $chemin): array
    {
        $contenu = self::normaliser((string) file_get_contents($chemin));

        $flux = fopen('php://memory', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $separateur = self::separateur((string) fgets($flux));
        rewind($flux);

        $entetes = fgetcsv($flux, 0, $separateur, '"', '');

        if ($entetes === false) {
            fclose($flux);

            return [];
        }

        $entetes = array_map(self::normaliserEntete(...), $entetes);

        $lignes = [];
        $position = ftell($flux);

        while (($valeurs = fgetcsv($flux, 0, $separateur, '"', '')) !== false) {
            $suivante = ftell($flux);

            // Le numéro se compte sur le fichier brut, pas sur les lignes
            // rendues : les lignes vides sautées ne doivent pas décaler les
            // suivantes, sans quoi le rapport d'erreurs désigne la mauvaise.
            $numero = substr_count(substr($contenu, 0, (int) $position), "\n") + 1;
            $position = $suivante;

            $valeurs = array_map(fn ($valeur) => trim((string) $valeur), $valeurs);

            if (implode('', $valeurs) === '') {
                continue;
            }

            // Une ligne plus courte que l'en-tête (colonnes de queue laissées
            // vides) est complétée ; le surplus est ignoré.
            $valeurs = array_pad(array_slice($valeurs, 0, count($entetes)), count($entetes), '');

            $lignes[] = [
                'numero' => $numero,
                'valeurs' => array_combine($entetes, $valeurs),
            ];
        }

        fclose($flux);

        return $lignes;
    }

    /**
     * Retire la marque d'ordre des octets et ramène le tout en UTF-8.
     */
    private static function normaliser(string $contenu): string
    {
        $contenu = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

        // « Enregistrer sous > CSV » d'un Excel Windows produit du
        // Windows-1252. Laissé tel quel, chaque accent devient un caractère
        // invalide et le nom du produit arrive abîmé en base.
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        return $contenu;
    }

    /**
     * Devine le séparateur sur la ligne d'en-tête : c'est celui qui y apparaît
     * le plus souvent.
     */
    private static function separateur(string $entete): string
    {
        $candidats = [';' => substr_count($entete, ';'), ',' => substr_count($entete, ','), "\t" => substr_count($entete, "\t")];

        arsort($candidats);
        $meilleur = array_key_first($candidats);

        return $candidats[$meilleur] > 0 ? $meilleur : self::SEPARATEUR;
    }

    /**
     * « Prix d'achat », « PRIX ACHAT » et « prix_achat » désignent la même
     * colonne : on ne peut pas demander à un commerçant de reproduire une
     * casse et des accents à l'identique.
     */
    private static function normaliserEntete(string $entete): string
    {
        $entete = mb_strtolower(trim($entete));
        $entete = strtr($entete, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $entete), '_');
    }
}
