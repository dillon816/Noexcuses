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
    private const CALORIENINJAS_URL = 'https://api.calorieninjas.com/v1/nutrition';

    public function __construct(
        private readonly EntityManagerInterface        $em,
        private readonly AlimentRepository             $alimentRepo,
        private readonly JournalAlimentaireRepository  $journalRepo,
        private readonly HttpClientInterface           $httpClient,
        private readonly AiTranslationService          $aiTranslation,
        private readonly string                        $calorieNinjasKey,
    ) {}

    /**
     * Seuil minimum de résultats en cache pour éviter un appel CalorieNinjas.
     * En dessous de ce seuil, on enrichit toujours via l'API pour avoir
     * toutes les déclinaisons (ex: "poulet" → cuisse, blanc, escalope...).
     */
    private const CACHE_MIN_RESULTS = 5;

    public function searchAliment(string $term): array
    {
        $termNorm = $this->removeAccents(mb_strtolower(trim($term), 'UTF-8'));

        // Cache local — retourner seulement si on a assez de variété
        $local = array_values(array_filter(
            $this->alimentRepo->searchByName($term),
            fn(Aliment $a) => str_starts_with(
                $this->removeAccents(mb_strtolower($a->getNom(), 'UTF-8')),
                $termNorm
            )
        ));

        if (count($local) >= self::CACHE_MIN_RESULTS) {
            return $local;
        }

        // Pas assez de variété → pipeline IA + CalorieNinjas
        // (searchCalorieNinjas gère lui-même le cache par nomApi)
        $fromApi = $this->searchCalorieNinjas($term);

        // Si l'API échoue, on retourne ce qu'on a en cache (même incomplet)
        return !empty($fromApi) ? $fromApi : $local;
    }

    /**
     * Supprime les accents d'une chaîne UTF-8.
     */
    private function removeAccents(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), [
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a',
            'î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','œ'=>'oe','æ'=>'ae','ñ'=>'n',
        ]);
    }

    /**
     * Traduit un terme alimentaire FR → EN avant l'appel CalorieNinjas.
     * Stratégie : normaliser les accents des deux côtés, puis chercher par longueur décroissante.
     */
    private function translateToEn(string $term): string
    {
        // Dictionnaire clé = FR sans accents (minuscule), valeur = EN
        // On stocke sans accents pour éviter tout problème d'encodage fichier
        $dict = [
            // --- Viandes ---
            'escalope de poulet'       => 'chicken breast',
            'blanc de poulet'          => 'chicken breast',
            'poitrine de poulet'       => 'chicken breast',
            'cuisse de poulet'         => 'chicken thigh',
            'poulet roti'              => 'roasted chicken',
            'poulet'                   => 'chicken',
            'steak hache'              => 'ground beef',
            'viande hachee'            => 'ground beef',
            'cote de boeuf'            => 'ribeye steak',
            'entrecote'                => 'ribeye steak',
            'filet de boeuf'           => 'beef tenderloin',
            'boeuf'                    => 'beef',
            'cote de porc'             => 'pork chop',
            'poitrine de porc'         => 'pork belly',
            'filet de porc'            => 'pork tenderloin',
            'porc'                     => 'pork',
            'jambon blanc'             => 'cooked ham',
            'jambon cru'               => 'prosciutto',
            'jambon'                   => 'ham',
            'lardons'                  => 'bacon bits',
            'saucisse de francfort'    => 'frankfurter',
            'saucisson'                => 'dry sausage',
            'saucisse'                 => 'sausage',
            'merguez'                  => 'lamb sausage',
            'boudin noir'              => 'blood sausage',
            'boudin'                   => 'blood sausage',
            'agneau'                   => 'lamb',
            'gigot'                    => 'leg of lamb',
            'veau'                     => 'veal',
            'dinde'                    => 'turkey',
            'poulet dinde'             => 'turkey',
            'canard'                   => 'duck',
            'lapin'                    => 'rabbit',
            'cheval'                   => 'horse meat',
            // --- Poissons & fruits de mer ---
            'saumon fume'              => 'smoked salmon',
            'saumon'                   => 'salmon',
            'thon en boite'            => 'canned tuna',
            'thon'                     => 'tuna',
            'crevettes cuites'         => 'cooked shrimp',
            'crevettes'                => 'shrimp',
            'crevette'                 => 'shrimp',
            'cabillaud'                => 'cod',
            'morue'                    => 'cod',
            'truite'                   => 'trout',
            'sardines'                 => 'sardine',
            'sardine'                  => 'sardine',
            'maquereau'                => 'mackerel',
            'bar'                      => 'sea bass',
            'loup de mer'              => 'sea bass',
            'dorade'                   => 'sea bream',
            'daurade'                  => 'sea bream',
            'lieu'                     => 'pollock',
            'moule'                    => 'mussel',
            'moules'                   => 'mussel',
            'calmar'                   => 'squid',
            'poulpe'                   => 'octopus',
            'homard'                   => 'lobster',
            'langouste'                => 'lobster',
            // --- Produits laitiers ---
            'lait entier'              => 'whole milk',
            'lait demi-ecreme'         => 'semi-skimmed milk',
            'lait ecreme'              => 'skim milk',
            'lait'                     => 'milk',
            'beurre'                   => 'butter',
            'fromage blanc'            => 'quark cheese',
            'fromage de chevre'        => 'goat cheese',
            'fromage'                  => 'cheese',
            'yaourt nature'            => 'plain yogurt',
            'yaourt aux fruits'        => 'fruit yogurt',
            'yaourt'                   => 'yogurt',
            'yogourt'                  => 'yogurt',
            'creme fraiche'            => 'sour cream',
            'creme liquide'            => 'heavy cream',
            'creme'                    => 'cream',
            'gruyere'                  => 'gruyere',
            'emmental'                 => 'emmental cheese',
            'camembert'                => 'camembert',
            'brie'                     => 'brie',
            'roquefort'                => 'roquefort',
            'parmesan'                 => 'parmesan',
            'mozzarella'               => 'mozzarella',
            'feta'                     => 'feta',
            'ricotta'                  => 'ricotta',
            'cheddar'                  => 'cheddar',
            'cottage'                  => 'cottage cheese',
            'skyr'                     => 'skyr',
            // --- Féculents & céréales ---
            'riz blanc cuit'           => 'white rice cooked',
            'riz complet cuit'         => 'brown rice cooked',
            'riz blanc'                => 'white rice',
            'riz complet'              => 'brown rice',
            'riz basmati'              => 'basmati rice',
            'riz'                      => 'rice',
            'pates cuites'             => 'pasta cooked',
            'pates completes'          => 'whole wheat pasta',
            'pates'                    => 'pasta',
            'spaghetti'                => 'spaghetti',
            'tagliatelles'             => 'tagliatelle',
            'penne'                    => 'penne pasta',
            'pain complet'             => 'whole wheat bread',
            'pain de mie'              => 'sandwich bread',
            'pain aux cereales'        => 'multigrain bread',
            'pain'                     => 'bread',
            'baguette'                 => 'french baguette',
            'brioche'                  => 'brioche',
            'croissant'                => 'croissant',
            'pain au chocolat'         => 'pain au chocolat',
            'pommes de terre cuites'   => 'boiled potato',
            'pomme de terre'           => 'potato',
            'pommes de terre'          => 'potato',
            'patates douces'           => 'sweet potato',
            'patate douce'             => 'sweet potato',
            'patates'                  => 'potato',
            'patate'                   => 'potato',
            'frites'                   => 'french fries',
            'frite'                    => 'french fries',
            'puree de pomme de terre'  => 'mashed potato',
            'puree'                    => 'mashed potato',
            'flocons d avoine'         => 'oatmeal',
            'flocons davoine'          => 'oatmeal',
            'avoine'                   => 'oats',
            'muesli'                   => 'muesli',
            'granola'                  => 'granola',
            'cereales'                 => 'cereal',
            'quinoa cuit'              => 'quinoa cooked',
            'quinoa'                   => 'quinoa',
            'boulgour'                 => 'bulgur',
            'bulgur'                   => 'bulgur',
            'semoule'                  => 'semolina',
            'couscous'                 => 'couscous',
            'lentilles vertes'         => 'green lentils',
            'lentilles'                => 'lentils',
            'lentille'                 => 'lentils',
            'haricots blancs'          => 'white beans',
            'haricots rouges'          => 'kidney beans',
            'haricots verts'           => 'green beans',
            'haricots'                 => 'beans',
            'haricot'                  => 'beans',
            'pois chiches'             => 'chickpeas',
            'pois'                     => 'peas',
            'mais'                     => 'corn',
            'edamame'                  => 'edamame',
            // --- Légumes ---
            'tomates cerises'          => 'cherry tomatoes',
            'tomate'                   => 'tomato',
            'tomates'                  => 'tomato',
            'carottes'                 => 'carrot',
            'carotte'                  => 'carrot',
            'salade verte'             => 'green salad',
            'salade'                   => 'lettuce',
            'laitue'                   => 'lettuce',
            'roquette'                 => 'arugula',
            'epinards'                 => 'spinach',
            'epinard'                  => 'spinach',
            'courgettes'               => 'zucchini',
            'courgette'                => 'zucchini',
            'aubergine'                => 'eggplant',
            'oignons'                  => 'onion',
            'oignon'                   => 'onion',
            'echalote'                 => 'shallot',
            'ail'                      => 'garlic',
            'poivron rouge'            => 'red bell pepper',
            'poivron vert'             => 'green bell pepper',
            'poivron'                  => 'bell pepper',
            'champignons de paris'     => 'mushroom',
            'champignons'              => 'mushroom',
            'champignon'               => 'mushroom',
            'concombre'                => 'cucumber',
            'poireaux'                 => 'leek',
            'poireau'                  => 'leek',
            'chou-fleur'               => 'cauliflower',
            'chou fleur'               => 'cauliflower',
            'choux de bruxelles'       => 'brussels sprouts',
            'chou'                     => 'cabbage',
            'brocoli'                  => 'broccoli',
            'brocolis'                 => 'broccoli',
            'asperges'                 => 'asparagus',
            'asperge'                  => 'asparagus',
            'betterave'                => 'beet',
            'celeri rave'              => 'celeriac',
            'celeri'                   => 'celery',
            'radis'                    => 'radish',
            'navet'                    => 'turnip',
            'endive'                   => 'endive',
            'fenouil'                  => 'fennel',
            'artichaut'                => 'artichoke',
            'poivrade'                 => 'artichoke',
            'panais'                   => 'parsnip',
            'potiron'                  => 'pumpkin',
            'courge'                   => 'squash',
            'butternut'                => 'butternut squash',
            'petits pois'              => 'green peas',
            // --- Fruits ---
            'pomme verte'              => 'green apple',
            'pomme'                    => 'apple',
            'poire'                    => 'pear',
            'banane'                   => 'banana',
            'orange'                   => 'orange',
            'citron vert'              => 'lime',
            'citron'                   => 'lemon',
            'pamplemousse'             => 'grapefruit',
            'fraises'                  => 'strawberry',
            'fraise'                   => 'strawberry',
            'framboises'               => 'raspberry',
            'framboise'                => 'raspberry',
            'myrtilles'                => 'blueberry',
            'myrtille'                 => 'blueberry',
            'mures'                    => 'blackberry',
            'cerises'                  => 'cherry',
            'cerise'                   => 'cherry',
            'raisins secs'             => 'raisins',
            'raisin'                   => 'grape',
            'peche'                    => 'peach',
            'abricot'                  => 'apricot',
            'prune'                    => 'plum',
            'melon'                    => 'melon',
            'ananas'                   => 'pineapple',
            'pasteque'                 => 'watermelon',
            'mangue'                   => 'mango',
            'kiwi'                     => 'kiwi',
            'papaye'                   => 'papaya',
            'figue'                    => 'fig',
            'datte'                    => 'date',
            'noix de coco'             => 'coconut',
            'coco'                     => 'coconut',
            'avocat'                   => 'avocado',
            // --- Oléagineux ---
            'beurre de cacahuete'      => 'peanut butter',
            'beurre d arachide'        => 'peanut butter',
            'beurre darachide'         => 'peanut butter',
            'cacahuetes'               => 'peanut',
            'cacahuete'                => 'peanut',
            'arachides'                => 'peanut',
            'noix de cajou'            => 'cashew',
            'noix de macadamia'        => 'macadamia',
            'noix de grenoble'         => 'walnut',
            'noix'                     => 'walnut',
            'amandes'                  => 'almond',
            'amande'                   => 'almond',
            'noisettes'                => 'hazelnut',
            'noisette'                 => 'hazelnut',
            'pistaches'                => 'pistachio',
            'pistache'                 => 'pistachio',
            'graines de chia'          => 'chia seeds',
            'graines de lin'           => 'flax seeds',
            'graines de tournesol'     => 'sunflower seeds',
            'graines de courge'        => 'pumpkin seeds',
            'tahini'                   => 'tahini',
            // --- Oeufs ---
            'oeuf a la coque'          => 'soft boiled egg',
            'oeuf dur'                 => 'hard boiled egg',
            'oeuf au plat'             => 'fried egg',
            'oeufs brouilles'          => 'scrambled eggs',
            'oeufs'                    => 'egg',
            'oeuf'                     => 'egg',
            'omelette'                 => 'omelette',
            // --- Sucré / dessert ---
            'chocolat noir'            => 'dark chocolate',
            'chocolat au lait'         => 'milk chocolate',
            'chocolat blanc'           => 'white chocolate',
            'chocolat'                 => 'chocolate',
            'sucre glace'              => 'powdered sugar',
            'sucre roux'               => 'brown sugar',
            'sucre'                    => 'sugar',
            'miel'                     => 'honey',
            'confiture'                => 'jam',
            'pate a tartiner'          => 'hazelnut spread',
            'nutella'                  => 'nutella',
            'gateau'                   => 'cake',
            'tarte aux pommes'         => 'apple pie',
            'tarte'                    => 'pie',
            'biscuit'                  => 'cookie',
            'cookies'                  => 'cookie',
            'crepe'                    => 'crepe',
            'gaufre'                   => 'waffle',
            'glace'                    => 'ice cream',
            'sorbet'                   => 'sorbet',
            'yaourt glace'             => 'frozen yogurt',
            'tiramisu'                 => 'tiramisu',
            'eclair'                   => 'eclair',
            'macaron'                  => 'macaron',
            // --- Boissons ---
            'jus d orange'             => 'orange juice',
            'jus dorange'              => 'orange juice',
            'jus de pomme'             => 'apple juice',
            'jus de raisin'            => 'grape juice',
            'eau'                      => 'water',
            'cafe'                     => 'coffee',
            'the vert'                 => 'green tea',
            'the'                      => 'tea',
            'lait de soja'             => 'soy milk',
            'lait d amande'            => 'almond milk',
            'lait damande'             => 'almond milk',
            'lait de coco'             => 'coconut milk',
            'lait d avoine'            => 'oat milk',
            'biere'                    => 'beer',
            'vin rouge'                => 'red wine',
            'vin blanc'                => 'white wine',
            'vin'                      => 'wine',
            // --- Condiments / matières grasses ---
            'huile d olive'            => 'olive oil',
            'huile dolive'             => 'olive oil',
            'huile de colza'           => 'canola oil',
            'huile de coco'            => 'coconut oil',
            'huile de tournesol'       => 'sunflower oil',
            'huile'                    => 'oil',
            'vinaigre balsamique'      => 'balsamic vinegar',
            'vinaigre'                 => 'vinegar',
            'moutarde'                 => 'mustard',
            'mayonnaise'               => 'mayonnaise',
            'ketchup'                  => 'ketchup',
            'sauce tomate'             => 'tomato sauce',
            'sauce soja'               => 'soy sauce',
            'sel'                      => 'salt',
            'poivre'                   => 'pepper',
            'persil'                   => 'parsley',
            'basilic'                  => 'basil',
            'coriandre'                => 'coriander',
            'thym'                     => 'thyme',
            'romarin'                  => 'rosemary',
            'origan'                   => 'oregano',
            'gingembre'                => 'ginger',
            'curcuma'                  => 'turmeric',
            'cannelle'                 => 'cinnamon',
            'paprika'                  => 'paprika',
            'cumin'                    => 'cumin',
            // --- Plats / préparations ---
            'soupe de legumes'         => 'vegetable soup',
            'soupe'                    => 'soup',
            'potage'                   => 'vegetable soup',
            'quiche lorraine'          => 'quiche lorraine',
            'quiche'                   => 'quiche',
            'tabboule'                 => 'tabbouleh',
            'tabboule'                 => 'tabbouleh',
            'ratatouille'              => 'ratatouille',
            'gratin dauphinois'        => 'potato gratin',
            'gratin'                   => 'gratin',
            'lasagnes'                 => 'lasagna',
            'lasagne'                  => 'lasagna',
            'raviolis'                 => 'ravioli',
            'ravioli'                  => 'ravioli',
            'pizza margherita'         => 'pizza margherita',
            'pizza'                    => 'pizza',
            'burger'                   => 'burger',
            'sandwich'                 => 'sandwich',
            'wrap'                     => 'wrap',
            'crepes'                   => 'crepe',
            'wok de legumes'           => 'stir fry vegetables',
            'riz cantonnais'           => 'fried rice',
            'pad thai'                 => 'pad thai',
            'sushi'                    => 'sushi',
        ];

        $input = $this->removeAccents(mb_strtolower(trim($term), 'UTF-8'));

        // Trier les clés par longueur décroissante pour matcher "patate douce" avant "patate"
        $keys = array_keys($dict);
        usort($keys, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        // Cherche la correspondance exacte
        if (isset($dict[$input])) {
            return $dict[$input];
        }

        // Cherche si le terme commence par une clé connue (ex: "poulet grille" → "chicken grille")
        foreach ($keys as $fr) {
            if ($input === $fr) {
                return $dict[$fr];
            }
            if (str_starts_with($input, $fr . ' ') || str_starts_with($input, $fr . 's ')) {
                $rest = trim(mb_substr($input, mb_strlen($fr)));
                return $dict[$fr] . ($rest ? ' ' . $rest : '');
            }
        }

        return $term; // Retourne le terme original si aucune traduction
    }

    private function searchCalorieNinjas(string $term): array
    {
        // ── 1. Génération de la requête CalorieNinjas ─────────────────────
        // Priorité : variantes spécifiques générées par IA (multi-résultats)
        // Fallback 1 : traduction simple IA  |  Fallback 2 : dict statique
        $queryTerm = $this->aiTranslation->generateVariationsQuery($term);

        if (empty($queryTerm)) {
            // IA indisponible ou réponse vide → traduction simple
            $queryTerm = $this->aiTranslation->translateQueryToEn($term);
            if ($queryTerm === $term) {
                // Aucune traduction IA → dict statique
                $dictTerm = $this->translateToEn($term);
                if ($dictTerm !== $term) {
                    $queryTerm = $dictTerm;
                }
            }
        }

        try {
            $response = $this->httpClient->request('GET', self::CALORIENINJAS_URL, [
                'headers' => ['X-Api-Key' => $this->calorieNinjasKey],
                'query'   => ['query' => $queryTerm],
                'timeout' => 6,
            ]);

            $data  = $response->toArray(false);
            $items = $data['items'] ?? [];

            if (empty($items)) {
                return [];
            }

            // ── 2. Séparer résultats déjà en cache (par nomApi) ───────────
            $toTranslate = [];
            $cachedByEn  = [];

            foreach ($items as $item) {
                $nomEn = $item['name'] ?? '';
                if (empty($nomEn)) {
                    continue;
                }
                $cached = $this->alimentRepo->findByNomApi($nomEn);
                if ($cached) {
                    $cachedByEn[$nomEn] = $cached;
                } else {
                    $toTranslate[] = $nomEn;
                }
            }

            // ── 3. Traduction EN → FR (batch IA) ──────────────────────────
            //    Les valeurs nutritionnelles viennent EXCLUSIVEMENT de CalorieNinjas.
            //    L'IA ne fait que traduire/reformuler les noms.
            $translations = !empty($toTranslate)
                ? $this->aiTranslation->translateNamesToFr($toTranslate)
                : [];

            // ── 4. Construire et persister les aliments ────────────────────
            $results = [];

            foreach ($items as $item) {
                $nomEn = $item['name'] ?? '';
                if (empty($nomEn)) {
                    continue;
                }

                // Déjà en cache → retour direct, pas de persistence
                if (isset($cachedByEn[$nomEn])) {
                    $results[] = $cachedByEn[$nomEn];
                    if (count($results) >= 10) {
                        break;
                    }
                    continue;
                }

                // CalorieNinjas retourne les valeurs pour serving_size_g grammes → normalise /100g
                $servingG = (float) ($item['serving_size_g'] ?? 100);
                $factor   = $servingG > 0 ? 100 / $servingG : 1;

                $nomFr     = $translations[$nomEn] ?? ucfirst($nomEn);
                $nomFrNorm = $this->removeAccents(mb_strtolower($nomFr, 'UTF-8'));

                // Éviter les doublons par nom FR
                $existing = $this->alimentRepo->searchByName($nomFr)[0] ?? null;
                if ($existing && str_starts_with(
                    $this->removeAccents(mb_strtolower($existing->getNom(), 'UTF-8')),
                    $nomFrNorm
                )) {
                    // Compléter le nomApi si l'entrée existante en est dépourvue
                    if (!$existing->getNomApi()) {
                        $existing->setNomApi($nomEn);
                    }
                    $results[] = $existing;
                } else {
                    $aliment = new Aliment();
                    $aliment->setNom($nomFr);
                    $aliment->setNomApi($nomEn);
                    // Valeurs nutritionnelles exactes CalorieNinjas — l'IA n'y touche pas
                    $aliment->setCalories100g((string) round(($item['calories'] ?? 0) * $factor, 2));
                    $aliment->setProteines100g((string) round(($item['protein_g'] ?? 0) * $factor, 2));
                    $aliment->setGlucides100g((string) round(($item['carbohydrates_total_g'] ?? 0) * $factor, 2));
                    $aliment->setLipides100g((string) round(($item['fat_total_g'] ?? 0) * $factor, 2));
                    $aliment->setFibres100g(isset($item['fiber_g'])
                        ? (string) round($item['fiber_g'] * $factor, 2)
                        : null);
                    $this->em->persist($aliment);
                    $results[] = $aliment;
                }

                if (count($results) >= 10) {
                    break;
                }
            }

            if (!empty($results)) {
                $this->em->flush();
            }

            return $results;

        } catch (\Throwable) {
            return [];
        }
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
