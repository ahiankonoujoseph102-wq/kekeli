<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A connector (MCP server) linked by a client, like the connectors of Claude and ChatGPT: from the catalog of Kekeli or
 * added by the client (« Ajouter un connecteur personnalisé »). Its tokens are encrypted, never shown, only sent to the
 * Runner for the request in progress; « Déconnecter » erases them.
 */
class UserConnector extends Model
{
    public const AUTH_NONE = 'aucune';

    public const AUTH_OAUTH = 'oauth';

    protected $fillable = ['catalog_key', 'name', 'url', 'auth', 'client', 'access_token', 'refresh_token', 'token_expires_at', 'resource', 'always_allowed', 'active', 'connected_at'];

    protected $hidden = ['client', 'access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'client' => 'encrypted:array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'always_allowed' => 'array',
            'active' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ConnectorAction::class);
    }

    /** Whether the connector can be used: a server without login, or an account linked with OAuth. */
    public function isConnected(): bool
    {
        return $this->connected_at !== null && ($this->auth === self::AUTH_NONE || filled($this->access_token));
    }

    /**
     * @return list<string>
     */
    public function alwaysAllowed(): array
    {
        return array_values(array_filter((array) $this->always_allowed, 'is_string'));
    }
}
