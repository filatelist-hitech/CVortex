<?php

namespace App\AI;

use App\AI\Data\RuntimeSkillDefinition;

class RuntimeSkillRegistry
{
    public function careerFactExtraction(): RuntimeSkillDefinition
    {
        return $this->load('career-fact-extraction/v1', 'career.fact-extraction');
    }

    public function vacancyRequirementExtraction(): RuntimeSkillDefinition
    {
        return $this->load('vacancy-requirement-extraction/v1', 'vacancy.requirement-extraction');
    }

    public function applicationDraftGeneration(): RuntimeSkillDefinition
    {
        return $this->load('application-draft-generation/v1', 'application.draft-generation');
    }

    public function applicationTruthReview(): RuntimeSkillDefinition
    {
        return $this->load('application-truth-review/v3', 'application.truth-review');
    }

    private function load(string $relativeDirectory, string $expectedId): RuntimeSkillDefinition
    {
        $directory = rtrim((string) config('ai.asset_root'), '/').'/skills/'.$relativeDirectory;
        $manifest = $this->json($directory.'/skill.json');
        $schema = $this->json($directory.'/output.schema.json');
        $prompt = file_get_contents($directory.'/system.prompt.md');
        if ($prompt === false
            || ($manifest['id'] ?? null) !== $expectedId
            || ! is_string($manifest['version'] ?? null)
            || ! is_string($manifest['prompt_version'] ?? null)
            || ! is_string($manifest['default_model_policy'] ?? null)) {
            throw new \RuntimeException('The runtime Skill assets are invalid.');
        }

        return new RuntimeSkillDefinition(
            $manifest['id'],
            $manifest['version'],
            $manifest['prompt_version'],
            $manifest['default_model_policy'],
            $prompt,
            $schema,
        );
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('A required runtime Skill asset is unavailable.');
        }
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('A runtime Skill JSON asset must be an object.');
        }

        return $decoded;
    }
}
