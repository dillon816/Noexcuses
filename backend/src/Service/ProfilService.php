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
        if (isset($data['prenom']) && $data['prenom'] !== '')
            $user->setPrenom($data['prenom']);
        if (isset($data['nom']) && $data['nom'] !== '')
            $user->setNom($data['nom']);
        if (array_key_exists('taille', $data))
            $user->setTaille($data['taille'] !== '' && $data['taille'] !== null ? (int) $data['taille'] : null);
        // Convert empty strings → null so Choice validator doesn't reject them
        if (array_key_exists('sexe', $data))
            $user->setSexe($data['sexe'] !== '' ? $data['sexe'] : null);
        if (array_key_exists('niveauActivite', $data))
            $user->setNiveauActivite($data['niveauActivite'] !== '' ? $data['niveauActivite'] : null);
        if (isset($data['dateNaissance']) && $data['dateNaissance'] !== '')
            $user->setDateNaissance(new \DateTime($data['dateNaissance']));
        // Calories objectif — save directly on user so dashboard reads it immediately
        if (array_key_exists('caloriesObjectif', $data))
            $user->setCaloriesObjectif($data['caloriesObjectif'] !== '' && $data['caloriesObjectif'] !== null
                ? (int) $data['caloriesObjectif']
                : null);
        if (array_key_exists('poidsInitial', $data))
            $user->setPoidsInitial($data['poidsInitial'] !== '' && $data['poidsInitial'] !== null
                ? (string) $data['poidsInitial']
                : null);

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
