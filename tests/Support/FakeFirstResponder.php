<?php

namespace Tests\Support;

use App\Agent\FirstResponder;
use App\Models\AgentRun;

/**
 * A simulated model for the tests: by default it calls the tool offered in the place (the work on the site starts,
 * as before); a test can script answers, tools, doubts or failures of every provider.
 */
class FakeFirstResponder extends FirstResponder
{
    /** @var list<array<string, mixed>> */
    public array $script = [];

    /** @var list<array{run: string, place: string, message: string}> */
    public array $received = [];

    public function __construct() {}

    /** Called while the model « thinks » (for example the client touches « Arrêter » meanwhile). */
    public ?\Closure $during = null;

    public function answers(string $text, ?string $title = null): static
    {
        $this->script[] = ['ok' => true, 'decision' => 'repondre', 'reponse' => $text, 'titre' => $title] + self::tokens();

        return $this;
    }

    /**
     * An answer after searching on the Internet, with its sources (as the Runner returns them).
     *
     * @param  list<array{titre: string, url: string}>  $sources
     */
    public function answersWithSources(string $text, array $sources, int $searches = 1): static
    {
        $this->script[] = ['ok' => true, 'decision' => 'repondre', 'reponse' => $text, 'sources' => $sources, 'requetes' => 2,
            'recherches' => array_fill(0, $searches, ['fournisseur' => 'tavily', 'requete' => 'recherche', 'resultats' => 5,
                'sites' => collect($sources)->map(fn ($source) => preg_replace('/^www\./', '', (string) parse_url($source['url'], PHP_URL_HOST)))->filter()->values()->all()])] + self::tokens();

        return $this;
    }

    public function callsTool(string $name): static
    {
        $this->script[] = ['ok' => true, 'decision' => 'outil', 'outil' => $name] + self::tokens();

        return $this;
    }

    public function fails(int $times = 1): static
    {
        for ($i = 0; $i < $times; $i++) {
            $this->script[] = ['ok' => false, 'refus' => [['fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite']]];
        }

        return $this;
    }

    public function decide(AgentRun $run): array
    {
        $place = self::placeOf($run->project);
        $this->received[] = ['run' => $run->id, 'place' => $place, 'message' => (string) $run->message?->content];
        if ($this->during) {
            ($this->during)($run);
        }

        return array_shift($this->script) ?? (['ok' => true, 'decision' => 'outil', 'outil' => array_key_first(self::TOOLS[$place])] + self::tokens());
    }

    /**
     * @return array<string, mixed>
     */
    private static function tokens(): array
    {
        return ['fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 1100, 'tokens_sortie' => 40, 'refus' => []];
    }
}
