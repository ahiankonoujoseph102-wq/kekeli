<?php

namespace Tests\Unit;

use App\Agent\MessageIntent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessageIntentTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function messages(): array
    {
        return [
            'hébergement' => ['Où héberger mon site ?', MessageIntent::QUESTION],
            'prix' => ['Combien ça coûte ?', MessageIntent::QUESTION],
            'question sans point d’interrogation' => ['Combien coûte un nom de domaine', MessageIntent::QUESTION],
            'Google' => ['Est-ce que mon site est visible sur Google ?', MessageIntent::QUESTION],
            'demande directe' => ['Ajoute un bouton WhatsApp', MessageIntent::CHANGE],
            'demande polie' => ['Peux-tu ajouter le menu ?', MessageIntent::CHANGE],
            'demande polie au vouvoiement' => ['Pouvez-vous mettre les prix en FCFA ?', MessageIntent::CHANGE],
            'description d’un site' => ['Un site pour mon restaurant', MessageIntent::CHANGE],
            'remarque et demande' => ['Le bouton est trop petit, agrandis-le.', MessageIntent::CHANGE],
            'doute : faut-il changer' => ['Faut-il changer la couleur ?', MessageIntent::DOUBT],
            'doute : comment ajouter' => ['Comment ajouter un produit ?', MessageIntent::DOUBT],
        ];
    }

    #[DataProvider('messages')]
    public function test_kekeli_knows_a_question_from_a_request_for_a_change(string $message, string $intent): void
    {
        $this->assertSame($intent, (new MessageIntent)->detect($message));
    }
}
