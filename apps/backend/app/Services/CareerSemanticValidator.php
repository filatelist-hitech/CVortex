<?php

namespace App\Services;

use App\Models\CareerFactType;

class CareerSemanticValidator
{
    public function supports(CareerFactType $type, string $assertion): bool
    {
        $text = mb_strtolower($assertion);

        return match ($type) {
            CareerFactType::EXPERIENCE => ! $this->matches($text, [
                '/\bfamiliar(?:ity)?\b/u', '/\bbasic knowledge\b/u', '/\baware(?:ness)?\b/u',
                '/\bmentioned?\b/u', '/знаком(?:ство|а|ы)?/u', '/базов(?:ые|ый|ая) знани/u',
            ]),
            CareerFactType::LEADERSHIP => $this->matches($text, [
                '/^(?:i\s+)?(?:led|managed|headed)(?!\s+by\b)\b/u', '/\bi\s+(?:led|managed|headed)\b/u',
                '/\bserved\s+as\s+(?:a\s+)?(?:team\s+)?lead\b/u', '/^(?:руководил|возглавил)\b/u',
                '/\bя\s+(?:руководил|возглавил)\b/u',
            ]),
            CareerFactType::RESPONSIBILITY => $this->matches($text, [
                '/^(?:i\s+(?:was\s+)?)?responsible\s+for\b/u', '/\bi\s+(?:was\s+)?responsible\s+for\b/u',
                '/^(?:i\s+)?owned(?!\s+by\b)\b/u', '/\bi\s+owned\b/u',
                '/^(?:i\s+(?:was\s+)?)?accountable\s+for\b/u', '/\bi\s+(?:was\s+)?accountable\s+for\b/u',
                '/^(?:я\s+)?отвечал\b/u', '/\bя\s+нес\s+ответственность\b/u',
            ]),
            CareerFactType::EMPLOYMENT_PERIOD => $this->hasDateCue($text) && $this->matches($text, [
                '/\b(?:worked|employed|joined|position|role)\b/u', '/\bworked\s+at\b/u',
                '/\b(?:работал|работала|трудоустроен|должност|позици)\w*/u',
            ]),
            CareerFactType::SENIORITY => $this->matches($text, [
                '/^(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/\b(?:worked|served)\s+as\s+(?:an?\s+)?(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/\b(?:my|current)\s+(?:role|title|level)\s+(?:was|is)\s+(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/^(?:стаж[её]р|джун|мидл|сеньор|ведущ(?:ий|ая))\b/u',
                '/\bработал(?:а)?\s+(?:как|в\s+роли)\s+(?:стаж[её]ра|джуна|мидла|сеньора|ведущ)/u',
            ]),
            CareerFactType::TECHNOLOGY_DEPTH => $this->matches($text, [
                '/\badvanced\s+(?:knowledge|skill|skills|experience)\b/u',
                '/\bexpert\s+(?:in|with)\b/u', '/\bdeep\s+(?:knowledge|expertise|experience)\b/u',
                '/\bproficient\s+(?:in|with)\b/u', '/\bspecialist\s+in\b/u',
                '/\b(?:эксперт\s+в|продвинут\w*\s+(?:знани|навык)|глубок\w*\s+(?:знани|экспертиз))\w*/u',
            ]),
            default => true,
        };
    }

    /** @param list<string> $patterns */
    private function matches(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasDateCue(string $value): bool
    {
        return $this->matches($value, [
            '/\b(?:19|20)\d{2}\b/u', '/\b\d{1,2}[\.\/-]\d{4}\b/u',
            '/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/u',
            '/\b(?:январ|феврал|март|апрел|ма[йя]|июн|июл|август|сентябр|октябр|ноябр|декабр)/u',
        ]);
    }
}
