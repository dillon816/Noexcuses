<?php

namespace App\Tests\Service;

use App\Entity\Exercice;
use App\Entity\Seance;
use App\Entity\SerieExercice;
use App\Entity\User;
use App\Repository\SeanceRepository;
use App\Service\EntrainementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EntrainementServiceTest extends TestCase
{
    public function testTonnageCalculation(): void
    {
        $serie = new SerieExercice();
        $serie->setRepetitions(5);
        $serie->setChargeKg('100');

        $exercice = new Exercice();
        $exercice->setNom('Squat');
        $serie->setExercice($exercice);
        $serie->calculerTonnage();

        $this->assertEquals('500.00', $serie->getTonnage());
    }

    public function testSeanceTonnageAggregateSeries(): void
    {
        $seance = new Seance();

        $s1 = new SerieExercice();
        $s1->setRepetitions(5);
        $s1->setChargeKg('100');
        $e1 = new Exercice();
        $e1->setNom('Squat');
        $s1->setExercice($e1);
        $s1->calculerTonnage();
        $seance->addSerie($s1);

        $s2 = new SerieExercice();
        $s2->setRepetitions(8);
        $s2->setChargeKg('60');
        $e2 = new Exercice();
        $e2->setNom('Développé couché');
        $s2->setExercice($e2);
        $s2->calculerTonnage();
        $seance->addSerie($s2);

        $seance->calculerTonnage();

        $this->assertEquals('980.00', $seance->getTonnageTotal());
    }

    public function testArchiverSeance(): void
    {
        $seance = new Seance();
        $seance->archiver();

        $this->assertSame(Seance::STATUT_ARCHIVEE, $seance->getStatut());
    }
}
