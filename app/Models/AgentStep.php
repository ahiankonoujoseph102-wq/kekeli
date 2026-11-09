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
            'echec', 'licences_probleme', 'echec_sauvegarde', 'qualite_probleme' => 'danger',
            'licences_ok', 'sauvegarde', 'reprise', 'restauration', 'qualite_ok' => 'success',
            default => 'normal',
        };
    }

    public function icon(): string
    {
        return self::ICONS[$this->kind] ?? self::ICONS['outil'];
    }
}
