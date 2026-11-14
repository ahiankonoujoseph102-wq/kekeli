<?php

namespace App\Agent;

use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

/**
 * Le temps de chaque étape d'une réponse ou d'un travail, pour mesurer la vitesse de Kekeli comme on mesure celle de
 * Claude ou de ChatGPT : seulement des nombres (jamais le texte du client, jamais un secret), gardés dans
 * `agent_runs.timings`, écrits dans le journal et montrés dans Administration → Consommation.
 *
 * - « serveur » : l'heure (en millisecondes) de chaque étape vue par le site : message reçu, tâche reçue, tâche
 *   démarrée, appel au Runner, premier mot, fin ; pour un travail : travail créé, envoyé au Runner, première étape.
 * - « runner » : les étapes vues par le Runner, en millisecondes depuis la réception de la demande : appel au modèle,
 *   premier mot reçu du modèle, fin.
 * - « navigateur » : les étapes vues par le téléphone, en millisecondes depuis le toucher sur « Envoyer » : message
 *   affiché, envoi accepté par le site, premier mot affiché, réponse finie.
 */
class Timings
{
    /** Étapes vues par le site, dans l'ordre. */
    public const SERVER = ['recu', 'tache_recue', 'tache_demarree', 'appel_runner', 'premier_mot', 'fin', 'travail_cree', 'travail_envoye', 'premiere_etape'];

    /** Étapes vues par le Runner. */
    public const RUNNER = ['appel_modele', 'premier_mot', 'fin'];

    /** Étapes vues par le navigateur. */
    public const BROWSER = ['affiche', 'envoye', 'premier_mot', 'fin'];

    /** Noms montrés dans l'administration. */
    public const LABELS = [
        'recu' => 'Message reçu',
        'tache_recue' => 'Tâche reçue',
        'tache_demarree' => 'Tâche démarrée',
        'appel_runner' => 'Appel au Runner',
        'premier_mot' => 'Premier mot',
        'fin' => 'Réponse finie',
        'travail_cree' => 'Travail créé',
        'travail_envoye' => 'Travail envoyé',
        'premiere_etape' => 'Première étape',
        'appel_modele' => 'Appel au modèle',
        'affiche' => 'Message affiché',
        'envoye' => 'Envoi accepté',
    ];

    /**
     * Note l'heure d'une étape vue par le site (la première fois seulement : un nouvel essai ne l'efface pas).
     */
    public static function mark(?AgentRun $run, string $step, ?float $at = null): void
    {
        if (! $run || ! in_array($step, self::SERVER, true)) {
            return;
        }
        $timings = self::current($run);
        if (isset($timings['serveur'][$step])) {
            return;
        }
        $timings['serveur'][$step] = (int) round($at ?? microtime(true) * 1000);
        self::save($run, $timings);

        if ($step === 'fin' || $step === 'premiere_etape') {
            Log::info('Vitesse de Kekeli', ['tache' => $run->id, 'but' => $run->purpose, 'etapes' => self::secondsFrom($timings, 'recu')]);
        }
    }

    /**
     * Les étapes vues par le Runner (millisecondes depuis la réception de sa demande), seulement des nombres connus.
     *
     * @param  array<string, mixed>  $chronos
     */
    public static function runner(?AgentRun $run, array $chronos): void
    {
        self::keep($run, 'runner', self::RUNNER, $chronos);
    }

    /**
     * Les étapes vues par le navigateur (millisecondes depuis le toucher sur « Envoyer »).
     *
     * @param  array<string, mixed>  $chronos
     */
    public static function browser(?AgentRun $run, array $chronos): void
    {
        self::keep($run, 'navigateur', self::BROWSER, $chronos);
    }

    /**
     * Les étapes du site en secondes depuis l'une d'elles (par défaut, le message reçu), pour l'affichage.
     *
     * @param  array<string, mixed>  $timings
     * @return array<string, float>
     */
    public static function secondsFrom(array $timings, string $from = 'recu'): array
    {
        $server = (array) ($timings['serveur'] ?? []);
        $start = $server[$from] ?? null;
        if ($start === null) {
            return [];
        }
        $seconds = [];
        foreach (self::SERVER as $step) {
            if (isset($server[$step])) {
                $seconds[$step] = round(((int) $server[$step] - (int) $start) / 1000, 2);
            }
        }

        return $seconds;
    }

    /**
     * Une famille d'étapes en secondes (Runner ou navigateur).
     *
     * @param  array<string, mixed>  $timings
     * @return array<string, float>
     */
    public static function seconds(array $timings, string $family): array
    {
        return array_map(fn ($ms) => round(((int) $ms) / 1000, 2), (array) ($timings[$family] ?? []));
    }

    /**
     * @param  list<string>  $allowed
     * @param  array<string, mixed>  $chronos
     */
    private static function keep(?AgentRun $run, string $family, array $allowed, array $chronos): void
    {
        if (! $run) {
            return;
        }
        $kept = [];
        foreach ($allowed as $step) {
            $value = $chronos[$step] ?? null;
            if (is_numeric($value) && $value >= 0 && $value < 3_600_000) {
                $kept[$step] = (int) round((float) $value);
            }
        }
        if ($kept === []) {
            return;
        }
        $timings = self::current($run);
        $timings[$family] = array_merge((array) ($timings[$family] ?? []), $kept);
        self::save($run, $timings);
    }

    /**
     * Les temps déjà notés, relus dans la base (le Runner, la file d'attente et la page écrivent chacun les leurs).
     *
     * @return array<string, mixed>
     */
    private static function current(AgentRun $run): array
    {
        $stored = AgentRun::query()->whereKey($run->id)->value('timings');

        return is_string($stored) ? (array) json_decode($stored, true) : (array) ($stored ?? []);
    }

    /**
     * @param  array<string, mixed>  $timings
     */
    private static function save(AgentRun $run, array $timings): void
    {
        $run->timings = $timings;
        // Seulement cette colonne : jamais une écriture qui écraserait un état changé ailleurs entre-temps.
        AgentRun::query()->whereKey($run->id)->update(['timings' => json_encode($timings)]);
        $run->syncOriginalAttribute('timings');
    }
}
