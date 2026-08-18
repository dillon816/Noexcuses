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

    /**
     * Initialise une nouvelle séance pour l'utilisateur.
     * Si $modele vaut true, la séance devient un gabarit réutilisable (statut "modele").
     * La date reste obligatoire côté base : pour un modèle on stocke la date du jour
     * comme date technique, jamais affichée comme date d'entraînement.
     */
    public function createSeance(User $user, string $nom, \DateTimeInterface $date, ?string $notes = null, bool $modele = false): Seance
    {
        $seance = new Seance();
        $seance->setUtilisateur($user);
        $seance->setNom($nom);
        $seance->setDateSeance($date);
        $seance->setNotes($notes);
        if ($modele) {
            $seance->setStatut(Seance::STATUT_MODELE);
        }

        $this->em->persist($seance);
        $this->em->flush();

        return $seance;
    }

    /** Retourne les séances-modèles (gabarits réutilisables) de l'utilisateur. @return Seance[] */
    public function getModeles(User $user): array
    {
        return $this->seanceRepo->findModeles($user);
    }

    /** Retourne les séances actuellement en cours (démarrées, pas encore terminées). @return Seance[] */
    public function getEnCours(User $user): array
    {
        return $this->seanceRepo->findEnCours($user);
    }

    /** Rouvre une séance terminée pour la modifier à nouveau (repasse en "en cours"). */
    public function rouvrirSeance(Seance $seance): Seance
    {
        $seance->setStatut(Seance::STATUT_EN_COURS);
        $this->em->flush();

        return $seance;
    }

    /**
     * Renomme et/ou redate une séance. Les paramètres nuls sont ignorés.
     * Un modèle n'affiche pas sa date : on autorise malgré tout la mise à jour du nom.
     */
    public function updateSeance(Seance $seance, ?string $nom, ?\DateTimeInterface $date): Seance
    {
        if ($nom !== null && $nom !== '') {
            $seance->setNom($nom);
        }
        if ($date !== null && !$seance->isModele()) {
            $seance->setDateSeance($date);
        }
        $this->em->flush();

        return $seance;
    }

    /**
     * Ajoute une ou plusieurs séries identiques à la séance.
     * Si l'exercice n'existe pas encore en BDD, il est créé à la volée.
     * Le tonnage total de la séance est recalculé après chaque ajout.
     */
    public function addSerie(Seance $seance, string $exerciceNom, int $repetitions, float $chargeKg, int $nbSeries = 1): SerieExercice
    {
        // Récupère l'exercice existant ou le crée si c'est la première fois
        $exercice = $this->em->getRepository(Exercice::class)->findOneBy(['nom' => $exerciceNom]);
        if (!$exercice) {
            $exercice = new Exercice();
            $exercice->setNom($exerciceNom);
            $this->em->persist($exercice);
        }

        // Sécurité : entre 1 et 20 séries maximum
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

    /** Marque la séance comme terminée et recalcule le tonnage final. */
    public function terminerSeance(Seance $seance): Seance
    {
        $seance->setStatut(Seance::STATUT_TERMINEE);
        $seance->calculerTonnage();
        $this->em->flush();

        return $seance;
    }

    /** Passe la séance en statut archivée (elle reste visible dans l'historique). */
    public function archiverSeance(Seance $seance): void
    {
        $seance->archiver();
        $this->em->flush();
    }

    /** Supprime définitivement une séance et toutes ses séries (cascade Doctrine). */
    public function deleteSeance(Seance $seance): void
    {
        $this->em->remove($seance);
        $this->em->flush();
    }

    /**
     * Modifie les reps et la charge d'une série existante.
     * On vérifie que la série appartient bien à la séance avant de modifier.
     * Le tonnage est recalculé au niveau série et au niveau séance.
     */
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

    /**
     * Supprime une série et met à jour le tonnage total de la séance.
     * Vérifie d'abord que la série fait bien partie de la séance (sécurité).
     */
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
     * Duplique une séance existante avec tous ses exercices, reps et charges.
     * La copie est datée d'aujourd'hui, prête à être réutilisée pour une nouvelle session.
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

    /** Retourne les dernières séances de l'utilisateur, triées par date décroissante. @return Seance[] */
    public function getSeances(User $user, int $limit = 20): array
    {
        return $this->seanceRepo->findByUser($user, $limit);
    }

    /**
     * Récupère une séance par son ID en vérifiant qu'elle appartient à l'utilisateur.
     * Lève une exception si la séance n'existe pas ou appartient à quelqu'un d'autre.
     */
    public function getSeance(User $user, int $id): Seance
    {
        $seance = $this->seanceRepo->find($id);
        if (!$seance || $seance->getUtilisateur()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Séance introuvable.');
        }

        return $seance;
    }
}
