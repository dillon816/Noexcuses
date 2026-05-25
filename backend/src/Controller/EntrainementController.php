<?php

namespace App\Controller;

use App\Service\EntrainementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/seances')]
class EntrainementController extends AbstractController
{
    public function __construct(private readonly EntrainementService $entrainementService) {}

    #[Route('', name: 'api_seances_list', methods: ['GET'])]
    public function getSeances(Request $request): JsonResponse
    {
        $limit   = min((int) $request->query->get('limit', 20), 100);
        $seances = $this->entrainementService->getSeances($this->getUser(), $limit);

        return $this->json(array_map(fn ($s) => [
            'id'           => $s->getId(),
            'nom'          => $s->getNom(),
            'dateSeance'   => $s->getDateSeance()->format('Y-m-d'),
            'statut'       => $s->getStatut(),
            'tonnageTotal' => (float) $s->getTonnageTotal(),
            'nbSeries'     => $s->getSeries()->count(),
        ], $seances));
    }

    #[Route('', name: 'api_seances_create', methods: ['POST'])]
    public function createSeance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['nom'])) {
            return $this->json(['error' => 'Le nom de la séance est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $seance = $this->entrainementService->createSeance(
            $this->getUser(),
            $data['nom'],
            isset($data['date']) ? new \DateTime($data['date']) : new \DateTime(),
            $data['notes'] ?? null,
        );

        return $this->json(['message' => 'Séance créée.', 'id' => $seance->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_seances_get', methods: ['GET'])]
    public function getSeance(int $id): JsonResponse
    {
        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Séance introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $series = array_map(fn ($s) => [
            'id'          => $s->getId(),
            'exercice'    => $s->getExercice()->getNom(),
            'exerciceId'  => $s->getExercice()->getId(),
            'repetitions' => $s->getRepetitions(),
            'chargeKg'    => (float) $s->getChargeKg(),
            'tonnage'     => (float) $s->getTonnage(),
            'numeroSerie' => $s->getNumeroSerie(),
        ], $seance->getSeries()->toArray());

        return $this->json([
            'id'           => $seance->getId(),
            'nom'          => $seance->getNom(),
            'dateSeance'   => $seance->getDateSeance()->format('Y-m-d'),
            'statut'       => $seance->getStatut(),
            'tonnageTotal' => (float) $seance->getTonnageTotal(),
            'notes'        => $seance->getNotes(),
            'series'       => $series,
        ]);
    }

    #[Route('/{id}/series', name: 'api_seances_add_serie', methods: ['POST'])]
    public function addSerie(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['exerciceId', 'repetitions', 'chargeKg'] as $f) {
            if (!isset($data[$f])) {
                return $this->json(['error' => "$f est requis."], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $serie  = $this->entrainementService->addSerie(
                $seance,
                (int)   $data['exerciceId'],
                (int)   $data['repetitions'],
                (float) $data['chargeKg'],
                (int)   ($data['numeroSerie'] ?? 1),
            );

            return $this->json(['message' => 'Série ajoutée.', 'id' => $serie->getId(), 'tonnage' => (float) $serie->getTonnage()], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/terminer', name: 'api_seances_terminer', methods: ['PUT'])]
    public function terminer(int $id): JsonResponse
    {
        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $this->entrainementService->terminerSeance($seance);

            return $this->json(['message' => 'Séance terminée.', 'tonnageTotal' => (float) $seance->getTonnageTotal()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{id}', name: 'api_seances_delete', methods: ['DELETE'])]
    public function deleteSeance(int $id): JsonResponse
    {
        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $this->entrainementService->deleteSeance($seance);

            return $this->json(['message' => 'Séance supprimée.']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
