<?php

namespace App\Support;

use App\Github\GithubClient;

/**
 * Connectors of the client's tools (Google Drive, Gmail, WhatsApp… later), like Claude, ChatGPT or Manus. The list
 * lives in config/kekeli.php (« connectors »). Only the connectors that really work are shown: as long as there is
 * none, « Réglages → Connecteurs » stays hidden (Kekeli never shows something that does not work yet).
 */
class Connectors
{
    /**
     * @return list<array{key: string, name: string}>
     */
    public function available(): array
    {
        $list = collect((array) config('kekeli.connectors', []))
            ->filter(fn ($connector) => ($connector['enabled'] ?? false) === true)
            ->map(fn ($connector, $key) => ['key' => (string) $key, 'name' => (string) ($connector['name'] ?? $key)])
            ->values()
            ->all();

        // GitHub, dès que la GitHub App de Kekeli est réglée (relier son compte, choisir ses dépôts).
        if (app(GithubClient::class)->configured()) {
            array_unshift($list, ['key' => 'github', 'name' => 'GitHub']);
        }

        return $list;
    }

    public function any(): bool
    {
        return $this->available() !== [];
    }
}
