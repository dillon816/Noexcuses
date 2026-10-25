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

    /** Le score le plus récent de l'utilisateur, ou null s'il n'en a jamais calculé. */
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

    /** Le score d'un jour précis. Sert à remplacer le score du jour au lieu d'en créer un deuxième. */
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
