<?php

namespace App\Tests\Service;

use App\Entity\RecoveryScore;
use App\Entity\Seance;
use App\Entity\User;
use App\Repository\JournalAlimentaireRepository;
use App\Repository\RecoveryScoreRepository;
use App\Repository\SeanceRepository;
use App\Service\RecoveryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RecoveryServiceTest extends TestCase
{
    private function buildService(
        float $charge7j        = 0,
        float $bilanCalorique  = 0,
        float $heuresSommeil   = 8,
    ): RecoveryService {
        $em         = $this->createMock(EntityManagerInterface::class);
        $recoveryRepo = $this->createMock(RecoveryScoreRepository::class);
        $seanceRepo = $this->createMock(SeanceRepository::class);
        $journalRepo = $this->createMock(JournalAlimentaireRepository::class);

        $seanceRepo->method('getTonnageLast7Days')->willReturn($charge7j);
        $journalRepo->method('findByUserAndDate')->willReturn(null);

        $em->method('getRepository')->willReturn(
            $this->createMock(\Doctrine\ORM\EntityRepository::class)
        );
        $em->method('persist');
        $em->method('flush');

        return new RecoveryService($em, $recoveryRepo, $seanceRepo, $journalRepo);
    }

    public function testScoreMaxWhenOptimalConditions(): void
    {
        $service = $this->buildService(charge7j: 3000, heuresSommeil: 8);
        $user    = new User();
        // Via reflection since no setter for id in tests
        $score   = $this->invokePrivate($service, 'computeScore', [3000, 0, 8, $user]);

        $this->assertSame(100, $score);
    }

    public function testScoreReposWhenOvertrainedAndNoSleep(): void
    {
        $service = $this->buildService(charge7j: 50000, heuresSommeil: 3);
        $user    = new User();
        $score   = $this->invokePrivate($service, 'computeScore', [50000, -800, 3, $user]);

        $this->assertLessThan(40, $score);
    }

    public function testRecommandationSeanceNormale(): void
    {
        $service = $this->buildService();
        $result  = $this->invokePrivate($service, 'getRecommandation', [75]);

        $this->assertSame(RecoveryScore::RECOMMANDATION_NORMALE, $result);
    }

    public function testRecommandationRepos(): void
    {
        $service = $this->buildService();
        $result  = $this->invokePrivate($service, 'getRecommandation', [20]);

        $this->assertSame(RecoveryScore::RECOMMANDATION_REPOS, $result);
    }

    private function invokePrivate(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }
}
