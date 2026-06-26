<?php

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests d'intégration pour AuthController.
 * Utilise la base noexcuses_test (MySQL) recréée avant chaque classe de test.
 * On teste les vrais endpoints HTTP du début à la fin : Controller → Service → BDD.
 */
class AuthControllerTest extends WebTestCase
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
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->getConnection()->executeStatement('TRUNCATE TABLE utilisateur');
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function testRegisterReturns201(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'test@noexcuses.fr',
            'password' => 'password123',
            'prenom'   => 'Test',
            'nom'      => 'User',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('userId', $data);
    }

    public function testRegisterWithDuplicateEmailReturns409(): void
    {
        $client = static::createClient();

        $payload = json_encode([
            'email'    => 'duplicate@noexcuses.fr',
            'password' => 'password123',
            'prenom'   => 'Test',
            'nom'      => 'User',
        ]);

        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testLoginWithValidCredentialsReturns200(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'login@noexcuses.fr',
            'password' => 'password123',
            'prenom'   => 'Login',
            'nom'      => 'Test',
        ]));

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'login@noexcuses.fr',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'wrongpwd@noexcuses.fr',
            'password' => 'password123',
            'prenom'   => 'Wrong',
            'nom'      => 'Pwd',
        ]));

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'wrongpwd@noexcuses.fr',
            'password' => 'mauvais_mdp',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedRouteWithoutTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/profil');
        $this->assertResponseStatusCodeSame(401);
    }
}
