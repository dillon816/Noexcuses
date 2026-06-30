<?php

namespace App\Entity;

use App\Repository\JournalAlimentaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalAlimentaireRepository::class)]
#[ORM\Table(name: 'journal_alimentaire')]
#[ORM\UniqueConstraint(name: 'uniq_journal_user_date', columns: ['id_utilisateur', 'date_journal'])]
class JournalAlimentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $dateJournal;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => 0])]
    private string $caloriesTotales = '0.00';

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, options: ['default' => 0])]
    private string $proteinesTotales = '0.00';

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, options: ['default' => 0])]
    private string $glucidesTotaux = '0.00';

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, options: ['default' => 0])]
    private string $lipidesTotaux = '0.00';

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, options: ['default' => 0])]
    private string $fibresTotales = '0.00';

    #[ORM\OneToMany(mappedBy: 'journal', targetEntity: LigneJournal::class, cascade: ['persist', 'remove'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    public function mettreAJourTotaux(): void
    {
        $cal = $prot = $gluc = $lip = $fib = 0.0;
        foreach ($this->lignes as $ligne) {
            $cal  += (float) $ligne->getCalories();
            $prot += (float) $ligne->getProteines();
            $gluc += (float) $ligne->getGlucides();
            $lip  += (float) $ligne->getLipides();
            $fib  += (float) $ligne->getFibres();
        }
        $this->caloriesTotales  = (string) round($cal,  2);
        $this->proteinesTotales = (string) round($prot, 2);
        $this->glucidesTotaux   = (string) round($gluc, 2);
        $this->lipidesTotaux    = (string) round($lip,  2);
        $this->fibresTotales    = (string) round($fib,  2);
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $u): self { $this->utilisateur = $u; return $this; }

    public function getDateJournal(): \DateTimeInterface { return $this->dateJournal; }
    public function setDateJournal(\DateTimeInterface $d): self { $this->dateJournal = $d; return $this; }

    public function getCaloriesTotales(): string { return $this->caloriesTotales; }
    public function getProteinesTotales(): string { return $this->proteinesTotales; }
    public function getGlucidesTotaux(): string { return $this->glucidesTotaux; }
    public function getLipidesTotaux(): string { return $this->lipidesTotaux; }
    public function getFibresTotales(): string { return $this->fibresTotales; }

    public function getLignes(): Collection { return $this->lignes; }
    public function addLigne(LigneJournal $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setJournal($this);
        }
        return $this;
    }
    public function removeLigne(LigneJournal $ligne): self
    {
        $this->lignes->removeElement($ligne);
        return $this;
    }
}
