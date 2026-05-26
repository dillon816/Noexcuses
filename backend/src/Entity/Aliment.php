<?php

namespace App\Entity;

use App\Repository\AlimentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlimentRepository::class)]
#[ORM\Table(name: 'aliment')]
class Aliment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $nom;

    #[ORM\Column(type: 'string', length: 100, unique: true, nullable: true)]
    private ?string $codeApi = null;

    /** Nom anglais utilisé pour l'appel CalorieNinjas (ex: "chicken breast") */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nomApi = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    private string $calories100g;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $proteines100g;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $glucides100g;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $lipides100g;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $fibres100g = null;

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getCodeApi(): ?string { return $this->codeApi; }
    public function setCodeApi(?string $code): self { $this->codeApi = $code; return $this; }

    public function getNomApi(): ?string { return $this->nomApi; }
    public function setNomApi(?string $nomApi): self { $this->nomApi = $nomApi; return $this; }

    public function getCalories100g(): string { return $this->calories100g; }
    public function setCalories100g(string $val): self { $this->calories100g = $val; return $this; }

    public function getProteines100g(): string { return $this->proteines100g; }
    public function setProteines100g(string $val): self { $this->proteines100g = $val; return $this; }

    public function getGlucides100g(): string { return $this->glucides100g; }
    public function setGlucides100g(string $val): self { $this->glucides100g = $val; return $this; }

    public function getLipides100g(): string { return $this->lipides100g; }
    public function setLipides100g(string $val): self { $this->lipides100g = $val; return $this; }

    public function getFibres100g(): ?string { return $this->fibres100g; }
    public function setFibres100g(?string $val): self { $this->fibres100g = $val; return $this; }
}
