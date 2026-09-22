<?php

namespace App\Tests\Controller;

use App\Entity\Exercice;
use App\Entity\PoidsHistorique;
use App\Entity\Seance;
use App\Entity\SerieExercice;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests d'intégration du droit à l'oubli (RGPD) : suppression du compte.
 * Vérifie la protection JWT, la suppression effective de l'utilisateur, et
 * l'effacement en cascade de ses données (poids, séances, séries).
 */
class ProfilDeleteTest extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($meta);
        $schemaTool->createSchema($meta);
        static::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        $conn = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'serie_exercice', 'seance', 'exercice', 'ligne_journal', 'journal_alimentaire',
            'aliment', 'poids_historique', 'sommeil', 'recovery_score', 'objectif', 'utilisateur',
        ] as $table) {
            $conn->executeStatement("TRUNCATE TABLE $table");
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ---------- Helpers ----------

    private function createUser(string $email): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email)->setPrenom('Data')->setNom('Owner');
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function tokenFor(KernelBrowser $client, string $email): string
    {
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => 'password123']));

        return json_decode($client->getResponse()->getContent(), true)['token'];
    }

    private function addPoids(User $user, float $kg): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $p = new PoidsHistorique();
        $p->setUtilisateur($user)->setDatePesee(new \DateTime('2026-01-10'))->setPoidsKg((string) $kg);
        $em->persist($p);
        $em->flush();
    }

    private function addSeance(User $user): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exercice = new Exercice();
        $exercice->setNom('Exo ' . uniqid());
        $em->persist($exercice);

        $seance = new Seance();
        $seance->setUtilisateur($user)->setNom('Pull')->setDateSeance(new \DateTime('2026-01-15'))->setStatut(Seance::STATUT_TERMINEE);
        $serie = new SerieExercice();
        $serie->setExercice($exercice)->setRepetitions(10)->setChargeKg('50')->setNumeroSerie(1);
        $serie->calculerTonnage();
        $seance->addSerie($serie);
        $seance->calculerTonnage();
        $em->persist($seance);
        $em->persist($serie);
        $em->flush();
    }

    private function countRows(string $table): int
    {
        $conn = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        return (int) $conn->fetchOne("SELECT COUNT(*) FROM $table");
    }

    // ---------- Protection JWT ----------

    public function testDeleteWithoutTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/profil');
        $this->assertResponseStatusCodeSame(401);
    }

    // ---------- Suppression du compte ----------

    public function testDeleteAccountReturns204AndRemovesUser(): void
    {
        $client = static::createClient();
        $this->createUser('delete-me@nx.com');
        $token = $this->tokenFor($client, 'delete-me@nx.com');

        $client->request('DELETE', '/api/profil', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseStatusCodeSame(204);

        $repo = static::getContainer()->get(UserRepository::class);
        $this->assertNull($repo->findOneBy(['email' => 'delete-me@nx.com']), 'Le compte doit être supprimé.');
    }

    // ---------- Effacement en cascade des données ----------

    public function testDeleteAlsoRemovesUserData(): void
    {
        $client = static::createClient();
        $user = $this->createUser('cascade@nx.com');
        $this->addPoids($user, 80.5);
        $this->addSeance($user);

        $this->assertSame(1, $this->countRows('poids_historique'));
        $this->assertSame(1, $this->countRows('seance'));
        $this->assertSame(1, $this->countRows('serie_exercice'));

        $token = $this->tokenFor($client, 'cascade@nx.com');
        $client->request('DELETE', '/api/profil', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseStatusCodeSame(204);

        // Les données personnelles disparaissent avec le compte (onDelete: CASCADE).
        $this->assertSame(0, $this->countRows('poids_historique'));
        $this->assertSame(0, $this->countRows('seance'));
        $this->assertSame(0, $this->countRows('serie_exercice'));
    }
}
