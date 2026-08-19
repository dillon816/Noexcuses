<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AuthService
{
    // Endpoint officiel Google : il valide la signature et l'expiration du token cote Google
    private const GOOGLE_TOKENINFO = 'https://oauth2.googleapis.com/tokeninfo';

    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserRepository              $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface          $validator,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly HttpClientInterface         $httpClient,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private readonly string                      $googleClientId,
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

    /**
     * Connexion via Google. Le frontend recupere un token d'identite signe par Google
     * et nous l'envoie ici. On le fait valider par Google, puis on retrouve le compte par
     * email (ou on le cree a la volee) et on renvoie notre propre JWT, comme un login normal.
     */
    public function loginWithGoogle(string $credential): string
    {
        $payload = $this->verifyGoogleToken($credential);

        // Le token doit avoir ete emis pour NOTRE application, sinon on refuse
        if (($payload['aud'] ?? null) !== $this->googleClientId) {
            throw new \InvalidArgumentException('Token Google non destine a cette application.');
        }
        // Google doit avoir verifie que l'email appartient bien a l'utilisateur
        $emailVerified = $payload['email_verified'] ?? null;
        if ($emailVerified !== true && $emailVerified !== 'true') {
            throw new \InvalidArgumentException('Email Google non verifie.');
        }
        $email = $payload['email'] ?? null;
        if (!$email) {
            throw new \InvalidArgumentException('Email absent du token Google.');
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            // Premier login Google : on cree le compte. L'utilisateur ne choisit pas de mot de passe
            // (il se connecte via Google), mais la colonne est NOT NULL : on stocke donc un hash aleatoire.
            $user = new User();
            $user->setEmail($email);
            $user->setPrenom($this->safeName($payload['given_name'] ?? null, 'Utilisateur'));
            $user->setNom($this->safeName($payload['family_name'] ?? null, 'Google'));
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(16))));

            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                throw new \InvalidArgumentException((string) $errors);
            }

            $this->em->persist($user);
            $this->em->flush();
        }

        return $this->jwtManager->create($user);
    }

    /**
     * Demande a Google de valider le token et renvoie les infos qu'il contient (email, prenom...).
     * Si Google renvoie une erreur, le token est invalide ou expire.
     */
    private function verifyGoogleToken(string $credential): array
    {
        try {
            $response = $this->httpClient->request('GET', self::GOOGLE_TOKENINFO, [
                'query'   => ['id_token' => $credential],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \InvalidArgumentException('Token Google invalide.');
            }

            return $response->toArray(false);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Verification du token Google impossible.');
        }
    }

    // Prenom/nom Google parfois vides ou trop courts : on met une valeur par defaut pour passer la validation
    private function safeName(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        return mb_strlen($value) >= 2 ? $value : $fallback;
    }
}
