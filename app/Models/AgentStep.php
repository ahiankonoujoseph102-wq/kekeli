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

    public function icon(): string
    {
        return self::ICONS[$this->kind] ?? self::ICONS['outil'];
    }
}
