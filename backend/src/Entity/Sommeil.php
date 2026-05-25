<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'sommeil')]
#[ORM\UniqueConstraint(name: 'uniq_sommeil_user_date', columns: ['id_utilisateur', 'date_nuit'])]
class Sommeil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $dateNuit;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    #[Assert\Range(min: 0, max: 24)]
    private string $heuresSommeil;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 5)]
    private int $qualiteSommeil;

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $u): self { $this->utilisateur = $u; return $this; }

    public function getDateNuit(): \DateTimeInterface { return $this->dateNuit; }
    public function setDateNuit(\DateTimeInterface $d): self { $this->dateNuit = $d; return $this; }

    public function getHeuresSommeil(): string { return $this->heuresSommeil; }
    public function setHeuresSommeil(string $h): self { $this->heuresSommeil = $h; return $this; }

    public function getQualiteSommeil(): int { return $this->qualiteSommeil; }
    public function setQualiteSommeil(int $q): self { $this->qualiteSommeil = $q; return $this; }
}
