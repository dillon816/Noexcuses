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

    /** @return Seance[] */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('s.dateSeance', 'DESC')
            ->setMaxResults($limit)
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
            ->andWhere('s.statut != :archivee')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('archivee', Seance::STATUT_ARCHIVEE)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /** @return Seance[] */
    public function findByUserBetweenDates(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.dateSeance BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('s.dateSeance', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
