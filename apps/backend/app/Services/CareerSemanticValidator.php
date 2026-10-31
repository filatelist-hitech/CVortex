<?php

namespace App\Services;

use App\Models\CareerFactType;

class CareerSemanticValidator
{
    public function supports(CareerFactType $type, string $assertion): bool
    {
        $text = mb_strtolower($assertion);

        return match ($type) {
            CareerFactType::SKILL => $this->matches($text, [
                '/\b(?:familiar|knowledge|skills?|experience|proficient|proficiency|expert|used|worked|built(?:\s+with)?|tech\s+stack|stack|technolog(?:y|ies)|tools?|developed|developing|development|tested|testing|frameworks?|libraries|databases?)\b/u',
                '/знаком|знаю|знани|навык|стек|технологи|инструмент|опыт|владе|умени|умею|использовал|работал|разработ|тестиров|баз\w*\s+данных|фреймворк|библиотек/u',
            ]),
            CareerFactType::EXPERIENCE => ! $this->matches($text, [
                '/\bfamiliar(?:ity)?\b/u', '/\bbasic knowledge\b/u', '/\baware(?:ness)?\b/u',
                '/\bmentioned?\b/u', '/знаком(?:ство|а|ы)?/u', '/базов(?:ые|ый|ая) знани/u',
            ]),
            CareerFactType::LEADERSHIP => $this->matches($text, [
                '/^(?:i\s+)?(?:led|managed|headed|mentored|supervised|coordinated|directed)(?!\s+by\b)\b/u',
                '/\bi\s+(?:led|managed|headed|mentored|supervised|coordinated|directed)\b/u',
                '/\b(?:leading|managing|mentoring|supervising|coordinating|directing)\s+(?:a\s+)?(?:team|people|group|function|process|department|colleagues|engineers|developers)\b/u',
                '/\bserved\s+as\s+(?:a\s+)?(?:team|tech\s+)?lead\b/u',
                '/\b(?:team|tech)\s+lead\b/u',
                '/^(?:руководил|руководила|руководство|возглавил|возглавила|возглавлял|возглавляла|управлял|управляла|управление|менторил|менторила|менторство|наставничеств|координировал|координировала|координаци)\w*/u',
                '/\bя\s+(?:руководил|руководила|возглавил|возглавила|управлял|управляла|менторил|менторила|координировал|координировала)\b/u',
            ]),
            CareerFactType::RESPONSIBILITY => $this->matches($text, [
                '/^(?:i\s+(?:was\s+)?)?responsible\s+for\b/u', '/\bi\s+(?:was\s+)?responsible\s+for\b/u',
                '/^(?:i\s+)?owned(?!\s+by\b)\b/u', '/\bi\s+owned\b/u',
                '/^(?:i\s+(?:was\s+)?)?accountable\s+for\b/u', '/\bi\s+(?:was\s+)?accountable\s+for\b/u',
                '/\b(?:responsibilit(?:y|ies)|accountabilit(?:y|ies)|duties)\s*:/u',
                '/^(?:maintained|maintaining|designed|designing)\s+/u',
                '/\bi\s+(?:maintained|designed)\b/u',
                '/^(?:я\s+)?отвечал(?:а)?\b/u', '/\bя\s+нес(?:ла)?\s+ответственность\b/u',
                '/\b(?:обязанност\w*|ответственност\w*)\s*:/u',
                '/\bв\s+обязанности\s+входил/u',
                '/\bзона\s+ответственности\b/u',
                '/^(?:поддерживал|поддерживала|проектировал|проектировала|занимался|занималась)\b/u',
            ]),
            CareerFactType::EMPLOYMENT_PERIOD => $this->hasDateCue($text) && $this->matches($text, [
                '/\b(?:worked|employed|employment|joined|position|role|period)\b/u', '/\bworked\s+at\b/u',
                '/\b(?:работал|работала|трудоустроен|должност|позици|период|срок|стаж|опыт)\w*/u',
            ]),
            CareerFactType::SENIORITY => $this->matches($text, [
                '/^(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/\b(?:worked|served)\s+as\s+(?:an?\s+)?(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/\b(?:my|current)\s+(?:role|title|level)\s+(?:was|is)\s+(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/\b(?:role|position|title|level)\s*:\s*(?:intern|junior|middle|mid-level|senior|lead|principal|staff)\b/u',
                '/\b(?:team|tech)\s+lead\b/u',
                '/^(?:стаж[её]р|джун|мидл|сеньор|ведущ(?:ий|ая)|старш(?:ий|ая)|главн(?:ый|ая)|тимлид|техлид)\b/u',
                '/\b(?:должност|позици|роль|уровень)\w*\s*:\s*(?:стаж[её]р|джун|мидл|сеньор|ведущ|старш|главн|лид|тимлид|техлид|junior|middle|senior|lead)/u',
                '/\bработал(?:а)?\s+(?:как|в\s+роли)\s+(?:стаж[её]ра|джуна|мидла|сеньора|ведущ|старш)/u',
            ]),
            CareerFactType::TECHNOLOGY_DEPTH => $this->matches($text, [
                '/\b(?:advanced|deep|expert(?:-level)?|proficient|basic|intermediate)\s+(?:knowledge|expertise|experience|skill|skills|proficiency)\b/u',
                '/\bexpert\s+(?:in|with|at)\b/u', '/\bspecialist\s+in\b/u',
                '/\bdeep\s+practical\s+(?:knowledge|expertise|experience)\b/u',
                '/\b(?:эксперт\s+в|экспертн\w*\s+уровен|продвинут\w*\s+(?:знани|навык|уровен)|глубок\w*(?:\s+практическ\w*)?\s+(?:знани|экспертиз)|базов\w*\s+(?:знани|уровен|владени|навык)|средн\w*\s+уровен|уверенн\w*\s+(?:владени|знани))\w*/u',
            ]),
            // Categories without deterministic semantic checks are unsupported
            // until a validator is added. Never let a syntactically valid but
            // unchecked category promote source text into trusted facts.
            default => false,
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
