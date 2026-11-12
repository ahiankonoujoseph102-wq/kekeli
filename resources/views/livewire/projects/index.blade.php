<?php

use App\WebApps\NewSite;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Text typed on the welcome page before creating an account: Kekeli starts on it right away.
     */
    public function mount(): void
    {
        $pending = trim((string) session()->pull(NewSite::PENDING, ''));
        if (mb_strlen($pending) >= 2) {
            $project = app(NewSite::class)->create(Auth::user(), $pending);
            $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
        }
    }

    /**
     * Render the page with a translated title.
     */
    public function rendering($view): void
    {
        $view->title(__('New'));
    }
}; ?>

{{--
    « Nouveau » : la grande question et la zone de saisie, comme Claude, ChatGPT ou Lovable. Les sites et les discussions
    sont dans « Récents », dans la barre de navigation.
--}}
<section class="mx-auto flex min-h-[calc(100svh-9rem)] w-full max-w-3xl flex-col justify-center gap-6 pb-10 lg:min-h-[calc(100svh-4rem)]" data-new-page>
    <livewire:site-prompt :main="true" />
</section>
