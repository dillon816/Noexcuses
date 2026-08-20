<?php

namespace App\Service;

use App\Entity\JournalAlimentaire;
use App\Entity\Objectif;
use App\Entity\PoidsHistorique;
use App\Entity\RecoveryScore;
use App\Entity\Seance;
use App\Entity\SerieExercice;
use App\Entity\Sommeil;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Rassemble et exporte les données personnelles d'un utilisateur (RGPD / portabilité).
 * Lecture seule : aucune écriture en base. Ne contient jamais de donnée sensible
 * (mot de passe / hash, rôles, jetons, secrets). Les séances "modele" et "en_cours"
 * sont exclues : seul l'historique réellement effectué (statut "terminee") est exporté.
 */
class DataExportService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** Structure complète des données de l'utilisateur, réutilisée par le JSON et l'Excel. */
    public function collect(User $user): array
    {
        return [
            'export' => [
                'genere_le'   => (new \DateTime())->format('c'),
                'utilisateur' => $user->getEmail(),
            ],
            'profil'        => $this->profil($user),
            'objectifs'     => $this->objectifs($user),
            'poids'         => $this->poids($user),
            'nutrition'     => $this->nutrition($user),
            'entrainements' => $this->entrainements($user),
            'sommeil'       => $this->sommeil($user),
            'recovery'      => $this->recovery($user),
        ];
    }

    private function profil(User $u): array
    {
        return [
            'email'             => $u->getEmail(),
            'prenom'            => $u->getPrenom(),
            'nom'               => $u->getNom(),
            'date_naissance'    => $u->getDateNaissance()?->format('Y-m-d'),
            'sexe'              => $u->getSexe(),
            'taille_cm'         => $u->getTaille(),
            'poids_initial_kg'  => $u->getPoidsInitial() !== null ? (float) $u->getPoidsInitial() : null,
            'calories_objectif' => $u->getCaloriesObjectif(),
            'niveau_activite'   => $u->getNiveauActivite(),
            'compte_cree_le'    => $u->getCreatedAt()->format('Y-m-d'),
        ];
    }

    private function objectifs(User $u): array
    {
        $rows = $this->em->getRepository(Objectif::class)->findBy(['utilisateur' => $u], ['createdAt' => 'ASC']);

        return array_map(fn (Objectif $o) => [
            'type'          => $o->getType(),
            'calories_jour' => $o->getCaloriesJour(),
            'proteines_g'   => $o->getProteinesG(),
            'glucides_g'    => $o->getGlucidesG(),
            'lipides_g'     => $o->getLipidesG(),
            'actif'         => $o->isActif(),
            'cree_le'       => $o->getCreatedAt()->format('Y-m-d'),
        ], $rows);
    }

    private function poids(User $u): array
    {
        $rows = $this->em->getRepository(PoidsHistorique::class)->findBy(['utilisateur' => $u], ['datePesee' => 'ASC']);

        return array_map(fn (PoidsHistorique $p) => [
            'date'     => $p->getDatePesee()->format('Y-m-d'),
            'poids_kg' => (float) $p->getPoidsKg(),
            'notes'    => $p->getNotes(),
        ], $rows);
    }

    private function nutrition(User $u): array
    {
        $rows = $this->em->getRepository(JournalAlimentaire::class)->findBy(['utilisateur' => $u], ['dateJournal' => 'ASC']);

        return array_map(fn (JournalAlimentaire $j) => [
            'date'      => $j->getDateJournal()->format('Y-m-d'),
            'calories'  => (float) $j->getCaloriesTotales(),
            'proteines' => (float) $j->getProteinesTotales(),
            'glucides'  => (float) $j->getGlucidesTotaux(),
            'lipides'   => (float) $j->getLipidesTotaux(),
            'fibres'    => (float) $j->getFibresTotales(),
        ], $rows);
    }

    private function entrainements(User $u): array
    {
        // Uniquement les séances réellement effectuées : ni modèle, ni en cours
        $seances = $this->em->getRepository(Seance::class)->findBy(
            ['utilisateur' => $u, 'statut' => Seance::STATUT_TERMINEE],
            ['dateSeance' => 'ASC'],
        );

        return array_map(fn (Seance $s) => [
            'date'          => $s->getDateSeance()->format('Y-m-d'),
            'nom'           => $s->getNom(),
            'tonnage_total' => (float) $s->getTonnageTotal(),
            'series'        => array_map(fn (SerieExercice $se) => [
                'exercice'     => $se->getExercice()->getNom(),
                'numero_serie' => $se->getNumeroSerie(),
                'repetitions'  => $se->getRepetitions(),
                'charge_kg'    => (float) $se->getChargeKg(),
                'tonnage'      => (float) $se->getTonnage(),
            ], $s->getSeries()->toArray()),
        ], $seances);
    }

    private function sommeil(User $u): array
    {
        $rows = $this->em->getRepository(Sommeil::class)->findBy(['utilisateur' => $u], ['dateNuit' => 'ASC']);

        return array_map(fn (Sommeil $s) => [
            'date'    => $s->getDateNuit()->format('Y-m-d'),
            'heures'  => (float) $s->getHeuresSommeil(),
            'qualite' => $s->getQualiteSommeil(),
        ], $rows);
    }

    private function recovery(User $u): array
    {
        $rows = $this->em->getRepository(RecoveryScore::class)->findBy(['utilisateur' => $u], ['dateCalcul' => 'ASC']);

        return array_map(fn (RecoveryScore $r) => [
            'date'           => $r->getDateCalcul()->format('Y-m-d'),
            'score'          => $r->getScore(),
            'recommandation' => $r->getRecommandation(),
        ], $rows);
    }

    /**
     * Génère un classeur Excel (.xlsx) multi-feuilles à partir des données collectées.
     * Retourne le chemin d'un fichier temporaire (supprimé après l'envoi côté contrôleur).
     */
    public function toXlsxFile(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nx_export_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        // Feuille 1 : Profil (présentation clé / valeur, plus lisible pour un enregistrement unique)
        $writer->getCurrentSheet()->setName('Profil');
        $writer->addRow(Row::fromValues(['Champ', 'Valeur']));
        foreach ($data['profil'] as $key => $value) {
            $writer->addRow(Row::fromValues([$this->humanLabel($key), $this->scalar($value)]));
        }

        $this->addSheet($writer, 'Objectifs',
            ['Type', 'Calories/jour', 'Proteines (g)', 'Glucides (g)', 'Lipides (g)', 'Actif', 'Cree le'],
            array_map(fn ($o) => [
                $o['type'], $o['calories_jour'], $o['proteines_g'],
                $o['glucides_g'], $o['lipides_g'], $o['actif'] ? 'oui' : 'non', $o['cree_le'],
            ], $data['objectifs']));

        $this->addSheet($writer, 'Poids',
            ['Date', 'Poids (kg)'],
            array_map(fn ($p) => [$p['date'], $p['poids_kg']], $data['poids']));

        $this->addSheet($writer, 'Nutrition',
            ['Date', 'Calories', 'Proteines (g)', 'Glucides (g)', 'Lipides (g)'],
            array_map(fn ($n) => [$n['date'], $n['calories'], $n['proteines'], $n['glucides'], $n['lipides']], $data['nutrition']));

        // Entrainements : une ligne par série
        $entRows = [];
        foreach ($data['entrainements'] as $s) {
            foreach ($s['series'] as $se) {
                $entRows[] = [$s['date'], $s['nom'], $se['exercice'], $se['numero_serie'], $se['repetitions'], $se['charge_kg'], $se['tonnage']];
            }
        }
        $this->addSheet($writer, 'Entrainements',
            ['Date', 'Seance', 'Exercice', 'N serie', 'Repetitions', 'Charge (kg)', 'Tonnage (kg)'],
            $entRows);

        $this->addSheet($writer, 'Sommeil',
            ['Date', 'Heures', 'Qualite (1-5)'],
            array_map(fn ($s) => [$s['date'], $s['heures'], $s['qualite']], $data['sommeil']));

        $this->addSheet($writer, 'Recovery',
            ['Date', 'Score', 'Recommandation'],
            array_map(fn ($r) => [$r['date'], $r['score'], $r['recommandation']], $data['recovery']));

        $writer->close();

        return $path;
    }

    /** Ajoute une feuille avec une ligne d'en-tête puis les données. */
    private function addSheet(Writer $writer, string $name, array $header, array $rows): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName($name);
        $writer->addRow(Row::fromValues($header));
        foreach ($rows as $r) {
            $writer->addRow(Row::fromValues($r));
        }
    }

    private function humanLabel(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        return $value === null ? '' : (string) $value;
    }
}
