<?php

namespace App\Tests\Controller;

use App\Entity\Exercice;
use App\Entity\PoidsHistorique;
use App\Entity\Seance;
use App\Entity\SerieExercice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests d'intégration de l'export RGPD (JSON + Excel).
 * Vérifie la protection JWT, l'absence de données sensibles, l'isolation par
 * utilisateur, l'exclusion des séances non terminées, et les feuilles Excel.
 */
class ExportControllerTest extends WebTestCase
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
        foreach (['serie_exercice', 'seance', 'exercice', 'poids_historique', 'utilisateur'] as $table) {
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

    private function addPoids(User $user, string $date, float $kg): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $p = new PoidsHistorique();
        $p->setUtilisateur($user)->setDatePesee(new \DateTime($date))->setPoidsKg((string) $kg);
        $em->persist($p);
        $em->flush();
    }

    private function addSeance(User $user, string $statut, string $nom): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exercice = new Exercice();
        $exercice->setNom('Exo ' . uniqid());
        $em->persist($exercice);

        $seance = new Seance();
        $seance->setUtilisateur($user)->setNom($nom)->setDateSeance(new \DateTime('2026-01-15'))->setStatut($statut);
        $serie = new SerieExercice();
        $serie->setExercice($exercice)->setRepetitions(10)->setChargeKg('50')->setNumeroSerie(1);
        $serie->calculerTonnage();
        $seance->addSerie($serie);
        $seance->calculerTonnage();
        $em->persist($seance);
        $em->persist($serie);
        $em->flush();
    }

    // ---------- Protection JWT ----------

    public function testExportJsonWithoutTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/profil/export/json');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testExportExcelWithoutTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/profil/export/excel');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testExportJsonAuthenticatedReturns200(): void
    {
        $client = static::createClient();
        $this->createUser('json200@nx.com');
        $token = $this->tokenFor($client, 'json200@nx.com');

        $client->request('GET', '/api/profil/export/json', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        foreach (['profil', 'objectifs', 'poids', 'nutrition', 'entrainements', 'sommeil', 'recovery'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    public function testExportExcelAuthenticatedReturns200(): void
    {
        $client = static::createClient();
        $this->createUser('excel200@nx.com');
        $token = $this->tokenFor($client, 'excel200@nx.com');

        $client->request('GET', '/api/profil/export/excel', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringStartsWith('PK', $client->getResponse()->getContent()); // signature xlsx (zip)
    }

    // ---------- Données sensibles ----------

    public function testExportJsonHasNoSensitiveData(): void
    {
        $client = static::createClient();
        $this->createUser('sensitive@nx.com');
        $token = $this->tokenFor($client, 'sensitive@nx.com');

        $client->request('GET', '/api/profil/export/json', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $raw = $client->getResponse()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('password', $raw);
        $this->assertStringNotContainsStringIgnoringCase('mot_de_passe', $raw);
        $this->assertStringNotContainsString('password123', $raw); // la valeur du mot de passe ne fuite pas
        $this->assertStringNotContainsString('$2y$', $raw);         // pas de hash bcrypt
    }

    // ---------- Isolation par utilisateur ----------

    public function testExportContainsOnlyOwnData(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner@nx.com');
        $other = $this->createUser('other@nx.com');
        $this->addPoids($owner, '2026-01-10', 80.5);
        $this->addPoids($other, '2026-01-10', 99.9);

        $token = $this->tokenFor($client, 'owner@nx.com');
        $client->request('GET', '/api/profil/export/json', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $data = json_decode($client->getResponse()->getContent(), true);

        $poids = array_column($data['poids'], 'poids_kg');
        $this->assertContains(80.5, $poids);
        $this->assertNotContains(99.9, $poids); // les données de l'autre utilisateur n'apparaissent jamais
    }

    // ---------- Historique : modèle / en cours exclus ----------

    public function testExportExcludesModeleAndEnCoursSeances(): void
    {
        $client = static::createClient();
        $user = $this->createUser('seances@nx.com');
        $this->addSeance($user, Seance::STATUT_TERMINEE, 'Pull termine');
        $this->addSeance($user, Seance::STATUT_MODELE, 'Modele Pull');
        $this->addSeance($user, Seance::STATUT_EN_COURS, 'Pull en cours');

        $token = $this->tokenFor($client, 'seances@nx.com');
        $client->request('GET', '/api/profil/export/json', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $data = json_decode($client->getResponse()->getContent(), true);

        $noms = array_column($data['entrainements'], 'nom');
        $this->assertContains('Pull termine', $noms);
        $this->assertNotContains('Modele Pull', $noms);
        $this->assertNotContains('Pull en cours', $noms);
    }

    // ---------- Feuilles Excel ----------

    public function testExportExcelHasExpectedSheets(): void
    {
        $client = static::createClient();
        $this->createUser('sheets@nx.com');
        $token = $this->tokenFor($client, 'sheets@nx.com');

        $client->request('GET', '/api/profil/export/excel', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $tmp = tempnam(sys_get_temp_dir(), 'test_export_') . '.xlsx';
        file_put_contents($tmp, $client->getResponse()->getContent());

        $reader = new Reader();
        $reader->open($tmp);
        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }
        $reader->close();
        @unlink($tmp);

        foreach (['Profil', 'Objectifs', 'Poids', 'Nutrition', 'Entrainements', 'Sommeil', 'Recovery'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }
}
