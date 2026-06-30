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

    #[Route('/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json($this->progressionService->getDashboardSummary($this->getUser()));
    }

    #[Route('/progression/poids', name: 'api_progression_poids', methods: ['GET'])]
    public function statsPoids(Request $request): JsonResponse
    {
        $days = min((int) $request->query->get('days', 90), 365);
        return $this->json($this->progressionService->getStatsPoids($this->getUser(), $days));
    }

    #[Route('/progression/calories', name: 'api_progression_calories', methods: ['GET'])]
    public function statsCalories(Request $request): JsonResponse
    {
        $days = min((int) $request->query->get('days', 30), 365);
        return $this->json($this->progressionService->getStatsCalories($this->getUser(), $days));
    }

    #[Route('/progression/entrainement', name: 'api_progression_entrainement', methods: ['GET'])]
    public function statsEntrainement(Request $request): JsonResponse
    {
        $days = min((int) $request->query->get('days', 30), 365);
        return $this->json($this->progressionService->getStatsEntrainement($this->getUser(), $days));
    }
}
