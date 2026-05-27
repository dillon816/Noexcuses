<?php

namespace App\Service;

use App\Entity\RecoveryScore;
use App\Entity\Sommeil;
use App\Entity\User;
use App\Repository\JournalAlimentaireRepository;
use App\Repository\RecoveryScoreRepository;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;

class RecoveryService
{
    public function __construct(
        private readonly EntityManagerInterface       $em,
        private readonly RecoveryScoreRepository      $recoveryRepo,
        private readonly SeanceRepository             $seanceRepo,
        private readonly JournalAlimentaireRepository $journalRepo,
    ) {}

    /**
     * Calcule et sauvegarde le score de récupération pour une date donnée.
     * Si un score existe déjà pour cette date, il est remplacé (recalcul à la demande).
     * Les trois facteurs utilisés : charge 7 jours, bilan calorique du jour, moyenne de sommeil.
     */
    public function calculateScore(User $user, ?\DateTimeInterface $date = null): RecoveryScore
    {
        $date ??= new \DateTime();

        // Si un score existe déjà pour aujourd'hui, on le supprime avant de recalculer
        $existing = $this->recoveryRepo->findByUserAndDate($user, $date);
        if ($existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $charge7j         = $this->seanceRepo->getTonnageLast7Days($user);
        $bilanCalorique   = $this->getBilanCalorique($user, $date);
        $heuresSommeilMoy = $this->getMoyenneSommeil($user);

        $score = $this->computeScore($charge7j, $bilanCalorique, $heuresSommeilMoy, $user);

        $recovery = new RecoveryScore();
        $recovery->setUtilisateur($user);
        $recovery->setDateCalcul($date);
        $recovery->setScore($score);
        $recovery->setRecommandation($this->getRecommandation($score));
        $recovery->setCharge7j((string) $charge7j);
        $recovery->setBilanCalorique((string) $bilanCalorique);
        $recovery->setHeuresSommeilMoy((string) $heuresSommeilMoy);

        $this->em->persist($recovery);
        $this->em->flush();

        return $recovery;
    }

    /**
     * Algorithme scoring (0–100) :
     * - Sommeil : 40 pts max (8h = max, < 5h = 0)
     * - Bilan calorique : 30 pts max (proche de l'objectif = max, déficit > 500 kcal = pénalité)
     * - Charge 7j : 30 pts max (charge modérée = max, surcharge = pénalité)
     */
    private function computeScore(float $charge7j, float $bilanCalorique, float $heuresSommeilMoy, User $user): int
    {
        // Sommeil (40 pts)
        $sommeilScore = match(true) {
            $heuresSommeilMoy >= 8  => 40,
            $heuresSommeilMoy >= 7  => 34,
            $heuresSommeilMoy >= 6  => 24,
            $heuresSommeilMoy >= 5  => 12,
            default                 => 0,
        };

        // Bilan calorique (30 pts) — on favorise les bilans équilibrés
        $deficit = abs($bilanCalorique);
        $calorieScore = match(true) {
            $deficit <= 200         => 30,
            $deficit <= 400         => 22,
            $deficit <= 600         => 14,
            $deficit <= 1000        => 6,
            default                 => 0,
        };

        // Charge 7j (30 pts) — charge modérée recommandée <= 5000 kg cumulés = plage idéale
        $chargeScore = match(true) {
            $charge7j === 0.0       => 20,
            $charge7j <= 5000       => 30,
            $charge7j <= 10000      => 22,
            $charge7j <= 20000      => 12,
            default                 => 4,
        };

        return $sommeilScore + $calorieScore + $chargeScore;
    }

    /** Traduit le score numérique en recommandation textuelle (normale, legere, repos). */
    private function getRecommandation(int $score): string
    {
        return match(true) {
            $score >= 70 => RecoveryScore::RECOMMANDATION_NORMALE,
            $score >= 40 => RecoveryScore::RECOMMANDATION_LEGERE,
            default      => RecoveryScore::RECOMMANDATION_REPOS,
        };
    }

    /**
     * Calcule la différence entre les calories consommées et l'objectif du jour.
     * Retourne 0 si l'utilisateur n'a rien saisi dans son journal.
     */
    private function getBilanCalorique(User $user, \DateTimeInterface $date): float
    {
        $journal = $this->journalRepo->findByUserAndDate($user, $date);
        if (!$journal) {
            return 0.0;
        }
        $objectif = (float) ($user->getCaloriesObjectif() ?? 2000);
        return (float) $journal->getCaloriesTotales() - $objectif;
    }

    /**
     * Calcule la moyenne des heures de sommeil sur les 7 derniers jours via une requête DQL AVG.
     * Si aucune donnée de sommeil n'est disponible, on part sur 7h par défaut.
     */
    private function getMoyenneSommeil(User $user): float
    {
        $from = (new \DateTime())->modify('-7 days');
        $entries = $this->em->getRepository(Sommeil::class)->createQueryBuilder('s')
            ->select('AVG(s.heuresSommeil) as avg')
            ->where('s.utilisateur = :user')
            ->andWhere('s.dateNuit >= :from')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($entries ?? 7.0);
    }

    /**
     * Enregistre (ou met à jour) une entrée de sommeil pour une nuit donnée.
     * Pattern upsert : si une entrée existe déjà pour cette date, on la modifie plutôt que d'en créer une nouvelle.
     */
    public function logSommeil(User $user, \DateTimeInterface $dateNuit, float $heures, int $qualite): Sommeil
    {
        $existing = $this->em->getRepository(Sommeil::class)->findOneBy([
            'utilisateur' => $user,
            'dateNuit'    => $dateNuit,
        ]);

        // Si une entrée existe déjà pour cette nuit, on la réutilise
        $sommeil = $existing ?? new Sommeil();
        $sommeil->setUtilisateur($user);
        $sommeil->setDateNuit($dateNuit);
        $sommeil->setHeuresSommeil((string) $heures);
        $sommeil->setQualiteSommeil($qualite);

        $this->em->persist($sommeil);
        $this->em->flush();

        return $sommeil;
    }

    /** Retourne le dernier score de récupération calculé pour l'utilisateur. */
    public function getLatestScore(User $user): ?RecoveryScore
    {
        return $this->recoveryRepo->findLatestForUser($user);
    }
}
