<?php

namespace App\Controller;

use App\Service\ProfilService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/profil')]
class ProfilController extends AbstractController
{
    public function __construct(private readonly ProfilService $profilService) {}

    #[Route('', name: 'api_profil_get', methods: ['GET'])]
    public function getProfil(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'id'              => $user->getId(),
            'email'           => $user->getEmail(),
            'prenom'          => $user->getPrenom(),
            'nom'             => $user->getNom(),
            'taille'          => $user->getTaille(),
            'sexe'            => $user->getSexe(),
            'poidsInitial'    => $user->getPoidsInitial(),
            'caloriesObjectif' => $user->getCaloriesObjectif(),
            'niveauActivite'  => $user->getNiveauActivite(),
        ]);
    }

    #[Route('', name: 'api_profil_update', methods: ['PUT', 'PATCH'])]
    public function updateProfil(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $user = $this->profilService->updateProfil($this->getUser(), $data);
            return $this->json(['message' => 'Profil mis à jour.', 'prenom' => $user->getPrenom()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/objectif', name: 'api_profil_objectif', methods: ['POST', 'PUT'])]
    public function setObjectif(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['type']) || empty($data['caloriesJour'])) {
            return $this->json(['error' => 'type et caloriesJour sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $objectif = $this->profilService->setObjectif(
            $this->getUser(),
            $data['type'],
            (int) $data['caloriesJour'],
            isset($data['proteinesG']) ? (int) $data['proteinesG'] : null,
            isset($data['glucidesG'])  ? (int) $data['glucidesG']  : null,
            isset($data['lipidesG'])   ? (int) $data['lipidesG']   : null,
        );

        return $this->json(['message' => 'Objectif défini.', 'id' => $objectif->getId()], Response::HTTP_CREATED);
    }

    #[Route('/poids', name: 'api_profil_poids', methods: ['POST'])]
    public function logPoids(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['poidsKg'])) {
            return $this->json(['error' => 'poidsKg est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $entry = $this->profilService->logPoids(
            $this->getUser(),
            (float) $data['poidsKg'],
            isset($data['date']) ? new \DateTime($data['date']) : null,
        );

        return $this->json(['message' => 'Poids enregistré.', 'id' => $entry->getId()], Response::HTTP_CREATED);
    }
}
