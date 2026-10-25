<?php

namespace App\Controller;

use App\Service\RecoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/recovery')]
class RecoveryController extends AbstractController
{
    public function __construct(private readonly RecoveryService $recoveryService) {}

    /**
     * GET /api/recovery/score : le dernier score calculé.
     * S'il n'y en a pas encore, on répond 200 avec un message, ce n'est pas une erreur.
     */
    #[Route('/score', name: 'api_recovery_score_get', methods: ['GET'])]
    public function getScore(): JsonResponse
    {
        $score = $this->recoveryService->getLatestScore($this->getUser());

        if (!$score) {
            return $this->json(['message' => 'Aucun score calculé. POST /api/recovery/score pour calculer.'], Response::HTTP_OK);
        }

        return $this->json([
            'score'           => $score->getScore(),
            'recommandation'  => $score->getRecommandation(),
            'dateCalcul'      => $score->getDateCalcul()->format('Y-m-d'),
            'charge7j'        => (float) $score->getCharge7j(),
            'bilanCalorique'  => (float) $score->getBilanCalorique(),
            'heuresSommeilMoy' => (float) $score->getHeuresSommeilMoy(),
        ]);
    }

    /**
     * POST /api/recovery/score : calcule le score du jour, ou de la date envoyée.
     * Un seul score par jour : si on relance le calcul, il remplace celui déjà enregistré.
     */
    #[Route('/score', name: 'api_recovery_score_calculate', methods: ['POST'])]
    public function calculateScore(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $date = isset($data['date']) ? new \DateTime($data['date']) : new \DateTime();

        $score = $this->recoveryService->calculateScore($this->getUser(), $date);

        return $this->json([
            'score'          => $score->getScore(),
            'recommandation' => $score->getRecommandation(),
            'dateCalcul'     => $score->getDateCalcul()->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }

    /** GET /api/recovery/sommeil : les nuits des 14 derniers jours. */
    #[Route('/sommeil', name: 'api_recovery_sommeil_history', methods: ['GET'])]
    public function getSommeilHistory(): JsonResponse
    {
        $entries = $this->recoveryService->getSommeilHistory($this->getUser(), 14);

        return $this->json(array_map(fn ($s) => [
            'id'            => $s->getId(),
            'dateNuit'      => $s->getDateNuit()->format('Y-m-d'),
            'heuresSommeil' => (float) $s->getHeuresSommeil(),
            'qualite'       => $s->getQualiteSommeil(),
        ], $entries));
    }

    /**
     * POST /api/recovery/sommeil : enregistre une nuit (heures + qualité de 1 à 5).
     * Si la nuit existe déjà, le service la met à jour au lieu d'en créer une deuxième.
     */
    #[Route('/sommeil', name: 'api_recovery_sommeil', methods: ['POST'])]
    public function logSommeil(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['heuresSommeil'], $data['qualite'])) {
            return $this->json(['error' => 'heuresSommeil et qualite sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        if ($data['qualite'] < 1 || $data['qualite'] > 5) {
            return $this->json(['error' => 'qualite doit être entre 1 et 5.'], Response::HTTP_BAD_REQUEST);
        }

        $sommeil = $this->recoveryService->logSommeil(
            $this->getUser(),
            isset($data['dateNuit']) ? new \DateTime($data['dateNuit']) : new \DateTime(),
            (float) $data['heuresSommeil'],
            (int)   $data['qualite'],
        );

        return $this->json(['message' => 'Sommeil enregistré.', 'id' => $sommeil->getId()], Response::HTTP_CREATED);
    }
}
