<?php

namespace Tests\Feature;

use App\Agent\FirstResponder;
use App\Agent\RunnerSignature;
use App\Jobs\IndexKnowledgeDocument;
use App\Jobs\RespondToMessage;
use App\Knowledge\Chunker;
use App\Knowledge\KnowledgeAnswers;
use App\Knowledge\KnowledgeIndexer;
use App\Knowledge\KnowledgeLinks;
use App\Knowledge\KnowledgeSearch;
use App\Knowledge\TextReader;
use App\Knowledge\Vectors;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSpace;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Base de connaissances (Kekeli Knowledge, 18 novembre 2026) : espaces, importation et traitement des documents,
 * recherche par le sens et par les mots, réponses avec leurs sources, isolation entre clients et suppression.
 */
class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-base-de-connaissances-123456';

    /** A Runner whose vectors fail, or which cannot read a file. */
    private bool $vectorsDown = false;

    private bool $extractBroken = false;

    /** What Google Drive, YouTube and the reading of a video answer (simulated). */
    private array $drive = ['body' => '', 'headers' => []];

    private mixed $oembed = ['title' => 'Vidéo'];

    private array $video = ['ok' => false, 'erreur' => 'video_inaccessible'];

    private const TARIFS = "# Tarifs Ama Pagnes\n\nLe pagne wax hollandais coûte 18 000 FCFA.\n\nLe pagne wax ordinaire coûte 8 000 FCFA.\n\n## Livraison\n\nLivraison à Lomé en 24 heures pour 1 500 FCFA. Livraison vers Kara en 3 jours pour 3 000 FCFA.";

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
        Storage::fake(ProjectPhoto::disk());
    }

    /** A Runner that gives a vector from a few words: same words, close vectors. */
    private function fakeRunner(?array $answer = null): void
    {
        $vector = function (string $text): array {
            $words = ['pagne', 'wax', 'livraison', 'kara', 'lome', 'conge', 'prix', 'coute', 'remboursement'];
            $ascii = mb_strtolower(Str::ascii($text));
            $v = array_map(fn ($w) => substr_count($ascii, $w) + 0.01, $words);
            $length = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));

            return array_map(fn ($x) => $x / $length, $v);
        };
        Http::fake([
            'runner.test/base/vecteurs' => fn (Request $request) => $this->vectorsDown ? Http::response([], 500) : Http::response(['ok' => true, 'modele' => 'gemini/gemini-embedding-001', 'requetes' => 1, 'vecteurs' => array_map($vector, $request['textes'])]),
            'runner.test/base/extraire' => fn () => $this->extractBroken ? Http::response(['ok' => false, 'erreur' => 'illisible'], 502) : Http::response(['ok' => true, 'pages' => 2, 'morceaux' => [['page' => 1, 'texte' => 'Catalogue Ama Pagnes. Boutique au grand marché de Lomé.'], ['page' => 2, 'texte' => 'Le pagne kente tissé main coûte 45 000 FCFA.']]]),
            'docs.google.com/*' => fn () => Http::response($this->drive['body'], 200, $this->drive['headers']),
            'drive.usercontent.google.com/*' => fn () => Http::response($this->drive['body'], 200, $this->drive['headers']),
            'www.youtube.com/oembed*' => fn () => is_array($this->oembed) ? Http::response($this->oembed) : Http::response('Unauthorized', 401),
            'runner.test/base/video' => fn () => Http::response($this->video, ($this->video['ok'] ?? false) ? 200 : 502),
            'runner.test/repondre' => Http::response($answer ?? ['ok' => true, 'decision' => 'repondre', 'reponse' => 'Le pagne wax ordinaire coûte 8 000 FCFA [1]. Le hollandais coûte 18 000 FCFA [1, 7].', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.1-flash-lite', 'tokens_entree' => 900, 'tokens_sortie' => 40, 'refus' => []]),
        ]);
    }

    private function space(?User $user = null, string $name = 'Entreprise'): KnowledgeSpace
    {
        $user ??= User::factory()->create(['country' => 'TG']);

        return $user->knowledgeSpaces()->create(['name' => $name]);
    }

    private function importText(KnowledgeSpace $space, string $name = 'tarifs.md', string $content = self::TARIFS): KnowledgeDocument
    {
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent($name, $content)])
            ->assertHasNoErrors();

        return $space->documents()->where('name', $name)->sole();
    }

    public function test_the_navigation_shows_the_knowledge_base_and_a_space_can_be_created(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->actingAs($user)->get(route('knowledge.index'))->assertOk()
            ->assertSee('data-nav-knowledge', false)
            ->assertSee('Base de connaissances')
            ->assertSee('Aucun espace pour l’instant')
            ->assertSee('Comptabilité');

        Volt::actingAs($user)->test('knowledge.index')->call('suggest', 'Formation')->assertRedirect();
        $space = $user->knowledgeSpaces()->sole();
        $this->assertSame('Formation', $space->name);
        $this->actingAs($user)->get(route('knowledge.show', $space))->assertOk()->assertSee('Importer un document')->assertSee('Aucun document pour l’instant');
    }

    public function test_an_imported_text_is_cut_into_passages_with_vectors_and_shown_ready(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $document = $this->importText($space);

        $document->refresh();
        $this->assertSame(KnowledgeDocument::READY, $document->status);
        $this->assertSame('gemini/gemini-embedding-001', $document->embedding_model);
        $this->assertGreaterThanOrEqual(2, $document->chunks_count);
        $chunk = $document->chunks()->orderBy('position')->first();
        $this->assertCount(9, Vectors::unpack($chunk->vector));
        // Le texte des passages est chiffré dans la base, comme la mémoire.
        $this->assertStringNotContainsString('pagne', (string) \DB::table('knowledge_chunks')->where('id', $chunk->id)->value('content'));
        // Un passage garde le titre de sa section.
        $this->assertTrue($document->chunks->contains(fn ($c) => str_starts_with($c->content, 'Livraison')));
        // Les requêtes au modèle gratuit de vecteurs sont comptées par fournisseur.
        $this->assertSame(1, (int) ProviderUsage::query()->where('provider', 'gemini')->where('model', 'gemini-embedding-001')->value('requests'));

        $this->actingAs($space->user)->get(route('knowledge.show', $space))->assertOk()
            ->assertSee('tarifs.md')->assertSee('data-knowledge-status="ready"', false)->assertSee('Prêt');
    }

    public function test_a_pdf_is_read_by_the_isolated_computer_of_the_runner_with_its_pages(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('catalogue.pdf', "%PDF-1.4\nfaux contenu")])
            ->assertHasNoErrors();
        $document = $space->documents()->sole()->fresh();

        $this->assertSame(KnowledgeDocument::READY, $document->status);
        $this->assertSame(2, $document->pages);
        $this->assertSame([1, 2], $document->chunks()->orderBy('position')->pluck('page')->all());
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/base/extraire'
            && $request['format'] === 'pdf' && $request['espace'] === $space->id
            && str_contains($request['url'], '/runner/base/documents/'.$document->id) && str_contains($request['url'], 'signature='));
    }

    public function test_the_file_address_needs_both_signatures(): void
    {
        $space = $this->space();
        Storage::disk(ProjectPhoto::disk())->put('connaissances/x.md', 'secret');
        $document = $space->documents()->create(['user_id' => $space->user_id, 'name' => 'a.md', 'format' => 'md', 'size' => 6, 'path' => 'connaissances/x.md', 'hash' => str_repeat('a', 64)]);

        $url = URL::temporarySignedRoute('runner.knowledge.file', now()->addMinutes(10), ['document' => $document]);
        $this->get($url)->assertStatus(401);
        $this->get(route('runner.knowledge.file', $document))->assertStatus(403);
        $headers = RunnerSignature::headers(self::SECRET, '');
        $this->withHeaders($headers)->get($url)->assertOk();
    }

    public function test_files_are_checked_never_imported_twice_and_a_twin_is_copied_without_new_vectors(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $this->importText($space);

        // Le même fichier sous un autre nom : refusé dans le même espace.
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('copie.md', self::TARIFS)])
            ->assertHasErrors('uploads');
        $this->assertSame(1, $space->documents()->count());

        // Un programme renommé en PDF, ou un format inconnu : refusés avant d'être gardés.
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('faux.pdf', "MZ\x90\x00programme")])
            ->assertHasErrors();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('outil.exe', 'MZ')])
            ->assertHasErrors();
        $this->assertSame(1, $space->documents()->count());

        // Le même fichier dans un autre espace du client : ses passages et vecteurs sont copiés, aucune requête de plus.
        Http::fake();
        $other = $this->space($space->user, 'Formation');
        $copy = $this->importText($other, 'tarifs.md');
        $this->assertSame(KnowledgeDocument::READY, $copy->fresh()->status);
        $this->assertSame($space->documents()->sole()->chunks_count, $copy->fresh()->chunks_count);
        Http::assertNothingSent();
    }

    public function test_hybrid_search_finds_the_right_passage_and_works_by_words_without_the_runner(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $this->importText($space);
        $this->importText($space, 'conges.txt', "Chaque employé a droit à 24 jours de congé payé par an.\n\nLa demande se fait deux semaines à l'avance.");

        $found = app(KnowledgeSearch::class)->search($space, null, 'Quel est le prix de la livraison à Kara ?');
        $this->assertSame('hybride', $found['mode']);
        $this->assertStringContainsString('Kara', $found['passages'][0]['text']);

        // Le Runner injoignable : la recherche par mots répond quand même.
        $this->vectorsDown = true;
        $byWords = app(KnowledgeSearch::class)->search($space, null, 'combien de jours de congé');
        $this->assertSame('mots', $byWords['mode']);
        $this->assertStringContainsString('24 jours', $byWords['passages'][0]['text']);

        // Seulement les documents choisis.
        $tarifs = $space->documents()->where('name', 'tarifs.md')->value('id');
        $only = app(KnowledgeSearch::class)->search($space, [$tarifs], 'congé payé');
        $this->assertTrue(collect($only['passages'])->every(fn ($p) => $p['document'] === 'tarifs.md'));
    }

    public function test_a_question_gets_an_answer_from_the_documents_with_its_cited_sources(): void
    {
        $this->useRealFirstResponder();
        Bus::fake([IndexKnowledgeDocument::class]);
        $space = $this->space();
        $document = $space->documents()->create(['user_id' => $space->user_id, 'name' => 'Tarifs 2026.pdf', 'format' => 'pdf', 'size' => 100, 'path' => 'p', 'hash' => str_repeat('b', 64), 'status' => KnowledgeDocument::READY, 'chunks_count' => 2]);
        $document->chunks()->create(['knowledge_space_id' => $space->id, 'position' => 0, 'page' => 2, 'content' => 'Le pagne wax ordinaire coûte 8 000 FCFA. Le hollandais coûte 18 000 FCFA.']);
        $document->chunks()->create(['knowledge_space_id' => $space->id, 'position' => 1, 'page' => 3, 'content' => 'Livraison à Kara : 3 000 FCFA.']);
        $this->fakeRunner();

        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('question', 'Combien coûte le pagne wax ?')->call('ask')->assertRedirect();
        $project = $space->user->projects()->sole();
        $this->assertTrue($project->isKnowledgeChat());
        $this->assertSame(FirstResponder::PLACE_KNOWLEDGE, FirstResponder::placeOf($project));
        RespondToMessage::dispatchSync($project->agentRuns()->sole());

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/repondre'
            && $request['lieu'] === 'base'
            && $request['contexte']['base']['nom'] === 'Entreprise'
            && $request['contexte']['base']['documents'] === ['Tarifs 2026.pdf']
            && $request['contexte']['base']['passages'][0]['n'] === 1
            && $request['contexte']['base']['passages'][0]['page'] === 2
            && str_contains($request['contexte']['base']['passages'][0]['texte'], '8 000 FCFA'));

        $run = $project->agentRuns()->sole()->fresh();
        $sources = $run->report['knowledge'];
        $this->assertTrue($sources[0]['cited']);
        $this->assertSame('Tarifs 2026.pdf', $sources[0]['document']);
        $this->assertFalse(collect($sources)->contains('n', 7), 'un numéro inventé par le modèle n’existe pas');

        $page = $this->actingAs($space->user)->get(route('projects.show', $project))->assertOk();
        $page->assertSee('data-knowledge-bar', false)->assertSee('data-knowledge-sources', false)
            ->assertSee('Tarifs 2026.pdf')->assertSee('page 2')
            ->assertSee('data-citation-document', false)
            ->assertSee('Une question sur vos documents…');
        // Le chiffre 7 inventé ne devient jamais une pastille.
        $this->assertStringNotContainsString('data-citation="7"', $page->getContent());
        // La conversation reste dans son espace : elle n'apparaît pas dans « Récents ».
        $this->assertStringNotContainsString('data-recent="'.$project->id.'"', $this->actingAs($space->user)->get(route('projects.index'))->getContent());
        $this->actingAs($space->user)->get(route('knowledge.show', $space))->assertSee('data-knowledge-conversation', false)->assertSee($project->name);
    }

    public function test_a_follow_up_question_uses_the_previous_question_for_the_search(): void
    {
        $space = $this->space();
        $project = $space->user->projects()->make(['name' => 'Tarifs']);
        $project->forceFill(['kind' => Project::KIND_CHAT, 'knowledge_space_id' => $space->id])->save();
        $project->messages()->create(['role' => 'user', 'content' => 'Combien coûte la livraison vers Kara ?']);
        $message = $project->messages()->create(['role' => 'user', 'content' => 'Et pour Lomé ?']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $this->fakeRunner();
        $space->documents()->create(['user_id' => $space->user_id, 'name' => 'a.md', 'format' => 'md', 'size' => 1, 'path' => 'p', 'hash' => str_repeat('c', 64), 'status' => KnowledgeDocument::READY])
            ->chunks()->create(['knowledge_space_id' => $space->id, 'position' => 0, 'content' => 'Livraison à Lomé : 1 500 FCFA.', 'vector' => Vectors::pack([1, 0]), 'embedding_model' => 'gemini/gemini-embedding-001']);

        app(KnowledgeAnswers::class)->contextFor($run);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/base/vecteurs'
            && $request['type'] === 'question' && str_contains($request['textes'][0], 'Kara') && str_contains($request['textes'][0], 'Lomé'));
    }

    public function test_the_documents_of_a_conversation_can_be_chosen(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $a = $this->importText($space);
        $b = $this->importText($space, 'conges.txt', 'Vingt-quatre jours de congé.');
        $project = $space->user->projects()->make(['name' => 'Questions']);
        $project->forceFill(['kind' => Project::KIND_CHAT, 'knowledge_space_id' => $space->id])->save();

        Volt::actingAs($space->user)->test('projects.show', ['project' => $project])->call('toggleKnowledgeDocument', $b->id);
        $this->assertSame([$a->id], KnowledgeAnswers::chosen($project->fresh()));
        // Le dernier document ne peut pas être retiré : jamais une conversation sans document.
        Volt::actingAs($space->user)->test('projects.show', ['project' => $project->fresh()])->call('toggleKnowledgeDocument', $a->id);
        $this->assertSame([$a->id], KnowledgeAnswers::chosen($project->fresh()));
        Volt::actingAs($space->user)->test('projects.show', ['project' => $project->fresh()])->call('chooseKnowledgeDocuments', null);
        $this->assertNull(KnowledgeAnswers::chosen($project->fresh()));
    }

    public function test_a_client_never_reaches_the_space_documents_or_conversations_of_another(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $document = $this->importText($space);
        $intruder = User::factory()->create(['country' => 'TG']);

        $this->actingAs($intruder)->get(route('knowledge.show', $space))->assertNotFound();
        Volt::actingAs($intruder)->test('knowledge.show', ['space' => $space->id])->assertNotFound();
        $intruderSpace = $this->space($intruder, 'À moi');
        Volt::actingAs($intruder)->test('knowledge.show', ['space' => $intruderSpace->id])->call('confirmDeletion', $document->id)->assertNotFound();
        Volt::actingAs($intruder)->test('knowledge.show', ['space' => $intruderSpace->id])->call('startReplacing', $document->id)->assertNotFound();
        Volt::actingAs($intruder)->test('knowledge.index')->call('confirmDeletion', $space->id)->assertNotFound();
        // La recherche d'un espace ne lit jamais les passages d'un autre.
        $this->assertSame([], app(KnowledgeSearch::class)->search($intruderSpace, [$document->id], 'pagne wax')['passages']);
        $this->assertNotNull($document->fresh());
    }

    public function test_deleting_a_document_or_a_space_removes_files_passages_vectors_and_conversations(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $document = $this->importText($space);
        $other = $this->importText($space, 'conges.txt', 'Vingt-quatre jours de congé.');
        $path = $document->path;
        Storage::disk(ProjectPhoto::disk())->assertExists($path);

        $page = Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id]);
        $page->call('confirmDeletion', $document->id)->call('delete');
        $this->assertNull($document->fresh());
        $this->assertSame(0, KnowledgeChunk::query()->where('knowledge_document_id', $document->id)->count());
        Storage::disk(ProjectPhoto::disk())->assertMissing($path);

        $project = $space->user->projects()->make(['name' => 'Questions']);
        $project->forceFill(['kind' => Project::KIND_CHAT, 'knowledge_space_id' => $space->id])->save();
        Volt::actingAs($space->user)->test('knowledge.index')->call('confirmDeletion', $space->id)->call('delete');
        $this->assertNull($space->fresh());
        $this->assertNull($project->fresh());
        $this->assertSame(0, KnowledgeChunk::query()->count());
        Storage::disk(ProjectPhoto::disk())->assertMissing($other->path);
    }

    public function test_replacing_a_document_indexes_the_new_file_and_failures_explain_what_to_do(): void
    {
        $this->fakeRunner();
        $space = $this->space();
        $document = $this->importText($space);
        $oldPath = $document->path;

        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->call('startReplacing', $document->id)
            ->set('replacement', UploadedFile::fake()->createWithContent('tarifs-2027.md', "Le pagne wax ordinaire coûte 9 000 FCFA en 2027.\n\nLivraison à Kara : 3 500 FCFA."))
            ->assertHasNoErrors();
        $document->refresh();
        $this->assertSame('tarifs-2027.md', $document->name);
        $this->assertSame(KnowledgeDocument::READY, $document->status);
        $this->assertTrue($document->chunks->contains(fn ($c) => str_contains($c->content, '9 000 FCFA')));
        $this->assertFalse($document->chunks->contains(fn ($c) => str_contains($c->content, '18 000 FCFA')));
        Storage::disk(ProjectPhoto::disk())->assertMissing($oldPath);

        // Un document vide : arrêté tout de suite, avec une phrase claire et « Réessayer ».
        $empty = $this->importText($space, 'vide.txt', "   \n\n  ");
        $this->assertSame(KnowledgeDocument::FAILED, $empty->fresh()->status);
        $this->actingAs($space->user)->get(route('knowledge.show', $space))->assertSee('Kekeli n’a trouvé aucun texte dans ce fichier')->assertSee('data-knowledge-retry', false);

        // Un fichier abîmé, reconnu par le Runner.
        $this->extractBroken = true;
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('uploads', [UploadedFile::fake()->createWithContent('abime.pdf', '%PDF-1.4 abîmé')]);
        $this->assertSame('illisible', $space->documents()->where('name', 'abime.pdf')->value('error'));
    }

    public function test_limits_of_the_space_are_kept(): void
    {
        $this->fakeRunner();
        app(Settings::class)->set('knowledge_documents_per_space', 1);
        $space = $this->space();
        $this->importText($space);
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('autre.txt', 'Autre texte.')])
            ->assertHasErrors('uploads');
        $this->assertSame(1, $space->documents()->count());

        app(Settings::class)->set('knowledge_spaces_max', 1);
        Volt::actingAs($space->user)->test('knowledge.index')->set('name', 'Deuxième')->call('create')->assertHasErrors('name');

        app(Settings::class)->set('knowledge_documents_per_space', 10);
        app(Settings::class)->set('knowledge_chunks_per_space', 2);
        $big = $this->importText($space, 'long.txt', implode("\n\n", array_fill(0, 30, str_repeat('Un long paragraphe sur les pagnes. ', 20))));
        $this->assertSame('espace_plein', $big->fresh()->error);
    }

    public function test_a_runner_that_cannot_be_reached_is_tried_again_then_explained(): void
    {
        Http::fake(['runner.test/*' => Http::response([], 503)]);
        $space = $this->space();
        Bus::fake([IndexKnowledgeDocument::class]);
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('cat.pdf', '%PDF-1.4')]);
        $document = $space->documents()->sole();
        Bus::assertDispatched(IndexKnowledgeDocument::class);

        $job = new IndexKnowledgeDocument($document);
        try {
            $job->handle(app(KnowledgeIndexer::class));
            $this->fail('la tâche devait réessayer');
        } catch (\RuntimeException) {
        }
        $this->assertSame(KnowledgeDocument::PENDING, $document->fresh()->status);
        $job->failed(null);
        $this->assertSame(KnowledgeDocument::FAILED, $document->fresh()->status);
        $this->assertSame('indisponible', $document->fresh()->error);
    }

    public function test_chunker_keeps_pages_titles_and_overlap(): void
    {
        $long = implode(' ', array_map(fn ($i) => "Phrase numéro {$i} sur la boutique de pagnes.", range(1, 60)));
        $chunks = Chunker::split([['page' => 1, 'text' => $long], ['page' => 2, 'text' => 'Page deux.']]);
        $this->assertGreaterThan(2, count($chunks));
        $this->assertTrue(collect($chunks)->every(fn ($c) => mb_strlen($c['content']) <= Chunker::MAX));
        $this->assertSame(2, end($chunks)['page']);
        // Le chevauchement : la fin d'un passage revient au début du suivant.
        $this->assertStringContainsString(mb_substr($chunks[1]['content'], 0, 30), $chunks[0]['content']);

        $md = Chunker::split(TextReader::read("# Guide\n## Congés\nVingt-quatre jours.\n\n## Horaires\nOuverture à 7 h 30.", 'md'));
        $this->assertSame("Horaires\nOuverture à 7 h 30.", end($md)['content']);
        $this->assertSame("Guide\nCongés\nVingt-quatre jours.", $md[0]['content']);
        $this->assertSame([], TextReader::read("\xEF\xBB\xBF", 'txt'));
        $html = TextReader::read('<html><script>alert(1)</script><p>Prix : 8 000 FCFA</p></html>', 'html');
        $this->assertSame('Prix : 8 000 FCFA', $html[0]['text']);
    }

    public function test_links_are_recognized_and_only_google_hosts_are_built(): void
    {
        $this->assertSame('https://www.youtube.com/watch?v=aircAruvnKk', KnowledgeLinks::parse('https://youtu.be/aircAruvnKk?si=abc')['url']);
        $this->assertSame('aircAruvnKk', KnowledgeLinks::parse('https://m.youtube.com/watch?v=aircAruvnKk&t=30')['id']);
        $this->assertSame('aircAruvnKk', KnowledgeLinks::parse('https://www.youtube.com/shorts/aircAruvnKk')['id']);
        $this->assertSame(['type' => 'drive', 'kind' => 'spreadsheets', 'id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', 'url' => 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms'], KnowledgeLinks::parse('https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit#gid=0'));
        $this->assertSame('file', KnowledgeLinks::parse('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345/view?usp=sharing')['kind']);
        $this->assertSame('dossier', KnowledgeLinks::parse('https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz012345')['type']);
        $this->assertNull(KnowledgeLinks::parse('https://exemple.tg/fichier.pdf'));
        $this->assertNull(KnowledgeLinks::parse('https://drive.google.com.exemple.tg/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345'));
        $this->assertNull(KnowledgeLinks::parse('javascript:alert(1)'));
    }

    public function test_a_public_google_sheet_is_downloaded_read_and_refreshed(): void
    {
        $this->fakeRunner();
        $this->drive = ['body' => "Produit,Prix\nPagne wax,8 000 FCFA\nKente,45 000 FCFA", 'headers' => ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"Tarifs.csv\"; filename*=UTF-8''Tarifs%20Ama.csv"]];
        $space = $this->space();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('link', 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit')
            ->call('addLink')->assertHasNoErrors();
        $document = $space->documents()->sole();
        $this->assertSame(KnowledgeDocument::READY, $document->status);
        $this->assertSame('drive', $document->source);
        $this->assertSame('csv', $document->format);
        $this->assertSame('Tarifs Ama.csv', $document->name);
        $this->assertTrue($document->chunks->contains(fn ($c) => str_contains($c->content, '45 000 FCFA')));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/export?format=csv');

        // Le même lien deux fois : refusé ; un dossier : refusé avec une phrase claire.
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('link', 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms')->call('addLink')->assertHasErrors('link');
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('link', 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz012345')->call('addLink')->assertHasErrors('link');
        $this->assertSame(1, $space->documents()->count());

        // « Actualiser » : la dernière version du fichier est relue (jamais copiée d'un double).
        $this->drive = ['body' => "Produit,Prix\nPagne wax,9 500 FCFA", 'headers' => ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="Tarifs.csv"']];
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->call('refresh', $document->id);
        $this->assertTrue($document->fresh()->chunks->contains(fn ($c) => str_contains($c->content, '9 500 FCFA')));
        $this->assertFalse($document->fresh()->chunks->contains(fn ($c) => str_contains($c->content, '45 000 FCFA')));
    }

    public function test_a_private_drive_file_is_explained(): void
    {
        $this->fakeRunner();
        $this->drive = ['body' => '<html>Connexion</html>', 'headers' => ['Content-Type' => 'text/html; charset=utf-8']];
        $space = $this->space();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('link', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345/view')->call('addLink')->assertHasNoErrors();
        $document = $space->documents()->sole();
        $this->assertSame('drive_prive', $document->error);
        $this->actingAs($space->user)->get(route('knowledge.show', $space))->assertSee('Tous les utilisateurs disposant du lien');
    }

    public function test_a_youtube_video_is_transcribed_and_cited_with_its_moment(): void
    {
        $this->useRealFirstResponder();
        $this->fakeRunner(['ok' => true, 'decision' => 'repondre', 'reponse' => 'La livraison vers Kara coûte 3 000 FCFA [1].', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.1-flash-lite', 'refus' => []]);
        $this->oembed = ['title' => 'Présentation de la boutique Ama Pagnes'];
        $lines = array_map(fn ($t) => ['debut' => $t, 'texte' => "À {$t} secondes, Ama présente les nouveaux tissus de la boutique, leurs couleurs et la façon de les entretenir à la maison."], range(0, 290, 10));
        $lines[20] = ['debut' => 200, 'texte' => 'La livraison vers Kara coûte 3 000 FCFA et prend trois jours.'];
        $this->video = ['ok' => true, 'secondes' => 290, 'requetes' => 1, 'morceaux' => $lines];
        $space = $this->space();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('link', 'https://youtu.be/aircAruvnKk')->call('addLink')->assertHasNoErrors();
        $video = $space->documents()->sole();
        $this->assertSame(KnowledgeDocument::READY, $video->status);
        $this->assertSame('Présentation de la boutique Ama Pagnes', $video->name);
        $this->assertSame(5, $video->pages, 'durée en minutes');
        $this->assertGreaterThan(1, $video->chunks_count);
        $kara = $video->chunks->first(fn ($c) => str_contains($c->content, 'Kara'));
        $this->assertGreaterThan(100, $kara->seconds);
        $moment = KnowledgeAnswers::moment($kara->seconds);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/base/video' && $request['url'] === 'https://www.youtube.com/watch?v=aircAruvnKk' && $request['minutes_max'] === 120);
        $this->actingAs($space->user)->get(route('knowledge.show', $space))->assertSee('YouTube')->assertSee('5 min');

        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('question', 'Combien coûte la livraison vers Kara ?')->call('ask');
        $project = $space->user->projects()->sole();
        RespondToMessage::dispatchSync($project->agentRuns()->sole());
        $run = $project->agentRuns()->sole()->fresh();
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/repondre' && collect($request['contexte']['base']['passages'])->contains('moment', $moment));
        $this->actingAs($space->user)->get(route('projects.show', $project))
            ->assertSee('à '.$moment)
            ->assertSee('https://www.youtube.com/watch?v=aircAruvnKk&amp;t='.$kara->seconds.'s', false)
            ->assertSee('data-knowledge-video-link', false);
    }

    public function test_a_private_video_is_refused_and_the_daily_minutes_are_kept(): void
    {
        $this->fakeRunner();
        $this->oembed = null;
        $space = $this->space();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('link', 'https://www.youtube.com/watch?v=aircAruvnKk')->call('addLink')->assertHasErrors('link');
        $this->assertSame(0, $space->documents()->count());

        app(Settings::class)->set('knowledge_video_minutes_per_day', 0);
        $this->oembed = ['title' => 'Vidéo'];
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])->set('link', 'https://www.youtube.com/watch?v=aircAruvnKk')->call('addLink');
        $this->assertSame('videos_du_jour', $space->documents()->sole()->error);
    }

    public function test_files_up_to_one_hundred_megabytes_are_accepted(): void
    {
        $this->fakeRunner();
        $this->assertSame(102400, app(Settings::class)->int('knowledge_file_max_kb'));
        $this->assertContains('max:102400', config('livewire.temporary_file_upload.rules'));
        $space = $this->space();
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('gros.pdf', '%PDF-1.4'.str_repeat(' ', 60 * 1024 * 1024))])
            ->assertHasNoErrors();
        $this->assertSame(1, $space->documents()->count());
        Volt::actingAs($space->user)->test('knowledge.show', ['space' => $space->id])
            ->set('uploads', [UploadedFile::fake()->createWithContent('trop.pdf', '%PDF-1.4'.str_repeat(' ', 101 * 1024 * 1024))])
            ->assertHasErrors();
    }
}
