<?php

namespace App\Service;

use App\Entity\Aliment;
use App\Entity\JournalAlimentaire;
use App\Entity\LigneJournal;
use App\Entity\User;
use App\Repository\AlimentRepository;
use App\Repository\JournalAlimentaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NutritionService
{
    private const OFF_API_URL = 'https://world.openfoodfacts.org/cgi/search.pl';

    public function __construct(
        private readonly EntityManagerInterface        $em,
        private readonly AlimentRepository             $alimentRepo,
        private readonly JournalAlimentaireRepository  $journalRepo,
        private readonly HttpClientInterface           $httpClient,
    ) {}

    /** Search locally first, then fallback to OpenFoodFacts. */
    public function searchAliment(string $term): array
    {
        $local = $this->alimentRepo->searchByName($term);
        if (!empty($local)) {
            return $local;
        }

        return $this->searchOpenFoodFacts($term);
    }

    private function searchOpenFoodFacts(string $term): array
    {
        $response = $this->httpClient->request('GET', self::OFF_API_URL, [
            'query' => [
                'search_terms'   => $term,
                'search_simple'  => 1,
                'action'         => 'process',
                'json'           => 1,
                'page_size'      => 20,
                'fields'         => 'code,product_name,nutriments',
            ],
            'timeout' => 5,
        ]);

        $data = $response->toArray(false);
        $results = [];

        foreach ($data['products'] ?? [] as $product) {
            $nutriments = $product['nutriments'] ?? [];
            if (empty($product['product_name']) || !isset($nutriments['energy-kcal_100g'])) {
                continue;
            }

            $aliment = $this->alimentRepo->findByCodeApi($product['code'] ?? '')
                       ?? $this->createAlimentFromOFF($product, $nutriments);

            $results[] = $aliment;
        }

        return $results;
    }

    private function createAlimentFromOFF(array $product, array $n): Aliment
    {
        $aliment = new Aliment();
        $aliment->setNom($product['product_name']);
        $aliment->setCodeApi($product['code'] ?? null);
        $aliment->setCalories100g((string) ($n['energy-kcal_100g'] ?? 0));
        $aliment->setProteines100g((string) ($n['proteins_100g'] ?? 0));
        $aliment->setGlucides100g((string) ($n['carbohydrates_100g'] ?? 0));
        $aliment->setLipides100g((string) ($n['fat_100g'] ?? 0));
        $aliment->setFibres100g(isset($n['fiber_100g']) ? (string) $n['fiber_100g'] : null);

        $this->em->persist($aliment);
        $this->em->flush();

        return $aliment;
    }

    public function addRepas(User $user, int $alimentId, float $quantiteG, string $typeRepas, ?\DateTimeInterface $date = null): LigneJournal
    {
        $date ??= new \DateTime();

        $aliment = $this->em->getRepository(Aliment::class)->find($alimentId);
        if (!$aliment) {
            throw new \InvalidArgumentException('Aliment introuvable.');
        }

        $journal = $this->journalRepo->findByUserAndDate($user, $date);
        if (!$journal) {
            $journal = new JournalAlimentaire();
            $journal->setUtilisateur($user);
            $journal->setDateJournal($date);
            $this->em->persist($journal);
        }

        $ligne = new LigneJournal();
        $ligne->setAliment($aliment);
        $ligne->setQuantiteG((string) $quantiteG);
        $ligne->setTypeRepas($typeRepas);
        $ligne->calculerCalories();

        $journal->addLigne($ligne);
        $journal->mettreAJourTotaux();

        $this->em->persist($ligne);
        $this->em->flush();

        return $ligne;
    }

    public function getJournal(User $user, \DateTimeInterface $date): ?JournalAlimentaire
    {
        return $this->journalRepo->findByUserAndDate($user, $date);
    }

    public function removeRepas(User $user, int $ligneId): void
    {
        $ligne = $this->em->getRepository(LigneJournal::class)->find($ligneId);
        if (!$ligne || $ligne->getJournal()->getUtilisateur()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Ligne introuvable.');
        }

        $journal = $ligne->getJournal();
        $journal->removeLigne($ligne);
        $this->em->remove($ligne);
        $this->em->flush();
        $journal->mettreAJourTotaux();
        $this->em->flush();
    }
}
