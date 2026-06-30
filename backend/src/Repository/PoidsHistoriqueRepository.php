<?php

namespace App\Repository;

use App\Entity\PoidsHistorique;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PoidsHistoriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoidsHistorique::class);
    }

    /** @return PoidsHistorique[] */
    public function findByUser(User $user, int $limit = 90): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.datePesee', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
