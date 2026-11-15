<?php

namespace Tests\Unit;

use App\Agent\RunReport;
use PHPUnit\Framework\TestCase;

class RunReportCodeTest extends TestCase
{
    public function test_code_copied_into_the_final_message_is_never_shown_to_the_client(): void
    {
        // Essai réel du 15 novembre 2026 : une automatisation recopiait ses tests dans le message final.
        $message = "Votre automatisation est prête : elle envoie le résumé des ventes chaque lundi.\n"
            ."test('sans clé d’e-mail, rien n’est envoyé', async () => {\n"
            ."const appeler = async () => reponse({ choices: [] })\n"
            ."assert.equal(resultat.ok, false)\n"
            ."})\n"
            .'Ajoutez votre clé dans les Variables secrètes pour recevoir les e-mails.';

        $text = RunReport::text($message);

        $this->assertStringContainsString('Votre automatisation est prête', $text);
        $this->assertStringContainsString('Ajoutez votre clé dans les Variables secrètes', $text);
        $this->assertStringNotContainsString('assert', $text);
        $this->assertStringNotContainsString('=>', $text);
        $this->assertStringNotContainsString('const', $text);
    }
}
