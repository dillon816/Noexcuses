<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $prenom;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $nom;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[Assert\Choice(choices: ['homme', 'femme', 'autre'])]
    private ?string $sexe = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 50, max: 300)]
    private ?int $taille = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 20, max: 500)]
    private ?string $poidsInitial = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 500, max: 10000)]
    private ?int $caloriesObjectif = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['sédentaire', 'légèrement actif', 'modérément actif', 'très actif', 'extrêmement actif'])]
    private ?string $niveauActivite = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getDateNaissance(): ?\DateTimeInterface { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeInterface $date): self { $this->dateNaissance = $date; return $this; }

    public function getSexe(): ?string { return $this->sexe; }
    public function setSexe(?string $sexe): self { $this->sexe = $sexe; return $this; }

    public function getTaille(): ?int { return $this->taille; }
    public function setTaille(?int $taille): self { $this->taille = $taille; return $this; }

    public function getPoidsInitial(): ?string { return $this->poidsInitial; }
    public function setPoidsInitial(?string $poids): self { $this->poidsInitial = $poids; return $this; }

    public function getCaloriesObjectif(): ?int { return $this->caloriesObjectif; }
    public function setCaloriesObjectif(?int $cal): self { $this->caloriesObjectif = $cal; return $this; }

    public function getNiveauActivite(): ?string { return $this->niveauActivite; }
    public function setNiveauActivite(?string $niveau): self { $this->niveauActivite = $niveau; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
