<?php

namespace App\Service;

use App\Entity\Exercice;
use App\Entity\Seance;
use App\Entity\SerieExercice;
use App\Entity\User;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;

class EntrainementService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SeanceRepository       $seanceRepo,
    ) {}

    public function createSeance(User $user, string $nom, \DateTimeInterface $date, ?string $notes = null): Seance
    {
        $seance = new Seance();
        $seance->setUtilisateur($user);
        $seance->setNom($nom);
        $seance->setDateSeance($date);
        $seance->setNotes($notes);

        $this->em->persist($seance);
        $this->em->flush();

        return $seance;
    }

    public function addSerie(Seance $seance, int $exerciceId, int $repetitions, float $chargeKg, int $numeroSerie = 1): SerieExercice
    {
        $exercice = $this->em->getRepository(Exercice::class)->find($exerciceId);
        if (!$exercice) {
            throw new \InvalidArgumentException('Exercice introuvable.');
        }

        $serie = new SerieExercice();
        $serie->setExercice($exercice);
        $serie->setRepetitions($repetitions);
        $serie->setChargeKg((string) $chargeKg);
        $serie->setNumeroSerie($numeroSerie);
        $serie->calculerTonnage();

        $seance->addSerie($serie);
        $seance->calculerTonnage();

        $this->em->persist($serie);
        $this->em->flush();

        return $serie;
    }

    public function terminerSeance(Seance $seance): Seance
    {
        $seance->setStatut(Seance::STATUT_TERMINEE);
        $seance->calculerTonnage();
        $this->em->flush();

        return $seance;
    }

    public function archiverSeance(Seance $seance): void
    {
        $seance->archiver();
        $this->em->flush();
    }

    public function deleteSeance(Seance $seance): void
    {
        $this->em->remove($seance);
        $this->em->flush();
    }

    public function removeSerie(Seance $seance, int $serieId): void
    {
        $serie = $this->em->getRepository(SerieExercice::class)->find($serieId);
        if (!$serie || $serie->getSeance()->getId() !== $seance->getId()) {
            throw new \InvalidArgumentException('Série introuvable.');
        }

        $seance->removeSerie($serie);
        $this->em->remove($serie);
        $seance->calculerTonnage();
        $this->em->flush();
    }

    /** @return Seance[] */
    public function getSeances(User $user, int $limit = 20): array
    {
        return $this->seanceRepo->findByUser($user, $limit);
    }

    public function getSeance(User $user, int $id): Seance
    {
        $seance = $this->seanceRepo->find($id);
        if (!$seance || $seance->getUtilisateur()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Séance introuvable.');
        }

        return $seance;
    }
}
