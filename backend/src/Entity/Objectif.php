<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'objectif')]
class Objectif
{
    public const TYPE_PERTE_POIDS  = 'perte_poids';
    public const TYPE_PRISE_MASSE  = 'prise_masse';
    public const TYPE_MAINTIEN     = 'maintien';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: [self::TYPE_PERTE_POIDS, self::TYPE_PRISE_MASSE, self::TYPE_MAINTIEN])]
    private string $type;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 500, max: 10000)]
    private int $caloriesJour;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $proteinesG = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $glucidesG = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lipidesG = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $u): self { $this->utilisateur = $u; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = $t; return $this; }

    public function getCaloriesJour(): int { return $this->caloriesJour; }
    public function setCaloriesJour(int $c): self { $this->caloriesJour = $c; return $this; }

    public function getProteinesG(): ?int { return $this->proteinesG; }
    public function setProteinesG(?int $p): self { $this->proteinesG = $p; return $this; }

    public function getGlucidesG(): ?int { return $this->glucidesG; }
    public function setGlucidesG(?int $g): self { $this->glucidesG = $g; return $this; }

    public function getLipidesG(): ?int { return $this->lipidesG; }
    public function setLipidesG(?int $l): self { $this->lipidesG = $l; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $a): self { $this->actif = $a; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
