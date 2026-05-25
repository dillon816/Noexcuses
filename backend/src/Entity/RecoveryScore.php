<?php

namespace App\Entity;

use App\Repository\RecoveryScoreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecoveryScoreRepository::class)]
#[ORM\Table(name: 'recovery_score')]
#[ORM\UniqueConstraint(name: 'uniq_recovery_user_date', columns: ['id_utilisateur', 'date_calcul'])]
class RecoveryScore
{
    public const RECOMMANDATION_REPOS    = 'Repos recommandé';
    public const RECOMMANDATION_LEGERE   = 'Séance légère';
    public const RECOMMANDATION_NORMALE  = 'Séance normale';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $dateCalcul;

    #[ORM\Column(type: 'integer')]
    private int $score;

    #[ORM\Column(type: 'string', length: 50)]
    private string $recommandation;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $charge7j = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $bilanCalorique = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $heuresSommeilMoy = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $calculatedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->calculatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $u): self { $this->utilisateur = $u; return $this; }

    public function getDateCalcul(): \DateTimeInterface { return $this->dateCalcul; }
    public function setDateCalcul(\DateTimeInterface $d): self { $this->dateCalcul = $d; return $this; }

    public function getScore(): int { return $this->score; }
    public function setScore(int $s): self { $this->score = max(0, min(100, $s)); return $this; }

    public function getRecommandation(): string { return $this->recommandation; }
    public function setRecommandation(string $r): self { $this->recommandation = $r; return $this; }

    public function getCharge7j(): ?string { return $this->charge7j; }
    public function setCharge7j(?string $c): self { $this->charge7j = $c; return $this; }

    public function getBilanCalorique(): ?string { return $this->bilanCalorique; }
    public function setBilanCalorique(?string $b): self { $this->bilanCalorique = $b; return $this; }

    public function getHeuresSommeilMoy(): ?string { return $this->heuresSommeilMoy; }
    public function setHeuresSommeilMoy(?string $h): self { $this->heuresSommeilMoy = $h; return $this; }

    public function getCalculatedAt(): \DateTimeInterface { return $this->calculatedAt; }
}
