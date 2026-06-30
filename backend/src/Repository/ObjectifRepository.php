<?php

namespace App\Repository;

use App\Entity\Objectif;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ObjectifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Objectif::class);
    }

    public function findActiveForUser(User $user): ?Objectif
    {
        return $this->createQueryBuilder('o')
            ->where('o.utilisateur = :user')
            ->andWhere('o.actif = true')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
