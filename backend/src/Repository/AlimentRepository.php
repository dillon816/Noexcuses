<?php

namespace App\Repository;

use App\Entity\Aliment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlimentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Aliment::class);
    }

    public function findByCodeApi(string $codeApi): ?Aliment
    {
        return $this->findOneBy(['codeApi' => $codeApi]);
    }

    /** @return Aliment[] */
    public function searchByName(string $term, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.nom LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->setMaxResults($limit)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
