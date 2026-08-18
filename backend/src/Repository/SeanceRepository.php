<?php

namespace App\Repository;

use App\Entity\Seance;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    /**
     * Historique : seances reellement effectuees (statut "terminee" uniquement).
     * Les modeles et les seances en cours en sont exclus, tout comme des statistiques.
     * @return Seance[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.statut = :terminee')
            ->setParameter('user', $user)
            ->setParameter('terminee', Seance::STATUT_TERMINEE)
            ->orderBy('s.dateSeance', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Seances actuellement en cours (demarrees mais pas encore terminees).
     * @return Seance[]
     */
    public function findEnCours(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.statut = :enCours')
            ->setParameter('user', $user)
            ->setParameter('enCours', Seance::STATUT_EN_COURS)
            ->orderBy('s.dateSeance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Seances-modeles reutilisables de l'utilisateur (gabarits).
     * @return Seance[]
     */
    public function findModeles(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.statut = :modele')
            ->setParameter('user', $user)
            ->setParameter('modele', Seance::STATUT_MODELE)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Charge d'entrainement des 7 derniers jours pour le Recovery Score.
     * Seules les seances terminees comptent : ni les modeles ni les seances en cours.
     */
    public function getTonnageLast7Days(User $user): float
    {
        $from = (new \DateTime())->modify('-7 days')->format('Y-m-d');
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.tonnageTotal) as total')
            ->where('s.utilisateur = :user')
            ->andWhere('s.dateSeance >= :from')
            ->andWhere('s.statut = :terminee')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('terminee', Seance::STATUT_TERMINEE)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Seances terminees sur une periode, pour les statistiques de progression.
     * @return Seance[]
     */
    public function findByUserBetweenDates(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.dateSeance BETWEEN :from AND :to')
            ->andWhere('s.statut = :terminee')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->setParameter('terminee', Seance::STATUT_TERMINEE)
            ->orderBy('s.dateSeance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
