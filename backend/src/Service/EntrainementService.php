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

    /** Crée nbSeries séries identiques et retourne la dernière. */
    public function addSerie(Seance $seance, string $exerciceNom, int $repetitions, float $chargeKg, int $nbSeries = 1): SerieExercice
    {
        $exercice = $this->em->getRepository(Exercice::class)->findOneBy(['nom' => $exerciceNom]);
        if (!$exercice) {
            $exercice = new Exercice();
            $exercice->setNom($exerciceNom);
            $this->em->persist($exercice);
        }

        $nbSeries = max(1, min($nbSeries, 20));
        $derniere = null;

        for ($i = 1; $i <= $nbSeries; $i++) {
            $serie = new SerieExercice();
            $serie->setExercice($exercice);
            $serie->setRepetitions($repetitions);
            $serie->setChargeKg((string) $chargeKg);
            $serie->setNumeroSerie($i);
            $serie->calculerTonnage();

            $seance->addSerie($serie);
            $this->em->persist($serie);
            $derniere = $serie;
        }

        $seance->calculerTonnage();
        $this->em->flush();

        return $derniere;
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

    public function updateSerie(Seance $seance, int $serieId, int $repetitions, float $chargeKg): SerieExercice
    {
        $serie = $this->em->getRepository(SerieExercice::class)->find($serieId);
        if (!$serie || $serie->getSeance()->getId() !== $seance->getId()) {
            throw new \InvalidArgumentException('Série introuvable.');
        }

        $serie->setRepetitions($repetitions);
        $serie->setChargeKg((string) $chargeKg);
        $serie->calculerTonnage();
        $seance->calculerTonnage();
        $this->em->flush();

        return $serie;
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

    /**
     * Crée une copie d'une séance (même exercices / reps / charge) datée d'aujourd'hui.
     */
    public function dupliquerSeance(User $user, int $seanceId): Seance
    {
        $original = $this->getSeance($user, $seanceId);

        $nouvelle = new Seance();
        $nouvelle->setUtilisateur($user);
        $nouvelle->setNom($original->getNom());
        $nouvelle->setDateSeance(new \DateTime());
        $nouvelle->setNotes($original->getNotes());
        $this->em->persist($nouvelle);

        foreach ($original->getSeries() as $serie) {
            $copie = new SerieExercice();
            $copie->setExercice($serie->getExercice());
            $copie->setRepetitions($serie->getRepetitions());
            $copie->setChargeKg($serie->getChargeKg());
            $copie->setNumeroSerie($serie->getNumeroSerie());
            $copie->calculerTonnage();
            $nouvelle->addSerie($copie);
            $this->em->persist($copie);
        }

        $nouvelle->calculerTonnage();
        $this->em->flush();

        return $nouvelle;
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
