<?php

namespace App\Agent;

use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends the signed requests to the Runner.
 */
class RunnerClient
{
    public function __construct(private ProviderList $providers) {}

    /**
     * Send a request to the agent. The Runner answers at once, then reports each step.
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    public function send(AgentRun $run, bool $resume = false): void
    {
        $run->loadMissing('message', 'project');
        $project = $run->project;

        $this->post('/taches', [
            'tache' => $run->id,
            'projet' => $run->project_id,
            'message' => $run->message?->content ?? '',
            'reprise' => $resume,
            'fournisseurs' => $this->providers->forRunner(),
            'rappel' => route('runner.events'),
            'sites' => $project->allowedDomains(),
            'sauvegarde' => $this->backupUrls($project),
            'historique' => $this->history($run),
        ]);
    }

    /**
     * Give the user's answer to a permission asked by the agent.
     */
    public function answerPermission(AgentPermission $permission, bool $allowed): bool
    {
        $permission->loadMissing('run');

        try {
            $this->post('/permissions', [
                'projet' => $permission->run->project_id,
                'permission' => $permission->request_id,
                'decision' => $allowed ? 'autoriser' : 'refuser',
            ]);
        } catch (RequestException) {
            return false;
        }

        return true;
    }

    /**
     * Wake the project's computer to show the preview again, without a request to the agent.
     */
    public function wakePreview(Project $project): void
    {
        $this->post('/apercus', [
            'projet' => $project->id,
            'rappel' => route('runner.project-events', $project),
            'sites' => $project->allowedDomains(),
            'sauvegarde' => $this->backupUrls($project),
        ]);
    }

    /**
     * Permanently erase the computers of a deleted project.
     */
    public function erase(string $projectId): void
    {
        $this->post('/projets/effacer', ['projet' => $projectId]);
    }

    /**
     * Where the Runner sends the backup after each request, and where it fetches the last one.
     *
     * @return array{envoi: string, telechargement: string|null}
     */
    private function backupUrls(Project $project): array
    {
        return [
            'envoi' => route('runner.backup.store', $project),
            'telechargement' => $project->backup_at ? route('runner.backup.show', $project) : null,
        ];
    }

    /**
     * The last exchanges of the conversation, for an agent that starts again from a backup.
     *
     * @return list<array{role: string, contenu: string}>
     */
    private function history(AgentRun $run): array
    {
        return $run->project->messages()
            ->when($run->message_id, fn ($query) => $query->where('id', '!=', $run->message_id))
            ->get()
            ->take(-10)
            ->map(fn (Message $message) => [
                'role' => $message->role,
                'contenu' => mb_substr($message->content, 0, 1500),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    private function post(string $path, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->request($body)->post($this->url($path))->throw();
    }

    /**
     * State of the providers as seen by the Runner (keys present, providers set aside).
     *
     * @return array{cles_presentes: list<string>, mis_de_cote: array<string, string>}|null
     */
    public function state(): ?array
    {
        try {
            $response = $this->request('')->timeout(3)->get($this->url('/etat'));
        } catch (ConnectionException|RuntimeException) {
            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    private function request(string $body)
    {
        $secret = config('kekeli.runner.secret');

        if (blank($secret)) {
            throw new RuntimeException('KEKELI_RUNNER_SECRET is missing.');
        }

        $request = Http::acceptJson()
            ->timeout(config('kekeli.runner.timeout', 10))
            ->withHeaders(RunnerSignature::headers($secret, $body));

        return $body === '' ? $request : $request->withBody($body, 'application/json');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('kekeli.runner.url'), '/').$path;
    }
}
