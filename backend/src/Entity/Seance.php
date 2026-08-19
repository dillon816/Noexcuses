<?php

namespace App\Entity;

use App\Repository\SeanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'seance')]
#[ORM\HasLifecycleCallbacks]
class Seance
{
    public const STATUT_EN_COURS  = 'en_cours';
    public const STATUT_TERMINEE  = 'terminee';
    public const STATUT_ARCHIVEE  = 'archivee';
    // Seance-modele reutilisable : sert de gabarit, exclue de l'historique et des statistiques.
    public const STATUT_MODELE    = 'modele';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(type: 'string', length: 150)]
    private string $nom;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $dateSeance;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'en_cours'])]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => 0])]
    private string $tonnageTotal = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'seance', targetEntity: SerieExercice::class, cascade: ['persist', 'remove'])]
    private Collection $series;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->series = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function calculerTonnage(): void
    {
        $total = 0.0;
        foreach ($this->series as $serie) {
            $total += (float) $serie->getTonnage();
        }
        $this->tonnageTotal = (string) round($total, 2);
    }

    public function archiver(): void
    {
        $this->statut = self::STATUT_ARCHIVEE;
    }

    public function isModele(): bool
    {
        return $this->statut === self::STATUT_MODELE;
    }

    /**
     * Nombre d'exercices distincts de la séance (par id_exercice), à ne pas confondre
     * avec le nombre total de séries : 2 exercices en 3 séries = 2 exercices, 6 séries.
     */
    public function getNbExercices(): int
    {
        $ids = [];
        foreach ($this->series as $serie) {
            $exercice = $serie->getExercice();
            $ids[$exercice->getId() ?? spl_object_id($exercice)] = true;
        }

        return count($ids);
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $u): self { $this->utilisateur = $u; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getDateSeance(): \DateTimeInterface { return $this->dateSeance; }
    public function setDateSeance(\DateTimeInterface $d): self { $this->dateSeance = $d; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }

    public function getTonnageTotal(): string { return $this->tonnageTotal; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }

    public function getSeries(): Collection { return $this->series; }
    public function addSerie(SerieExercice $serie): self
    {
        if (!$this->series->contains($serie)) {
            $this->series->add($serie);
            $serie->setSeance($this);
        }
        return $this;
    }
    public function removeSerie(SerieExercice $serie): self
    {
        $this->series->removeElement($serie);
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
