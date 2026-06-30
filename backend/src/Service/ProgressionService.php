<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\JournalAlimentaireRepository;
use App\Repository\ObjectifRepository;
use App\Repository\PoidsHistoriqueRepository;
use App\Repository\SeanceRepository;

class ProgressionService
{
    public function __construct(
        private readonly PoidsHistoriqueRepository    $poidsRepo,
        private readonly JournalAlimentaireRepository $journalRepo,
        private readonly SeanceRepository             $seanceRepo,
        private readonly ObjectifRepository           $objectifRepo,
    ) {}

    /**
     * Retourne l'historique de poids sur les N derniers jours.
     * Le poids initial saisi dans le profil sert de point de départ du graphique.
     */
    public function getStatsPoids(User $user, int $days = 90): array
    {
        $entries = $this->poidsRepo->findByUser($user, $days);

        $data = array_map(fn ($e) => [
            'date'    => $e->getDatePesee()->format('Y-m-d'),
            'poidsKg' => (float) $e->getPoidsKg(),
        ], $entries);

        // On ajoute le poids initial du profil comme premier point (si renseigné),
        // pour que le graphique parte de la valeur de départ même sans pesée enregistrée
        $poidsInitial = $user->getPoidsInitial();
        if ($poidsInitial !== null) {
            array_unshift($data, [
                'date'    => $user->getCreatedAt()->format('Y-m-d'),
                'poidsKg' => (float) $poidsInitial,
            ]);
        }

        return $data;
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

        $journal        = $this->journalRepo->findByUserAndDate($user, $today);
        $seances        = $this->seanceRepo->findByUser($user, 1);
        $objectifActif  = $this->objectifRepo->findActiveForUser($user);

        return [
            'date'               => $today->format('Y-m-d'),
            'caloriesObjectif'   => $objectif,
            'caloriesConsommees' => $journal ? (float) $journal->getCaloriesTotales() : 0,
            'proteines'          => $journal ? (float) $journal->getProteinesTotales() : 0,
            'glucides'           => $journal ? (float) $journal->getGlucidesTotaux() : 0,
            'lipides'            => $journal ? (float) $journal->getLipidesTotaux() : 0,
            'fibres'             => $journal ? (float) $journal->getFibresTotales() : 0,
            // Cibles macros de l'objectif actif (null si non defini : le front met une valeur par defaut)
            'objectifsMacros'    => [
                'proteines' => $objectifActif?->getProteinesG(),
                'glucides'  => $objectifActif?->getGlucidesG(),
                'lipides'   => $objectifActif?->getLipidesG(),
            ],
            // Nombre de jours consecutifs avec un journal alimentaire (serie de regularite)
            'joursConsecutifs'   => $this->computeStreak($user),
            'derniereSeance'     => $seances ? [
                'nom'     => $seances[0]->getNom(),
                'date'    => $seances[0]->getDateSeance()->format('Y-m-d'),
                'tonnage' => (float) $seances[0]->getTonnageTotal(),
            ] : null,
        ];
    }

    /**
     * Calcule le nombre de jours consecutifs (en remontant depuis aujourd'hui)
     * ou l'utilisateur a un journal alimentaire. Donnee derivee, aucun stockage.
     */
    private function computeStreak(User $user): int
    {
        $from = (new \DateTime())->modify('-60 days');
        $to   = new \DateTime();
        $journals = $this->journalRepo->findByUserBetweenDates($user, $from, $to);

        // Ensemble des dates ou un journal existe
        $jours = [];
        foreach ($journals as $j) {
            $jours[$j->getDateJournal()->format('Y-m-d')] = true;
        }

        $jour = new \DateTime();
        // Si rien aujourd'hui, on tolere de demarrer la serie a hier
        if (!isset($jours[$jour->format('Y-m-d')])) {
            $jour->modify('-1 day');
            if (!isset($jours[$jour->format('Y-m-d')])) {
                return 0;
            }
        }

        $streak = 0;
        while (isset($jours[$jour->format('Y-m-d')])) {
            $streak++;
            $jour->modify('-1 day');
        }

        return $streak;
    }
}
