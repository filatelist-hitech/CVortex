<?php

declare(strict_types=1);

namespace App\Services\PostingGuard;

class PostingGuardService
{
    /**
     * Invisible characters: Zero-width, Bidi overrides, formatting separators, BOM, and Unicode tag block.
     */
    private const INVISIBLE_PATTERN = '/[\x{200B}-\x{200D}\x{2060}-\x{2064}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{00AD}\x{2028}\x{2029}\x{FEFF}\x{E0000}-\x{E007F}]/u';

    /**
     * Blocks to completely drop including content (scripts, styles, svg, templates, noscript).
     */
    private const DROP_BLOCKS_PATTERN = '/<(?:script|style|noscript|svg|template)\b[^>]*>.*?<\/(?:script|style|noscript|svg|template)\s*>/is';

    /**
     * Hidden elements via inline CSS or HTML attributes.
     */
    private const HIDDEN_ELEMENT_PATTERN = '/<([a-zA-Z][\w:-]*)\b[^>]*(?:style\s*=\s*["\'][^"\']*(?:display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0(?![.\d]*[1-9])|font-size\s*:\s*0|color\s*:\s*transparent|text-indent\s*:\s*-\d{4,}px|clip\s*:\s*rect\(0|clip-path\s*:\s*inset\(100%)[^"\']*["\']|\bhidden\b|aria-hidden\s*=\s*["\']?true["\']?)[^>]*>(.*?)<\/\1\s*>/is';

    /**
     * Prompt injection and instruction-shaped patterns.
     */
    private const INJECTION_PATTERNS = [
        'ignore_instructions' => '/\bignore\s+(?:all|any|the|previous|prior|above|earlier|system)\b/i',
        'disregard_prompts' => '/\b(?:disregard|forget)(?:\s+(?:all|any|the|your|my))?\s+(?:previous|prior|above|earlier|preceding|initial|original|system)\s+(?:instructions?|prompts?)\b/i',
        'identity_override' => '/\byou\s+are\s+(?:an?|the)\s+(?:ai|assistant|llm|language\s+model)\b/i',
        'system_prompt' => '/\bsystem\s+prompt\b/i',
        'jailbreak' => '/\b(?:jailbreak|dan\s+mode|developer\s+mode)\b/i',
        'exfiltration_block' => '/\bdo\s+not\s+(?:reveal|output|display|mention)\b/i',
        'hidden_instruction' => '/\b(?:important|hidden|override)\s+instruction\s*:/i',
    ];

    /**
     * Scam & upfront payment indicator patterns.
     */
    private const SCAM_PATTERNS = [
        'upfront_payment' => '/(?:send|pay|transfer|deposit)\s+(?:money|\$|€|£|usdt|btc|crypto|rub|fee|deposit|funds)\b/i',
        'russian_deposit' => '/(?:оплатите|переведите|взнос|залог|страховой\s+депозит|комиссия\s+за\s+оформление)/ui',
        'unverified_telegram_recruiter' => '/(?:напишите\s+в\s+телеграм|пишите\s+менеджеру\s+в\s+тг|обращаться\s+в\s+tg)\s*:?\s*@[\w]+/ui',
    ];

    /**
     * Look-alike script spoofing (Latin letters mixed with Cyrillic inside a single token).
     */
    private const MIXED_SCRIPT_PATTERN = '/\b(?=[a-zA-Z]*[\x{0400}-\x{04FF}])(?=[\x{0400}-\x{04FF}]*[a-zA-Z])[a-zA-Z\x{0400}-\x{04FF}]{3,}\b/u';

    public function inspect(string $rawText): PostingGuardResult
    {
        $findings = [
            'stripped_invisible_count' => 0,
            'stripped_html_blocks' => 0,
            'injections' => [],
            'scams' => [],
            'mixed_script_tokens' => [],
        ];

        // 1. Count and strip invisible / steganographic characters
        $invisibleCount = 0;
        $strippedText = (string) preg_replace_callback(self::INVISIBLE_PATTERN, function () use (&$invisibleCount): string {
            $invisibleCount++;

            return '';
        }, $rawText);
        $findings['stripped_invisible_count'] = $invisibleCount;

        // 2. Drop script, style, and comments
        $strippedText = (string) preg_replace('/<!--.*?-->/s', '', $strippedText);
        $strippedText = (string) preg_replace(self::DROP_BLOCKS_PATTERN, '', $strippedText);

        // 3. Drop hidden HTML elements
        $hiddenBlocksCount = 0;
        $strippedText = (string) preg_replace_callback(self::HIDDEN_ELEMENT_PATTERN, function () use (&$hiddenBlocksCount): string {
            $hiddenBlocksCount++;

            return '';
        }, $strippedText);
        $findings['stripped_html_blocks'] = $hiddenBlocksCount;

        // 4. Strip remaining HTML tags converting line breaks
        $strippedText = (string) preg_replace('/<(?:br|p|div|li|tr|h[1-6])\b[^>]*>/i', "\n", $strippedText);
        $cleanText = strip_tags($strippedText);
        $cleanText = html_entity_decode($cleanText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespaces and excess newlines
        $cleanText = (string) preg_replace("/[ \t]+/", ' ', $cleanText);
        $cleanText = (string) preg_replace("/\n{3,}/", "\n\n", $cleanText);
        $cleanText = trim($cleanText);

        // 5. Scan for prompt injection patterns
        foreach (self::INJECTION_PATTERNS as $label => $pattern) {
            if (preg_match_all($pattern, $cleanText, $matches)) {
                $findings['injections'][$label] = array_unique($matches[0]);
            }
        }

        // 6. Scan for scam patterns
        foreach (self::SCAM_PATTERNS as $label => $pattern) {
            if (preg_match_all($pattern, $cleanText, $matches)) {
                $findings['scams'][$label] = array_unique($matches[0]);
            }
        }

        // 7. Scan for mixed-script look-alike spoofing
        if (preg_match_all(self::MIXED_SCRIPT_PATTERN, $cleanText, $matches)) {
            $findings['mixed_script_tokens'] = array_slice(array_unique($matches[0]), 0, 10);
        }

        // 8. Determine Threat Level
        $threatLevel = PostingGuardResult::THREAT_CLEAN;
        $isSafeForLlm = true;
        $summaryParts = [];

        if (! empty($findings['injections'])) {
            $threatLevel = PostingGuardResult::THREAT_HIGH_RISK;
            $isSafeForLlm = false;
            $summaryParts[] = 'Обнаружены попытки внедрения скрытых инструкций (Prompt Injection): '.implode(', ', array_keys($findings['injections']));
        }

        if (! empty($findings['scams'])) {
            if ($threatLevel !== PostingGuardResult::THREAT_HIGH_RISK) {
                $threatLevel = PostingGuardResult::THREAT_SUSPICIOUS;
            }
            $summaryParts[] = 'Выявлены признаки неблагонадёжной вакансии (залог/оплата/неофициальные каналы): '.implode(', ', array_keys($findings['scams']));
        }

        if ($invisibleCount > 0) {
            if ($threatLevel === PostingGuardResult::THREAT_CLEAN) {
                $threatLevel = PostingGuardResult::THREAT_SUSPICIOUS;
            }
            $summaryParts[] = "Удалено невидимых символов и стеганографии: {$invisibleCount}";
        }

        if ($hiddenBlocksCount > 0) {
            if ($threatLevel === PostingGuardResult::THREAT_CLEAN) {
                $threatLevel = PostingGuardResult::THREAT_SUSPICIOUS;
            }
            $summaryParts[] = "Вырезано скрытых HTML-блоков: {$hiddenBlocksCount}";
        }

        if (! empty($findings['mixed_script_tokens'])) {
            if ($threatLevel === PostingGuardResult::THREAT_CLEAN) {
                $threatLevel = PostingGuardResult::THREAT_SUSPICIOUS;
            }
            $summaryParts[] = 'Обнаружены поддельные токены со смешанной раскладкой (Look-alike): '.implode(', ', $findings['mixed_script_tokens']);
        }

        $threatSummary = ! empty($summaryParts) ? implode('; ', $summaryParts) : null;

        return new PostingGuardResult(
            cleanText: $cleanText,
            threatLevel: $threatLevel,
            findings: $findings,
            isSafeForLlm: $isSafeForLlm,
            threatSummary: $threatSummary,
        );
    }
}
