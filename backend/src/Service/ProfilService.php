<?php

namespace App\Service;

use App\Entity\Objectif;
use App\Entity\PoidsHistorique;
use App\Entity\User;
use App\Repository\ObjectifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfilService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ObjectifRepository     $objectifRepo,
        private readonly ValidatorInterface     $validator,
    ) {}

    public function updateProfil(User $user, array $data): User
    {
        if (isset($data['prenom']))        $user->setPrenom($data['prenom']);
        if (isset($data['nom']))           $user->setNom($data['nom']);
        if (isset($data['taille']))        $user->setTaille((int) $data['taille']);
        if (isset($data['sexe']))          $user->setSexe($data['sexe']);
        if (isset($data['niveauActivite'])) $user->setNiveauActivite($data['niveauActivite']);
        if (isset($data['dateNaissance'])) {
            $user->setDateNaissance(new \DateTime($data['dateNaissance']));
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException((string) $errors);
        }

        $this->em->flush();

        return $user;
    }

    public function setObjectif(User $user, string $type, int $caloriesJour, ?int $proteines = null, ?int $glucides = null, ?int $lipides = null): Objectif
    {
        $current = $this->objectifRepo->findActiveForUser($user);
        if ($current) {
            $current->setActif(false);
        }

        $objectif = new Objectif();
        $objectif->setUtilisateur($user);
        $objectif->setType($type);
        $objectif->setCaloriesJour($caloriesJour);
        $objectif->setProteinesG($proteines);
        $objectif->setGlucidesG($glucides);
        $objectif->setLipidesG($lipides);

        $user->setCaloriesObjectif($caloriesJour);

        $this->em->persist($objectif);
        $this->em->flush();

        return $objectif;
    }

    public function logPoids(User $user, float $poids, ?\DateTimeInterface $date = null): PoidsHistorique
    {
        $date ??= new \DateTime();

        $entry = new PoidsHistorique();
        $entry->setUtilisateur($user);
        $entry->setPoidsKg((string) $poids);
        $entry->setDatePesee($date);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }
}
