<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One action of the agent shown live to the user (file written, command run…).
 */
class AgentStep extends Model
{
    /**
     * Sentences shown to the user for each kind of step (translated in lang/fr.json).
     */
    public const LABELS = [
        'fichier_ecrit' => 'File written',
        'fichier_modifie' => 'File modified',
        'fichier_lu' => 'File read',
        'commande' => 'Command run',
        'recherche' => 'Search in the files',
        'internet' => 'Web page consulted',
        'outil' => 'Tool used',
        'echec' => 'An action failed, the agent is correcting it',
        'reprise' => 'Project resumed where you left it',
        'restauration' => 'Project files restored from the backup',
        'licences_correction' => 'Licence check: the agent is correcting the sources',
        'licences_ok' => 'Sources and licences checked',
        'licences_probleme' => 'Sources to check by hand',
        'sauvegarde' => 'Project saved',
        'echec_sauvegarde' => 'The project could not be saved this time',
        'verification_correction' => 'Automatic checks: the agent is correcting',
        'qualite_ok' => 'Quality checked',
        'qualite_probleme' => 'Quality points to review',
        'photos' => 'Search for free photos',
        'relance' => 'The agent stopped too early: it resumes the work',
        'photos_client' => 'Your photos are used first',
        'image_creee' => 'Image created',
        'icones' => 'Icons chosen',
        'typographie' => 'French typography corrected',
        'depot_ouvert' => 'GitHub repository opened',
        'detection' => 'Type of project recognized',
        'installation' => 'Tools and dependencies installed',
        'installation_echec' => 'An installation failed: the agent will see it',
        'depot_commits' => 'Work saved in Git',
        'application_deposee' => 'Starting application put in place',
        'tests_ok' => 'Tests of the application passed',
        'tests_echec' => 'Tests of the application failed',
        'securite_ok' => 'Security rules checked',
        'securite_probleme' => 'Security point to fix',
        'application_ok' => 'The application answers',
        'application_erreur' => 'The application does not answer correctly',
        'narration' => 'Kekeli explains',
    ];

    public const ICONS = [
        'fichier_ecrit' => 'document-plus',
        'fichier_modifie' => 'pencil-square',
        'fichier_lu' => 'document-text',
        'commande' => 'command-line',
        'recherche' => 'magnifying-glass',
        'internet' => 'globe-alt',
        'outil' => 'wrench',
        'echec' => 'exclamation-triangle',
        'reprise' => 'play',
        'restauration' => 'arrow-uturn-left',
        'licences_correction' => 'scale',
        'licences_ok' => 'check-badge',
        'licences_probleme' => 'exclamation-triangle',
        'sauvegarde' => 'cloud-arrow-up',
        'echec_sauvegarde' => 'exclamation-triangle',
        'verification_correction' => 'wrench-screwdriver',
        'qualite_ok' => 'check-badge',
        'qualite_probleme' => 'exclamation-triangle',
        'photos' => 'photo',
        'relance' => 'arrow-path',
        'photos_client' => 'camera',
        'image_creee' => 'sparkles',
        'icones' => 'squares-2x2',
        'typographie' => 'language',
        'depot_ouvert' => 'code-bracket-square',
        'detection' => 'magnifying-glass-circle',
        'installation' => 'arrow-down-tray',
        'installation_echec' => 'exclamation-triangle',
        'depot_commits' => 'check-circle',
        'application_deposee' => 'square-3-stack-3d',
        'tests_ok' => 'check-badge',
        'tests_echec' => 'x-circle',
        'securite_ok' => 'shield-check',
        'securite_probleme' => 'shield-exclamation',
        'application_ok' => 'check-circle',
        'application_erreur' => 'exclamation-triangle',
        'narration' => 'chat-bubble-left-ellipsis',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'target',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /** A sentence of Kekeli while it works (« Je regarde comment la page d'accueil est construite. »). */
    public function isNarration(): bool
    {
        return $this->kind === 'narration';
    }

    public function label(): string
    {
        return __(self::LABELS[$this->kind] ?? self::LABELS['outil']);
    }

    /**
     * Steps shown in red (something went wrong) or in green (a check passed).
     */
    public function tone(): string
    {
        return match ($this->kind) {
            'echec', 'licences_probleme', 'echec_sauvegarde', 'qualite_probleme', 'tests_echec', 'securite_probleme', 'application_erreur' => 'danger',
            'licences_ok', 'sauvegarde', 'reprise', 'restauration', 'qualite_ok', 'tests_ok', 'securite_ok', 'application_ok' => 'success',
            default => 'normal',
        };
    }

    public function icon(): string
    {
        return self::ICONS[$this->kind] ?? self::ICONS['outil'];
    }
}
