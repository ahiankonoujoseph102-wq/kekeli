<?php

namespace App\Livewire\Concerns;

use App\Jobs\EraseProject;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;

/**
 * « Renommer » and « Supprimer » from the menu « ⋯ » of a creation, the same in « Récents », « Projets » and
 * « Bibliothèque ». Each page names its own windows (modalPrefix), so two lists on the same page never mix them up.
 * Only the client's own creations (404 otherwise).
 */
trait ManagesCreations
{
    public ?string $renamingId = null;

    public string $newName = '';

    public ?string $deletingId = null;

    /** Prefix of the windows « Renommer » and « Supprimer » of this list. */
    abstract protected function modalPrefix(): string;

    /** Forget what the list keeps in memory after a change. */
    abstract protected function forgetLists(): void;

    public function startRenaming(string $projectId): void
    {
        $project = $this->findProject($projectId);
        $this->resetErrorBag();
        $this->renamingId = $project->id;
        $this->newName = $project->name;

        Flux::modal($this->modalPrefix().'-rename')->show();
    }

    public function renameProject(): void
    {
        $validated = $this->validate(['newName' => ['required', 'string', 'max:100']]);

        $this->findProject((string) $this->renamingId)->update(['name' => trim($validated['newName'])]);
        $this->reset('renamingId', 'newName');
        $this->forgetLists();

        Flux::modal($this->modalPrefix().'-rename')->close();
        // La page ouverte et la barre latérale peuvent afficher ce nom : elles se mettent à jour.
        $this->dispatch('projet-renomme');
    }

    public function confirmDeletion(string $projectId): void
    {
        $this->deletingId = $this->findProject($projectId)->id;

        Flux::modal($this->modalPrefix().'-delete')->show();
    }

    /** Delete the creation awaiting confirmation, with its conversation; its computer and backup are erased for good. */
    public function deleteProject(): void
    {
        $project = $this->findProject((string) $this->deletingId);
        $project->delete();

        rescue(function () use ($project) {
            EraseProject::dispatch($project->id);
        }, report: true);

        $this->reset('deletingId');
        $this->forgetLists();
        Flux::modal($this->modalPrefix().'-delete')->close();
        $this->dispatch('projet-renomme');

        if ($project->id === ($this->current ?? null)) {
            $this->redirectRoute('projects.index', navigate: true);
        }
    }

    protected function findProject(string $projectId): Project
    {
        return Auth::user()->projects()->findOrFail($projectId);
    }
}
