<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'serie_exercice')]
class SerieExercice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Seance::class, inversedBy: 'series')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Seance $seance;

    #[ORM\ManyToOne(targetEntity: Exercice::class)]
    #[ORM\JoinColumn(name: 'id_exercice', nullable: false, onDelete: 'RESTRICT')]
    private Exercice $exercice;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $repetitions;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $chargeKg;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $tonnage;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $numeroSerie = 1;

    public function calculerTonnage(): void
    {
        $this->tonnage = (string) round($this->repetitions * (float) $this->chargeKg, 2);
    }

    public function getId(): ?int { return $this->id; }

    public function getSeance(): Seance { return $this->seance; }
    public function setSeance(Seance $s): self { $this->seance = $s; return $this; }

    public function getExercice(): Exercice { return $this->exercice; }
    public function setExercice(Exercice $e): self { $this->exercice = $e; return $this; }

    public function getRepetitions(): int { return $this->repetitions; }
    public function setRepetitions(int $r): self { $this->repetitions = $r; return $this; }

    public function getChargeKg(): string { return $this->chargeKg; }
    public function setChargeKg(string $c): self { $this->chargeKg = $c; return $this; }

    public function getTonnage(): string { return $this->tonnage; }

    public function getNumeroSerie(): int { return $this->numeroSerie; }
    public function setNumeroSerie(int $n): self { $this->numeroSerie = $n; return $this; }
}
