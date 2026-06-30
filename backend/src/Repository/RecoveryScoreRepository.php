<?php

namespace App\Repository;

use App\Entity\RecoveryScore;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecoveryScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecoveryScore::class);
    }

    public function findLatestForUser(User $user): ?RecoveryScore
    {
        return $this->createQueryBuilder('r')
            ->where('r.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('r.dateCalcul', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUserAndDate(User $user, \DateTimeInterface $date): ?RecoveryScore
    {
        return $this->createQueryBuilder('r')
            ->where('r.utilisateur = :user')
            ->andWhere('r.dateCalcul = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
