<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les captures d'écran et les photos jointes sont lues par le modèle (16 novembre 2026) : le site envoie l'image
 * elle-même au Runner, plus seulement leur nombre.
 */
class MessageImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screenshot_joined_to_the_message_is_sent_to_the_runner(): void
    {
        Storage::fake(ProjectPhoto::disk());
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test-images']);
        $user = User::factory()->create(['country' => 'TG']);
        $chat = $user->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $message = $chat->messages()->create(['role' => 'user', 'content' => 'Pourquoi mon retrait ne passe pas ?']);
        $capture = new UploadedFile(base_path('tests/Fixtures/capture-erreur.png'), 'capture.png', 'image/png', null, true);
        ProjectPhoto::store($chat, $capture, $message);
        $run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        Http::fake(['runner.test/*' => Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Il manque 6 550 FCFA.'])]);

        app(RunnerClient::class)->respond($run, 'discussion');

        Http::assertSent(function (Request $request) {
            $images = $request['images'] ?? [];

            return count($images) === 1
                && $images[0]['type'] === 'image/png'
                && base64_decode($images[0]['donnees']) === file_get_contents(base_path('tests/Fixtures/capture-erreur.png'))
                && $request['contexte']['photos'] === 1;
        });
    }

    public function test_a_message_without_image_sends_none_and_a_big_photo_is_reduced(): void
    {
        Storage::fake(ProjectPhoto::disk());
        $user = User::factory()->create();
        $chat = $user->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $message = $chat->messages()->create(['role' => 'user', 'content' => 'Bonjour']);
        $run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $this->assertSame([], app(RunnerClient::class)->messageImages($run));

        // Une grande photo de téléphone (plus de 3,5 Mo) : réduite à 1 600 pixels, en JPEG, jamais plus de 5 Mo.
        $image = imagecreatetruecolor(4000, 3000);
        for ($i = 0; $i < 200000; $i++) {
            imagesetpixel($image, random_int(0, 3999), random_int(0, 2999), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($image, null, 0);
        $bytes = (string) ob_get_clean();
        $this->assertGreaterThan(3_500_000, strlen($bytes));
        $message2 = $chat->messages()->create(['role' => 'user', 'content' => 'Et celle-ci ?']);
        $path = 'photos/'.$chat->id.'/grande.png';
        Storage::disk(ProjectPhoto::disk())->put($path, $bytes);
        $chat->photos()->create(['name' => 'grande.png', 'path' => $path, 'size' => strlen($bytes), 'message_id' => $message2->id]);
        $run2 = $chat->agentRuns()->create(['message_id' => $message2->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $images = app(RunnerClient::class)->messageImages($run2);
        $this->assertCount(1, $images);
        $this->assertSame('image/jpeg', $images[0]['type']);
        $this->assertLessThanOrEqual(1600, getimagesizefromstring(base64_decode($images[0]['donnees']))[0]);
    }
}
