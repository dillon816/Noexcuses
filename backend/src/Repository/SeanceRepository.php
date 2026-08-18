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
     * Historique des seances reelles (exclut les seances-modeles reutilisables).
     * @return Seance[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.statut != :modele')
            ->setParameter('user', $user)
            ->setParameter('modele', Seance::STATUT_MODELE)
            ->orderBy('s.dateSeance', 'DESC')
            ->setMaxResults($limit)
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

    public function getTonnageLast7Days(User $user): float
    {
        $from = (new \DateTime())->modify('-7 days')->format('Y-m-d');
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.tonnageTotal) as total')
            ->where('s.utilisateur = :user')
            ->andWhere('s.dateSeance >= :from')
            ->andWhere('s.statut NOT IN (:exclus)')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('exclus', [Seance::STATUT_ARCHIVEE, Seance::STATUT_MODELE])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Seances reelles sur une periode (exclut les modeles), pour les statistiques.
     * @return Seance[]
     */
    public function findByUserBetweenDates(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.dateSeance BETWEEN :from AND :to')
            ->andWhere('s.statut != :modele')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->setParameter('modele', Seance::STATUT_MODELE)
            ->orderBy('s.dateSeance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
