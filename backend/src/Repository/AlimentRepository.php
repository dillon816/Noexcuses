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

    /** Recherche par nom anglais exact (pour le cache CalorieNinjas). */
    public function findByNomApi(string $nomApi): ?Aliment
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.nomApi) = :nomApi')
            ->setParameter('nomApi', mb_strtolower($nomApi, 'UTF-8'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Aliment[] */
    public function searchByName(string $term, int $limit = 20): array
    {
        // Priorité 1 : nom (FR) ou nomApi (EN) commence par le terme
        $prefix = $this->createQueryBuilder('a')
            ->where('a.nom LIKE :prefix OR a.nomApi LIKE :prefix')
            ->setParameter('prefix', $term . '%')
            ->setMaxResults($limit)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();

        if (!empty($prefix)) {
            return $prefix;
        }

        // Priorité 2 : le terme apparaît n'importe où (nom ou nomApi)
        return $this->createQueryBuilder('a')
            ->where('a.nom LIKE :term OR a.nomApi LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->setMaxResults($limit)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
