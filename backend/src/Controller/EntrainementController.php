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

        return $this->json(array_map(fn ($s) => $this->serializeSummary($s), $seances));
    }

    #[Route('/modeles', name: 'api_seances_modeles', methods: ['GET'])]
    public function getModeles(): JsonResponse
    {
        $modeles = $this->entrainementService->getModeles($this->getUser());

        return $this->json(array_map(fn ($s) => [
            'id'          => $s->getId(),
            'nom'         => $s->getNom(),
            'nbExercices' => $s->getNbExercices(),
            'nbSeries'    => $s->getSeries()->count(),
        ], $modeles));
    }

    #[Route('/en-cours', name: 'api_seances_en_cours', methods: ['GET'])]
    public function getEnCours(): JsonResponse
    {
        $seances = $this->entrainementService->getEnCours($this->getUser());

        return $this->json(array_map(fn ($s) => $this->serializeSummary($s), $seances));
    }

    /** Vue résumée d'une séance réelle (historique ou en cours) pour les listes. */
    private function serializeSummary(\App\Entity\Seance $s): array
    {
        return [
            'id'           => $s->getId(),
            'nom'          => $s->getNom(),
            'dateSeance'   => $s->getDateSeance()->format('Y-m-d'),
            'statut'       => $s->getStatut(),
            'tonnageTotal' => (float) $s->getTonnageTotal(),
            'nbExercices'  => $s->getNbExercices(),
            'nbSeries'     => $s->getSeries()->count(),
        ];
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
            (bool) ($data['modele'] ?? false),
        );

        return $this->json(['message' => 'Séance créée.', 'id' => $seance->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_seances_get', methods: ['GET'], requirements: ['id' => '\d+'])]
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
            'nbExercices'  => $seance->getNbExercices(),
            'notes'        => $seance->getNotes(),
            'series'       => $series,
        ]);
    }

    #[Route('/{id}/series', name: 'api_seances_add_serie', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addSerie(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['exerciceNom', 'repetitions', 'chargeKg'] as $f) {
            if (empty($data[$f])) {
                return $this->json(['error' => "$f est requis."], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $serie  = $this->entrainementService->addSerie(
                $seance,
                trim($data['exerciceNom']),
                (int)   $data['repetitions'],
                (float) $data['chargeKg'],
                (int)   ($data['nbSeries'] ?? 1),
            );

            return $this->json(['message' => 'Série ajoutée.', 'id' => $serie->getId(), 'tonnage' => (float) $serie->getTonnage()], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/series/{serieId}', name: 'api_seances_update_serie', methods: ['PATCH'], requirements: ['id' => '\d+', 'serieId' => '\d+'])]
    public function updateSerie(int $id, int $serieId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['repetitions', 'chargeKg'] as $f) {
            if (!isset($data[$f])) {
                return $this->json(['error' => "$f est requis."], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $serie  = $this->entrainementService->updateSerie(
                $seance,
                $serieId,
                (int)   $data['repetitions'],
                (float) $data['chargeKg'],
            );

            return $this->json([
                'message'  => 'Série mise à jour.',
                'tonnage'  => (float) $serie->getTonnage(),
                'tonnageSeance' => (float) $seance->getTonnageTotal(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{id}/series/{serieId}', name: 'api_seances_delete_serie', methods: ['DELETE'], requirements: ['id' => '\d+', 'serieId' => '\d+'])]
    public function deleteSerie(int $id, int $serieId): JsonResponse
    {
        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $this->entrainementService->removeSerie($seance, $serieId);

            return $this->json(['message' => 'Série supprimée.', 'tonnageSeance' => (float) $seance->getTonnageTotal()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{id}/dupliquer', name: 'api_seances_dupliquer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dupliquer(int $id): JsonResponse
    {
        try {
            $seance = $this->entrainementService->dupliquerSeance($this->getUser(), $id);

            return $this->json([
                'message' => 'Séance dupliquée.',
                'id'      => $seance->getId(),
                'nom'     => $seance->getNom(),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{id}/terminer', name: 'api_seances_terminer', methods: ['PUT'], requirements: ['id' => '\d+'])]
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

    #[Route('/{id}/rouvrir', name: 'api_seances_rouvrir', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function rouvrir(int $id): JsonResponse
    {
        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $this->entrainementService->rouvrirSeance($seance);

            return $this->json(['message' => 'Séance rouverte.', 'statut' => $seance->getStatut()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{id}', name: 'api_seances_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateSeance(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $seance = $this->entrainementService->getSeance($this->getUser(), $id);
            $this->entrainementService->updateSeance(
                $seance,
                isset($data['nom']) ? trim($data['nom']) : null,
                isset($data['date']) ? new \DateTime($data['date']) : null,
            );

            return $this->json(['message' => 'Séance mise à jour.', 'nom' => $seance->getNom()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_seances_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
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
