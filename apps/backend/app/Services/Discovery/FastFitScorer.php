<?php

namespace App\Services\Discovery;

use App\Models\CareerFact;
use App\Models\DiscoveredVacancy;
use App\Models\User;
use App\Services\TrustedCareerQuery;

class FastFitScorer
{
    private const BOUNDARY_LEFT = '(?<=^|[\s,.;:!?\(\)\[\]\/\-\_])';

    private const BOUNDARY_RIGHT = '(?=$|[\s,.;:!?\(\)\[\]\/\-\_])';

    /** @var array<string, list<string>> */
    private array $candidateFactsCache = [];

    /** @var array<string, array{skills: list<string>, normalized: list<string>}> */
    private array $candidateKeywordsCache = [];

    public function __construct(
        private readonly ?TrustedCareerQuery $trustedCareerQuery = null,
    ) {}

    public function clearCache(?string $userId = null): void
    {
        if ($userId !== null) {
            unset($this->candidateFactsCache[$userId], $this->candidateKeywordsCache[$userId]);
        } else {
            $this->candidateFactsCache = [];
            $this->candidateKeywordsCache = [];
        }
    }

    /**
     * @return array{
     *     score: int,
     *     level: string,
     *     reasons: array<string, mixed>
     * }
     */
    public function score(User $user, string $title, ?string $description = null): array
    {
        $userId = (string) $user->id;

        if (! isset($this->candidateFactsCache[$userId])) {
            $this->candidateFactsCache[$userId] = $this->loadUserCandidateFacts($user);
        }

        $facts = $this->candidateFactsCache[$userId];

        if (empty($facts)) {
            return [
                'score' => 50,
                'level' => DiscoveredVacancy::FIT_MEDIUM,
                'reasons' => [
                    'matched_skills' => [],
                    'missing_skills' => [],
                    'title_match' => false,
                    'summary' => 'Базовый скор: в профиле пока нет подтверждённых карьерных фактов.',
                ],
            ];
        }

        if (! isset($this->candidateKeywordsCache[$userId])) {
            $this->candidateKeywordsCache[$userId] = $this->extractCandidateKeywords($facts);
        }

        $userKeywords = $this->candidateKeywordsCache[$userId];
        $searchableText = mb_strtolower($title.' '.($description ?? ''));

        // Check title match
        $titleLower = mb_strtolower($title);
        $titleMatch = false;

        $roleTerms = [
            'backend', 'frontend', 'fullstack', 'full-stack', 'lead', 'senior', 'staff',
            'principal', 'architect', 'developer', 'engineer', 'devops', 'techlead',
            'teamlead', 'cto', 'qa', 'data engineer', 'php', 'python', 'go', 'golang',
            'react', 'vue', 'laravel', 'node', 'typescript', 'java', 'c#', 'c++', 'rust',
        ];

        foreach ($roleTerms as $role) {
            if (str_contains($titleLower, $role) && in_array($role, $userKeywords['normalized'], true)) {
                $titleMatch = true;
            }
        }

        // Match skills
        $matchedSkills = [];
        foreach ($userKeywords['skills'] as $skill) {
            $skillLower = mb_strtolower($skill);
            $pattern = '/'.self::BOUNDARY_LEFT.preg_quote($skillLower, '/').self::BOUNDARY_RIGHT.'/iu';
            if (preg_match($pattern, $searchableText) === 1) {
                $matchedSkills[] = $skill;
            }
        }

        $matchedSkills = array_values(array_unique($matchedSkills));

        // Detect missing common technologies that vacancy explicitly mentions but candidate doesn't have
        $commonTechStack = [
            'Docker', 'Kubernetes', 'PostgreSQL', 'MySQL', 'Redis', 'Kafka', 'RabbitMQ',
            'Elasticsearch', 'GraphQL', 'AWS', 'GCP', 'Linux', 'CI/CD', 'GitLab',
            'FastAPI', 'Django', 'Spring', 'NestJS', 'Next.js', 'Vue', 'React',
            'TypeScript', 'PHP', 'Laravel', 'Go', 'Golang', 'Python', 'C#', 'C++', '.NET',
        ];

        $missingSkills = [];
        foreach ($commonTechStack as $tech) {
            $techLower = mb_strtolower($tech);
            $pattern = '/'.self::BOUNDARY_LEFT.preg_quote($techLower, '/').self::BOUNDARY_RIGHT.'/iu';
            if (preg_match($pattern, $searchableText) === 1) {
                if (! in_array($techLower, $userKeywords['normalized'], true)) {
                    $missingSkills[] = $tech;
                }
            }
        }

        // Calculate score
        $score = 25; // baseline

        if ($titleMatch) {
            $score += 35;
        }

        $skillPoints = min(40, count($matchedSkills) * 10);
        $score += $skillPoints;

        // Mild penalty for missing requirements
        if (count($missingSkills) > 2) {
            $score -= min(15, (count($missingSkills) - 2) * 3);
        }

        $score = max(5, min(98, $score));

        $level = match (true) {
            $score >= 70 => DiscoveredVacancy::FIT_HIGH,
            $score >= 40 => DiscoveredVacancy::FIT_MEDIUM,
            default => DiscoveredVacancy::FIT_LOW,
        };

        $summaryParts = [];
        if ($titleMatch) {
            $summaryParts[] = 'Совпадение по профилю роли';
        }
        if (! empty($matchedSkills)) {
            $summaryParts[] = 'Стек: '.implode(', ', array_slice($matchedSkills, 0, 4));
        }
        if (empty($summaryParts)) {
            $summaryParts[] = 'Частичное совпадение с общим описанием';
        }

        return [
            'score' => $score,
            'level' => $level,
            'reasons' => [
                'matched_skills' => $matchedSkills,
                'missing_skills' => array_values(array_unique($missingSkills)),
                'title_match' => $titleMatch,
                'summary' => implode(' | ', $summaryParts),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function loadUserCandidateFacts(User $user): array
    {
        if ($this->trustedCareerQuery !== null) {
            $trusted = $this->trustedCareerQuery->forMatching($user);
            $strings = [];
            foreach ($trusted['facts'] as $fact) {
                if ($fact->polarity !== 'negative') {
                    $strings[] = $fact->approvedAssertion();
                }
            }

            return $strings;
        }

        return CareerFact::query()
            ->where('owner_id', $user->id)
            ->where('status', CareerFact::STATUS_CONFIRMED)
            ->where('polarity', '!=', 'negative')
            ->pluck('assertion_approved')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $facts
     * @return array{skills: list<string>, normalized: list<string>}
     */
    private function extractCandidateKeywords(array $facts): array
    {
        $allText = implode(' ', $facts);
        $detectedSkills = [];
        $normalized = [];

        $dictionary = [
            'PHP', 'Laravel', 'Symfony', 'PostgreSQL', 'MySQL', 'Redis', 'Docker',
            'Kubernetes', 'Linux', 'Nginx', 'React', 'Vue', 'Next.js', 'TypeScript',
            'JavaScript', 'Python', 'Go', 'Golang', 'Kafka', 'RabbitMQ', 'Elasticsearch',
            'AWS', 'CI/CD', 'GitLab', 'REST', 'GraphQL', 'Microservices', 'Clean Architecture',
            'Domain Driven Design', 'Backend', 'Frontend', 'Fullstack', 'Lead', 'Senior',
            'Teamlead', 'Techlead', 'Staff', 'Architect', 'Developer', 'Engineer',
            'C#', 'C++', '.NET', 'QA',
        ];

        foreach ($dictionary as $word) {
            $lower = mb_strtolower($word);
            $pattern = '/'.self::BOUNDARY_LEFT.preg_quote($lower, '/').self::BOUNDARY_RIGHT.'/iu';
            if (preg_match($pattern, $allText) === 1) {
                $detectedSkills[] = $word;
                $normalized[] = $lower;
            }
        }

        // Also normalize raw words from facts (length >= 2 to include C#, Go, QA, etc.)
        preg_match_all('/[a-zA-Zа-яА-Я0-9\+\#\.\-]{2,}/u', $allText, $words);
        foreach ($words[0] as $w) {
            $normalized[] = mb_strtolower($w);
        }

        return [
            'skills' => array_values(array_unique($detectedSkills)),
            'normalized' => array_values(array_unique($normalized)),
        ];
    }
}
