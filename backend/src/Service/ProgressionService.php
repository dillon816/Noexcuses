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

    public function getStatsPoids(User $user, int $days = 90): array
    {
        $entries = $this->poidsRepo->findByUser($user, $days);

        return array_map(fn ($e) => [
            'date'    => $e->getDatePesee()->format('Y-m-d'),
            'poidsKg' => (float) $e->getPoidsKg(),
        ], $entries);
    }

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
                'nom'    => $seances[0]->getNom(),
                'date'   => $seances[0]->getDateSeance()->format('Y-m-d'),
                'tonnage' => (float) $seances[0]->getTonnageTotal(),
            ] : null,
        ];
    }
}
