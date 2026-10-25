<?php

namespace App\Repository;

use App\Entity\JournalAlimentaire;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JournalAlimentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalAlimentaire::class);
    }

    /**
     * Le journal d'un utilisateur pour un jour donné, ou null s'il n'a encore rien mangé.
     * Les lignes et leurs aliments arrivent dans la même requête (leftJoin + addSelect), ça évite le N+1.
     */
    public function findByUserAndDate(User $user, \DateTimeInterface $date): ?JournalAlimentaire
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.lignes', 'l')
            ->addSelect('l')
            ->leftJoin('l.aliment', 'a')
            ->addSelect('a')
            ->where('j.utilisateur = :user')
            ->andWhere('j.dateJournal = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return JournalAlimentaire[] */
    public function findByUserBetweenDates(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.utilisateur = :user')
            ->andWhere('j.dateJournal BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('j.dateJournal', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
