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

    /**
     * Met à jour le profil de l'utilisateur de façon partielle (PATCH).
     * Seuls les champs présents dans $data sont modifiés, les autres restent inchangés.
     * Les chaînes vides sont converties en null pour que les validators Symfony ne bloquent pas.
     */
    public function updateProfil(User $user, array $data): User
    {
        if (isset($data['prenom']) && $data['prenom'] !== '')
            $user->setPrenom($data['prenom']);
        if (isset($data['nom']) && $data['nom'] !== '')
            $user->setNom($data['nom']);
        if (array_key_exists('taille', $data))
            $user->setTaille($data['taille'] !== '' && $data['taille'] !== null ? (int) $data['taille'] : null);
        // Les chaînes vides → null pour que le validator Choice ne rejette pas les champs optionnels
        if (array_key_exists('sexe', $data))
            $user->setSexe($data['sexe'] !== '' ? $data['sexe'] : null);
        if (array_key_exists('niveauActivite', $data))
            $user->setNiveauActivite($data['niveauActivite'] !== '' ? $data['niveauActivite'] : null);
        if (isset($data['dateNaissance']) && $data['dateNaissance'] !== '')
            $user->setDateNaissance(new \DateTime($data['dateNaissance']));
        // L'objectif calorique est aussi stocké sur l'entité User pour un accès rapide depuis le dashboard
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

    /**
     * Crée un nouvel objectif nutritionnel et désactive l'ancien s'il en existe un.
     * L'objectif calorique est aussi mis à jour directement sur l'utilisateur.
     */
    public function setObjectif(User $user, string $type, int $caloriesJour, ?int $proteines = null, ?int $glucides = null, ?int $lipides = null): Objectif
    {
        // On désactive l'objectif actuel avant d'en créer un nouveau
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

        // Sync sur l'entité User pour que le dashboard puisse lire caloriesObjectif directement
        $user->setCaloriesObjectif($caloriesJour);

        $this->em->persist($objectif);
        $this->em->flush();

        return $objectif;
    }

    /** Enregistre une pesée dans l'historique de poids. Utilisé pour les graphiques de progression. */
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
