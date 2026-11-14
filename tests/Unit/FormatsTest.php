<?php

namespace Tests\Unit;

use App\Support\Formats;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Audit unslop, passe 4 : une seule façon d'écrire les dates, les heures et les durées, à l'heure de Lomé, sans
 * abréviation, avec des espaces insécables (jamais « 04/12/2026 », « 16:15 » ni « 513 s »).
 */
class FormatsTest extends TestCase
{
    public function test_dates_hours_and_durations_are_written_the_french_way(): void
    {
        $moment = CarbonImmutable::parse('2026-12-04 16:15:00', 'UTC');
        $n = "\u{00A0}";

        $this->assertSame("4{$n}décembre{$n}2026", Formats::date($moment));
        $this->assertSame("16{$n}h{$n}15", Formats::time($moment));
        $this->assertSame("4{$n}décembre{$n}2026 à 16{$n}h{$n}15", Formats::dateTime($moment));
        $this->assertSame("6{$n}s", Formats::duration(6));
        $this->assertSame("8{$n}min 33{$n}s", Formats::duration(513));
        $this->assertSame("1\u{202F}250", Formats::number(1250));
        $this->assertSame('', Formats::date(null));
    }

    public function test_no_numeric_date_is_shown_to_the_client(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $fichier) {
            if (str_ends_with($fichier->getFilename(), '.blade.php')) {
                $this->assertDoesNotMatchRegularExpression("/format\\('d\\/m|format\\('H:i'\\)/", file_get_contents($fichier->getPathname()), $fichier->getFilename());
            }
        }
    }
}
