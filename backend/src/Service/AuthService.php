<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserRepository              $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface          $validator,
        private readonly JWTTokenManagerInterface    $jwtManager,
    ) {}

    /**
     * Crée un compte utilisateur. Vérifie d'abord que l'email n'existe pas déjà,
     * puis hache le mot de passe avec bcrypt avant de persister en base.
     */
    public function register(string $email, string $password, string $prenom, string $nom): User
    {
        if ($this->userRepository->findByEmail($email)) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé.');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPrenom($prenom);
        $user->setNom($nom);
        // Le mot de passe est haché par Symfony, jamais stocké en clair
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException((string) $errors);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Vérifie les identifiants et retourne un token JWT signé si c'est bon.
     * Le token est ensuite envoyé dans le header Authorization par le frontend.
     */
    public function login(string $email, string $password): string
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            throw new \InvalidArgumentException('Identifiants invalides.');
        }

        return $this->jwtManager->create($user);
    }
}
