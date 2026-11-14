<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Support\SecretScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un mot de passe ou un code clairement annoncé dans un message (« mon mot de passe est… », « le code est… ») est
 * masqué avant tout enregistrement, comme une clé, et n'arrive donc jamais au modèle. Les prix, les montants, les
 * numéros de téléphone et les codes publics (promotion, code postal, couleur) ne sont jamais touchés.
 */
class AnnouncedSecretsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function phrases(): array
    {
        return [
            'mot de passe' => ['Mon mot de passe est Pagne2026!', 'Mon mot de passe est [mot de passe masqué]'],
            'mot de passe au milieu' => ['Pour info, mon mot de passe Kekeli est Pagne2026! et je suis à Lomé.', 'Pour info, mon mot de passe Kekeli est [mot de passe masqué] et je suis à Lomé.'],
            'mdp' => ['Mon mdp = ama@2026', 'Mon mdp = [mot de passe masqué]'],
            'code' => ['le code est 482913', 'le code est [code masqué]'],
            'code SMS' => ['Voici le code SMS : 482 913, merci', 'Voici le code SMS : [code masqué], merci'],
            'code de vérification' => ["Mon code de vérification c'est A7K9Q2.", "Mon code de vérification c'est [code masqué]."],
            'code reçu' => ["Le code que j'ai reçu est 739204", "Le code que j'ai reçu est [code masqué]"],
            'téléphone (piège)' => ['Mon numéro est le +228 90 12 34 56', 'Mon numéro est le +228 90 12 34 56'],
            'prix (piège)' => ['Le pagne coûte 15 000 FCFA, livraison 1 000 FCFA.', 'Le pagne coûte 15 000 FCFA, livraison 1 000 FCFA.'],
            'code promo (piège)' => ["Utilise le code promo NOEL20 sur l'affiche", "Utilise le code promo NOEL20 sur l'affiche"],
            'mot de passe oublié (piège)' => ["J'ai oublié mon mot de passe, que faire ?", "J'ai oublié mon mot de passe, que faire ?"],
            'code postal (piège)' => ['Le code postal est 01 BP 1234', 'Le code postal est 01 BP 1234'],
        ];
    }

    #[DataProvider('phrases')]
    public function test_an_announced_password_or_code_is_masked_and_nothing_else(string $text, string $expected): void
    {
        $this->assertSame($expected, SecretScanner::redactMessage($text)[0]);
    }

    public function test_the_message_is_stored_masked_so_the_model_never_reads_it(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('C’est noté.')->answers('Merci.');
        Volt::actingAs($user)->test('site-prompt')->set('description', 'Mon mot de passe Kekeli est Pagne2026! Le code SMS est 482913.')->call('build');

        $stored = Message::query()->where('role', Message::ROLE_USER)->sole()->content;
        $this->assertSame('Mon mot de passe Kekeli est [mot de passe masqué] Le code SMS est [code masqué].', $stored);
        $this->assertSame($stored, $this->model->received[0]['message'], 'le modèle ne reçoit que le texte masqué');

        $chat = $user->projects()->sole();
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->set('content', 'le code est 739204')->call('send')->assertHasNoErrors();
        $this->assertSame('le code est [code masqué]', $chat->messages()->reorder()->where('role', Message::ROLE_USER)->latest('id')->value('content'));
        $this->assertSame('le code est [code masqué]', $this->model->received[1]['message']);
    }
}
