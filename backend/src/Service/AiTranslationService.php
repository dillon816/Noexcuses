<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Passerelle IA (Mistral) pour la traduction alimentaire FR ↔ EN.
 *
 * - generateVariationsQuery() : "poulet" → "chicken breast chicken thigh ..."
 * - translateQueryToEn()      : "poulet rôti" → "roasted chicken"
 * - translateNamesToFr()      : ["chicken breast"] → ["Blanc de poulet"]
 *
 * Si la clé Mistral est absente ou si l'API échoue, les méthodes retournent
 * le terme original (fallback transparent) et loguent un warning.
 */
class AiTranslationService
{
    private const MISTRAL_URL = 'https://api.mistral.ai/v1/chat/completions';
    private const MODEL       = 'mistral-small-latest';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
        private readonly string              $mistralKey,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // API publique
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Traduit un terme alimentaire français vers l'anglais pour CalorieNinjas.
     * Retourne le terme original si l'IA est indisponible.
     */
    public function translateQueryToEn(string $frTerm): string
    {
        if (!$this->hasKey()) {
            return $frTerm;
        }

        try {
            $response = $this->httpClient->request('POST', self::MISTRAL_URL, [
                'headers' => $this->headers(),
                'json'    => [
                    'model'       => self::MODEL,
                    'temperature' => 0.1,
                    'max_tokens'  => 15,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You translate French food or ingredient names to English for a nutrition API query. '
                                       . 'Reply with ONLY the English term, no explanation, no punctuation, no quotes.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $frTerm,
                        ],
                    ],
                ],
                'timeout' => 5,
            ]);

            $data        = $response->toArray(false);
            $translation = trim($data['choices'][0]['message']['content'] ?? '');

            if (!empty($translation)) {
                $result = mb_strtolower($translation, 'UTF-8');
                $this->logger->info('[Mistral] translateQueryToEn', ['fr' => $frTerm, 'en' => $result]);
                return $result;
            }

            $this->logger->warning('[Mistral] translateQueryToEn réponse vide', ['fr' => $frTerm]);
            return $frTerm;

        } catch (\Throwable $e) {
            $this->logger->error('[Mistral] translateQueryToEn ERREUR — fallback term original', [
                'fr'    => $frTerm,
                'error' => $e->getMessage(),
            ]);
            return $frTerm;
        }
    }

    /**
     * Génère une requête CalorieNinjas multi-termes avec 5-6 variantes spécifiques.
     *
     * Exemple : "poulet" → "chicken breast chicken thigh chicken drumstick roasted chicken grilled chicken"
     * CalorieNinjas parse ce texte et retourne un item par variante.
     *
     * Retourne une chaîne vide si l'IA est indisponible (fallback sur translateQueryToEn).
     */
    public function generateVariationsQuery(string $frTerm): string
    {
        if (!$this->hasKey()) {
            return '';
        }

        try {
            $response = $this->httpClient->request('POST', self::MISTRAL_URL, [
                'headers' => $this->headers(),
                'json'    => [
                    'model'       => self::MODEL,
                    'temperature' => 0.3,
                    'max_tokens'  => 60,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You generate CalorieNinjas API query strings. '
                                       . 'Given a French food name, output a single line with 5 to 6 specific English food '
                                       . 'variations separated by spaces, ready to be used as a CalorieNinjas query. '
                                       . 'Example for "poulet": "chicken breast chicken thigh chicken drumstick roasted chicken grilled chicken". '
                                       . 'Example for "pâtes": "pasta spaghetti penne fettuccine whole wheat pasta". '
                                       . 'Output ONLY the query string, nothing else, no punctuation, no numbering.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $frTerm,
                        ],
                    ],
                ],
                'timeout' => 6,
            ]);

            $data  = $response->toArray(false);
            $query = trim($data['choices'][0]['message']['content'] ?? '');

            // Sécurité : rejeter les réponses trop longues ou mal formées
            if (empty($query) || mb_strlen($query) > 200 || str_contains($query, "\n")) {
                $this->logger->warning('[Mistral] generateVariationsQuery réponse invalide', [
                    'fr'    => $frTerm,
                    'query' => $query,
                ]);
                return '';
            }

            $result = mb_strtolower($query, 'UTF-8');
            $this->logger->info('[Mistral] generateVariationsQuery OK', ['fr' => $frTerm, 'query' => $result]);
            return $result;

        } catch (\Throwable $e) {
            $this->logger->error('[Mistral] generateVariationsQuery ERREUR — fallback traduction simple', [
                'fr'    => $frTerm,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Traduit une liste de noms d'aliments anglais vers le français (appel batch).
     *
     * @param  string[] $englishNames  ex: ["chicken breast", "chicken thigh"]
     * @return array<string,string>    ex: ["chicken breast" => "Blanc de poulet", ...]
     */
    public function translateNamesToFr(array $englishNames): array
    {
        if (!$this->hasKey() || empty($englishNames)) {
            return $this->fallbackMap($englishNames);
        }

        try {
            $numbered = implode("\n", array_map(
                static fn(int $i, string $n) => "$i. $n",
                range(1, count($englishNames)),
                $englishNames,
            ));

            $response = $this->httpClient->request('POST', self::MISTRAL_URL, [
                'headers' => $this->headers(),
                'json'    => [
                    'model'       => self::MODEL,
                    'temperature' => 0.1,
                    'max_tokens'  => 400,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You translate English food names to natural French consumer names. '
                                       . 'Reply with a numbered list matching exactly the input order. '
                                       . 'Format strictly: "1. Nom français". Only the list, nothing else. '
                                       . 'Use common French names (e.g. "chicken breast" → "Blanc de poulet", '
                                       . '"sweet potato" → "Patate douce", "oatmeal" → "Flocons d\'avoine").',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $numbered,
                        ],
                    ],
                ],
                'timeout' => 10,
            ]);

            $data    = $response->toArray(false);
            $content = trim($data['choices'][0]['message']['content'] ?? '');
            $lines   = array_filter(explode("\n", $content));

            $result = [];
            foreach ($englishNames as $idx => $enName) {
                $lineNum = $idx + 1;
                $frName  = null;
                foreach ($lines as $line) {
                    // Accepte "1. Nom" ou "1) Nom"
                    if (preg_match('/^' . $lineNum . '[.\)]\s*(.+)$/u', trim($line), $m)) {
                        $frName = trim($m[1]);
                        break;
                    }
                }
                $result[$enName] = $frName ?? ucfirst($enName);
            }

            $this->logger->info('[Mistral] translateNamesToFr OK', ['count' => count($result), 'sample' => array_slice($result, 0, 3)]);
            return $result;

        } catch (\Throwable $e) {
            $this->logger->error('[Mistral] translateNamesToFr ERREUR — fallback ucfirst', ['error' => $e->getMessage()]);
            return $this->fallbackMap($englishNames);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    private function hasKey(): bool
    {
        return !empty($this->mistralKey)
            && $this->mistralKey !== 'your_mistral_key_here';
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->mistralKey,
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Fallback : retourne les noms anglais avec ucfirst() comme nom d'affichage.
     *
     * @param  string[]             $names
     * @return array<string,string>
     */
    private function fallbackMap(array $names): array
    {
        return array_combine($names, array_map('ucfirst', $names));
    }
}
