<?php

namespace App\Controller;

use App\Service\NutritionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/nutrition')]
class NutritionController extends AbstractController
{
    public function __construct(private readonly NutritionService $nutritionService) {}

    #[Route('/aliments', name: 'api_nutrition_aliments', methods: ['GET'])]
    public function searchAliment(Request $request): JsonResponse
    {
        $term = trim($request->query->get('search', ''));
        if (strlen($term) < 2) {
            return $this->json(['error' => 'Minimum 2 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        $aliments = $this->nutritionService->searchAliment($term);

        return $this->json(array_map(fn ($a) => [
            'id'           => $a->getId(),
            'nom'          => $a->getNom(),
            'calories100g' => $a->getCalories100g(),
            'proteines100g' => $a->getProteines100g(),
            'glucides100g'  => $a->getGlucides100g(),
            'lipides100g'   => $a->getLipides100g(),
        ], $aliments));
    }

    #[Route('/journal', name: 'api_nutrition_journal', methods: ['GET'])]
    public function getJournal(Request $request): JsonResponse
    {
        $date = $request->query->get('date')
            ? new \DateTime($request->query->get('date'))
            : new \DateTime();

        $journal = $this->nutritionService->getJournal($this->getUser(), $date);

        if (!$journal) {
            return $this->json(['date' => $date->format('Y-m-d'), 'lignes' => [], 'totaux' => null]);
        }

        $lignes = array_map(fn ($l) => [
            'id'        => $l->getId(),
            'aliment'   => $l->getAliment()->getNom(),
            'quantiteG' => (float) $l->getQuantiteG(),
            'typeRepas' => $l->getTypeRepas(),
            'calories'  => (float) $l->getCalories(),
            'proteines' => (float) $l->getProteines(),
            'glucides'  => (float) $l->getGlucides(),
            'lipides'   => (float) $l->getLipides(),
        ], $journal->getLignes()->toArray());

        return $this->json([
            'date'   => $journal->getDateJournal()->format('Y-m-d'),
            'totaux' => [
                'calories'  => (float) $journal->getCaloriesTotales(),
                'proteines' => (float) $journal->getProteinesTotales(),
                'glucides'  => (float) $journal->getGlucidesTotaux(),
                'lipides'   => (float) $journal->getLipidesTotaux(),
                'fibres'    => (float) $journal->getFibresTotales(),
            ],
            'lignes' => $lignes,
        ]);
    }

    #[Route('/repas', name: 'api_nutrition_add_repas', methods: ['POST'])]
    public function addRepas(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['alimentId', 'quantiteG', 'typeRepas'] as $f) {
            if (empty($data[$f])) {
                return $this->json(['error' => "$f est requis."], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $ligne = $this->nutritionService->addRepas(
                $this->getUser(),
                (int)   $data['alimentId'],
                (float) $data['quantiteG'],
                $data['typeRepas'],
                isset($data['date']) ? new \DateTime($data['date']) : null,
            );

            return $this->json(['message' => 'Repas ajouté.', 'id' => $ligne->getId()], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/repas/{id}', name: 'api_nutrition_delete_repas', methods: ['DELETE'])]
    public function removeRepas(int $id): JsonResponse
    {
        try {
            $this->nutritionService->removeRepas($this->getUser(), $id);
            return $this->json(['message' => 'Repas supprimé.']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
