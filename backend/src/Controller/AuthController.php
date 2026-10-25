<?php

namespace App\Controller;

use App\Exception\EmailAlreadyUsedException;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        // Limiteur configure dans config/packages/rate_limiter.yaml (limiter "login")
        private readonly RateLimiterFactory $loginLimiter,
    ) {}

    /**
     * POST /api/login : connexion par email et mot de passe, renvoie le JWT.
     * Bloqué à 5 essais par 15 min (clé IP + email), au-delà on répond 429.
     * Mauvais identifiants : 401, avec le même message que l'email existe ou pas.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email et mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        // Anti brute force : on limite les tentatives par IP + email
        $limiter = $this->loginLimiter->create($request->getClientIp() . ':' . $data['email']);
        if (false === $limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $token = $this->authService->login($data['email'], $data['password']);
            $limiter->reset(); // connexion réussie : on efface le compteur
            return $this->json(['token' => $token]);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * POST /api/auth/google : connexion avec Google.
     * Le front envoie le jeton donné par Google, le service le fait vérifier puis on renvoie notre propre JWT.
     */
    #[Route('/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function googleLogin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['credential'])) {
            return $this->json(['error' => 'Token Google manquant.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $token = $this->authService->loginWithGoogle($data['credential']);
            return $this->json(['token' => $token]);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Connexion Google refusée.'], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * POST /api/register : création de compte.
     * Ici on contrôle les champs requis et les 8 caractères du mot de passe, le service gère le reste.
     * 201 si le compte est créé, 409 si l'email est déjà pris, 400 si une donnée est invalide.
     */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $required = ['email', 'password', 'prenom', 'nom'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Le champ '$field' est requis."], Response::HTTP_BAD_REQUEST);
            }
        }

        if (strlen($data['password']) < 8) {
            return $this->json(['error' => 'Le mot de passe doit faire au moins 8 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->authService->register(
                $data['email'],
                $data['password'],
                $data['prenom'],
                $data['nom'],
            );

            return $this->json([
                'message' => 'Compte créé avec succès.',
                'userId'  => $user->getId(),
            ], Response::HTTP_CREATED);
        } catch (EmailAlreadyUsedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            // Contraintes Assert non respectées (format d'email, longueur du prénom...)
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
