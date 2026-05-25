<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'ligne_journal')]
class LigneJournal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JournalAlimentaire::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JournalAlimentaire $journal;

    #[ORM\ManyToOne(targetEntity: Aliment::class)]
    #[ORM\JoinColumn(name: 'id_aliment', nullable: false, onDelete: 'RESTRICT')]
    private Aliment $aliment;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    #[Assert\Positive]
    private string $quantiteG;

    #[ORM\Column(type: 'string', length: 30)]
    #[Assert\Choice(choices: ['petit-dejeuner', 'dejeuner', 'diner', 'encas'])]
    private string $typeRepas;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    private string $calories;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $proteines;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $glucides;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $lipides;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, options: ['default' => 0])]
    private string $fibres = '0.00';

    public function calculerCalories(): void
    {
        $q = (float) $this->quantiteG / 100;
        $this->calories  = (string) round((float) $this->aliment->getCalories100g()  * $q, 2);
        $this->proteines = (string) round((float) $this->aliment->getProteines100g() * $q, 2);
        $this->glucides  = (string) round((float) $this->aliment->getGlucides100g()  * $q, 2);
        $this->lipides   = (string) round((float) $this->aliment->getLipides100g()   * $q, 2);
        $this->fibres    = (string) round((float) ($this->aliment->getFibres100g() ?? '0') * $q, 2);
    }

    public function getId(): ?int { return $this->id; }

    public function getJournal(): JournalAlimentaire { return $this->journal; }
    public function setJournal(JournalAlimentaire $j): self { $this->journal = $j; return $this; }

    public function getAliment(): Aliment { return $this->aliment; }
    public function setAliment(Aliment $a): self { $this->aliment = $a; return $this; }

    public function getQuantiteG(): string { return $this->quantiteG; }
    public function setQuantiteG(string $q): self { $this->quantiteG = $q; return $this; }

    public function getTypeRepas(): string { return $this->typeRepas; }
    public function setTypeRepas(string $t): self { $this->typeRepas = $t; return $this; }

    public function getCalories(): string { return $this->calories; }
    public function getProteines(): string { return $this->proteines; }
    public function getGlucides(): string { return $this->glucides; }
    public function getLipides(): string { return $this->lipides; }
    public function getFibres(): string { return $this->fibres; }
}
