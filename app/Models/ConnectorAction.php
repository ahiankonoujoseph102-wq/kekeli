<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One action done (or asked) with a connector: the journal shown to the client in « Réglages → Connecteurs », and the
 * permission asked before any action that changes something (« Autoriser » / « Refuser »), like Claude.
 */
class ConnectorAction extends Model
{
    public const DONE = 'fait';

    public const FAILED = 'erreur';

    public const PENDING = 'en_attente';

    public const ALLOWED = 'autorise';

    public const REFUSED = 'refuse';

    protected $fillable = ['user_id', 'user_connector_id', 'project_id', 'agent_run_id', 'connector_name', 'tool', 'writes', 'status', 'summary', 'arguments'];

    protected $hidden = ['arguments'];

    protected function casts(): array
    {
        return ['writes' => 'boolean', 'summary' => 'array', 'arguments' => 'encrypted:array'];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(UserConnector::class, 'user_connector_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The short line shown in the journal and on the card: « Créer une page ». */
    public function title(): string
    {
        return (string) (data_get($this->summary, 'action') ?: str_replace(['_', '-'], ' ', $this->tool));
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    public function details(): array
    {
        return collect((array) data_get($this->summary, 'details', []))
            ->map(fn ($detail) => ['name' => (string) data_get($detail, 'nom', ''), 'value' => (string) data_get($detail, 'valeur', '')])
            ->filter(fn ($detail) => $detail['name'] !== '')
            ->take(6)->values()->all();
    }
}
