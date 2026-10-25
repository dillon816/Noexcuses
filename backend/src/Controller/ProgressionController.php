<?php

namespace App\Controller;

use App\Service\ProgressionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ProgressionController extends AbstractController
{
    public function __construct(private readonly ProgressionService $progressionService) {}

    /** GET /api/dashboard : le résumé du jour (calories, macros, dernière séance, jours consécutifs). */
    #[Route('/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json($this->progressionService->getDashboardSummary($this->getUser()));
    }

    /**
     * GET /api/progression/poids?days=... : la courbe de poids, 90 jours par défaut.
     * Le min() plafonne la période à un an pour éviter une requête trop lourde.
     */
    #[Route('/progression/poids', name: 'api_progression_poids', methods: ['GET'])]
    public function statsPoids(Request $request): JsonResponse
    {
        $days = min((int) $request->query->get('days', 90), 365);
        return $this->json($this->progressionService->getStatsPoids($this->getUser(), $days));
    }

    /** GET /api/progression/calories?days=... : calories et macros jour par jour, 30 jours par défaut. */
    #[Route('/progression/calories', name: 'api_progression_calories', methods: ['GET'])]
    public function statsCalories(Request $request): JsonResponse
    {
        $days = min((int) $request->query->get('days', 30), 365);
        return $this->json($this->progressionService->getStatsCalories($this->getUser(), $days));
    }

    /** GET /api/progression/entrainement?weeks=... : le tonnage par semaine, 8 semaines par défaut. */
    #[Route('/progression/entrainement', name: 'api_progression_entrainement', methods: ['GET'])]
    public function statsEntrainement(Request $request): JsonResponse
    {
        $weeks = min((int) $request->query->get('weeks', 8), 52);
        return $this->json($this->progressionService->getStatsEntrainement($this->getUser(), $weeks));
    }
}
