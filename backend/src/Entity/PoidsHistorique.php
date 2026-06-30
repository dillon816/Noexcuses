<?php

namespace App\Entity;

use App\Repository\PoidsHistoriqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PoidsHistoriqueRepository::class)]
#[ORM\Table(name: 'poids_historique')]
class PoidsHistorique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $datePesee;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Range(min: 20, max: 500)]
    private string $poidsKg;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $u): self { $this->utilisateur = $u; return $this; }

    public function getDatePesee(): \DateTimeInterface { return $this->datePesee; }
    public function setDatePesee(\DateTimeInterface $d): self { $this->datePesee = $d; return $this; }

    public function getPoidsKg(): string { return $this->poidsKg; }
    public function setPoidsKg(string $p): self { $this->poidsKg = $p; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
}
