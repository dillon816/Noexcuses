<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\JournalAlimentaireRepository;
use App\Repository\PoidsHistoriqueRepository;
use App\Repository\SeanceRepository;

class ProgressionService
{
    public function __construct(
        private readonly PoidsHistoriqueRepository    $poidsRepo,
        private readonly JournalAlimentaireRepository $journalRepo,
        private readonly SeanceRepository             $seanceRepo,
    ) {}

    /**
     * Retourne l'historique de poids sur les N derniers jours.
     */
    public function getStatsPoids(User $user, int $days = 90): array
    {
        $entries = $this->poidsRepo->findByUser($user, $days);

        return array_map(fn ($e) => [
            'date'    => $e->getDatePesee()->format('Y-m-d'),
            'poidsKg' => (float) $e->getPoidsKg(),
        ], $entries);
    }

    /**
     * Retourne les stats caloriques et macros jour par jour sur la période.
     * Inclut le bilan (calories consommées - objectif) pour repérer les jours en déficit ou en surplus.
     */
    public function getStatsCalories(User $user, int $days = 30): array
    {
        $from = (new \DateTime())->modify("-{$days} days");
        $to   = new \DateTime();

        $journals = $this->journalRepo->findByUserBetweenDates($user, $from, $to);
        $objectif = (float) ($user->getCaloriesObjectif() ?? 2000);

        return array_map(fn ($j) => [
            'date'      => $j->getDateJournal()->format('Y-m-d'),
            'calories'  => (float) $j->getCaloriesTotales(),
            'proteines' => (float) $j->getProteinesTotales(),
            'glucides'  => (float) $j->getGlucidesTotaux(),
            'lipides'   => (float) $j->getLipidesTotaux(),
            'objectif'  => $objectif,
            'bilan'     => round((float) $j->getCaloriesTotales() - $objectif, 2),
        ], $journals);
    }

    /**
     * Retourne les séances d'entraînement sur la période avec tonnage et nombre de séries.
     * Sert à tracer l'évolution de la charge d'entraînement .
     */
    public function getStatsEntrainement(User $user, int $days = 30): array
    {
        $from = (new \DateTime())->modify("-{$days} days");
        $to   = new \DateTime();

        $seances = $this->seanceRepo->findByUserBetweenDates($user, $from, $to);

        return array_map(fn ($s) => [
            'date'         => $s->getDateSeance()->format('Y-m-d'),
            'nom'          => $s->getNom(),
            'tonnageTotal' => (float) $s->getTonnageTotal(),
            'statut'       => $s->getStatut(),
            'nbSeries'     => $s->getSeries()->count(),
        ], $seances);
    }

    /**
     * Rassemble les données du jour pour le dashboard (calories, macros, dernière séance).
     * Si aucun journal ou aucune séance n'existe pour aujourd'hui, les valeurs sont à 0/null.
     */
    public function getDashboardSummary(User $user): array
    {
        $today    = new \DateTime();
        $objectif = (float) ($user->getCaloriesObjectif() ?? 2000);

        $journal  = $this->journalRepo->findByUserAndDate($user, $today);
        $seances  = $this->seanceRepo->findByUser($user, 1);

        return [
            'date'               => $today->format('Y-m-d'),
            'caloriesObjectif'   => $objectif,
            'caloriesConsommees' => $journal ? (float) $journal->getCaloriesTotales() : 0,
            'proteines'          => $journal ? (float) $journal->getProteinesTotales() : 0,
            'glucides'           => $journal ? (float) $journal->getGlucidesTotaux() : 0,
            'lipides'            => $journal ? (float) $journal->getLipidesTotaux() : 0,
            'fibres'             => $journal ? (float) $journal->getFibresTotales() : 0,
            'derniereSeance'     => $seances ? [
                'nom'     => $seances[0]->getNom(),
                'date'    => $seances[0]->getDateSeance()->format('Y-m-d'),
                'tonnage' => (float) $seances[0]->getTonnageTotal(),
            ] : null,
        ];
    }
}
