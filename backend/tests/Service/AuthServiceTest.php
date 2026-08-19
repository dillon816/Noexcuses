<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AuthServiceTest extends TestCase
{
    private function buildService(bool $emailExists = false): AuthService
    {
        $em         = $this->createMock(EntityManagerInterface::class);
        $repo       = $this->createMock(UserRepository::class);
        $hasher     = $this->createMock(UserPasswordHasherInterface::class);
        $validator  = $this->createMock(ValidatorInterface::class);
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);

        $repo->method('findByEmail')->willReturn($emailExists ? new User() : null);
        $hasher->method('hashPassword')->willReturn('hashed_password');
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $em->method('persist');
        $em->method('flush');

        return new AuthService($em, $repo, $hasher, $validator, $jwtManager, $httpClient, 'test-google-client-id');
    }

    public function testRegisterCreatesUser(): void
    {
        $service = $this->buildService(false);
        $user = $service->register('test@example.com', 'password123', 'Jean', 'Dupont');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('Jean', $user->getPrenom());
    }

    public function testRegisterThrowsWhenEmailExists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('déjà utilisé');

        $service = $this->buildService(true);
        $service->register('existing@example.com', 'password123', 'Jean', 'Dupont');
    }
}
