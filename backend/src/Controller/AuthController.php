<?php

namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(private readonly AuthService $authService) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email et mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $token = $this->authService->login($data['email'], $data['password']);
            return $this->json(['token' => $token]);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
        }
    }

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
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }
}
