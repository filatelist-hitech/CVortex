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
     * Tag token matching pattern with tag name and attributes.
     */
    private const TAG_PATTERN = '/<(\/?)([a-zA-Z][\w:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s';

    /**
     * Attribute parser pattern.
     */
    private const ATTR_PATTERN = '/([^\s=\/"\'>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/';

    /**
     * Void tags that do not require closing tags and cannot contain content.
     */
    private const VOID_TAGS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    /**
     * Block-level tags that imply visual line breaks.
     */
    private const BLOCK_TAGS = [
        'address' => true,
        'article' => true,
        'aside' => true,
        'blockquote' => true,
        'br' => true,
        'dd' => true,
        'div' => true,
        'dl' => true,
        'dt' => true,
        'fieldset' => true,
        'figure' => true,
        'footer' => true,
        'form' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
        'h4' => true,
        'h5' => true,
        'h6' => true,
        'header' => true,
        'hr' => true,
        'legend' => true,
        'li' => true,
        'main' => true,
        'nav' => true,
        'ol' => true,
        'option' => true,
        'p' => true,
        'pre' => true,
        'section' => true,
        'table' => true,
        'tbody' => true,
        'td' => true,
        'th' => true,
        'thead' => true,
        'tr' => true,
        'ul' => true,
    ];

    /**
     * Tags whose open element is implicitly closed by a sibling of the same name.
     */
    private const IMPLIED_END_TAGS = [
        'dd' => true,
        'dt' => true,
        'li' => true,
        'option' => true,
        'p' => true,
        'td' => true,
        'th' => true,
        'tr' => true,
    ];

    /**
     * Block-level tags whose opening implicitly closes an open paragraph tag.
     */
    private const CLOSES_P_TAGS = [
        'address' => true,
        'article' => true,
        'aside' => true,
        'blockquote' => true,
        'dd' => true,
        'div' => true,
        'dl' => true,
        'dt' => true,
        'fieldset' => true,
        'figure' => true,
        'footer' => true,
        'form' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
        'h4' => true,
        'h5' => true,
        'h6' => true,
        'header' => true,
        'hr' => true,
        'legend' => true,
        'li' => true,
        'main' => true,
        'nav' => true,
        'ol' => true,
        'option' => true,
        'p' => true,
        'pre' => true,
        'section' => true,
        'table' => true,
        'tbody' => true,
        'td' => true,
        'th' => true,
        'thead' => true,
        'tr' => true,
        'ul' => true,
    ];

    /**
     * CSS declarations that deterministically hide content.
     */
    private const HIDDEN_STYLE_PATTERN = '/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0(?![.\d]*[1-9])|font-size\s*:\s*0(?:\.0+)?(?:px|pt|em|rem|%)?(?![\d.])|color\s*:\s*transparent|clip\s*:\s*rect\(\s*0|clip-path\s*:\s*inset\(\s*100%|(?:left|top|text-indent)\s*:\s*-\d{4,}px/i';

    /**
     * Collapsed box CSS: zero height or width.
     */
    private const COLLAPSED_BOX_PATTERN = '/\b(?:max-)?(?:height|width)\s*:\s*0(?:\.0+)?(?:px|em|rem|%|pt)?(?![\d.])/i';

    /**
     * Clipped overflow CSS.
     */
    private const CLIPPED_PATTERN = '/\boverflow(?:-[xy])?\s*:\s*hidden\b/i';

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

        // 2. Deterministic stack-based HTML parsing: separates visible content from hidden layers
        [$cleanText, $hiddenText, $hiddenBlocksCount] = $this->cleanHtml($strippedText);
        $findings['stripped_html_blocks'] = $hiddenBlocksCount;

        // 3. Scan BOTH visible and hidden text streams to prevent blindspots
        $scanText = $cleanText."\n".$hiddenText;

        // 4. Scan for prompt injection patterns
        foreach (self::INJECTION_PATTERNS as $label => $pattern) {
            if (preg_match_all($pattern, $scanText, $matches)) {
                $findings['injections'][$label] = array_values(array_unique($matches[0]));
            }
        }

        // 5. Scan for scam patterns
        foreach (self::SCAM_PATTERNS as $label => $pattern) {
            if (preg_match_all($pattern, $scanText, $matches)) {
                $findings['scams'][$label] = array_values(array_unique($matches[0]));
            }
        }

        // 6. Scan for mixed-script look-alike spoofing
        if (preg_match_all(self::MIXED_SCRIPT_PATTERN, $scanText, $matches)) {
            $findings['mixed_script_tokens'] = array_slice(array_unique($matches[0]), 0, 10);
        }

        // 7. Determine Threat Level
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

    /**
     * Stack-based HTML tokenizer: extracts visible clean text and extracts hidden text into a background stream.
     *
     * @return array{0: string, 1: string, 2: int} [cleanText, hiddenText, hiddenBlocksCount]
     */
    private function cleanHtml(string $markup): array
    {
        /** @var list<string> $hiddenParts */
        $hiddenParts = [];
        $hiddenBlocksCount = 0;

        // Strip DOCTYPE and XML preambles
        $markup = (string) preg_replace('/<!DOCTYPE[^>]*>|<\?[^>]*\?>/is', ' ', $markup);

        // Strip HTML comments, collecting any inner text into the hidden stream
        $markup = (string) preg_replace_callback('/<!--.*?(?:-->|\z)/is', function (array $match) use (&$hiddenParts): string {
            $inner = (string) preg_replace('/<!--|-->/', ' ', $match[0]);
            $inner = (string) preg_replace('/<[^>]+>/', ' ', $inner);
            $hiddenParts[] = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return ' ';
        }, $markup);

        // Strip non-content blocks (script, style, noscript, svg, template)
        $markup = (string) preg_replace_callback('/<(script|style|noscript|svg|template)\b[^>]*>.*?(?:<\/\1\s*>|\z)/is', function (array $match) use (&$hiddenParts): string {
            $inner = (string) preg_replace('/<[^>]+>/', ' ', $match[0]);
            $hiddenParts[] = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return ' ';
        }, $markup);

        /** @var list<string> $visible */
        $visible = [];
        /** @var list<string> $hiddenBuf */
        $hiddenBuf = [];
        /** @var list<string> $stack */
        $stack = [];
        /** @var int|null $hiddenAt */
        $hiddenAt = null;
        $pos = 0;

        if (preg_match_all(self::TAG_PATTERN, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tagStart = (int) $match[0][1];
                $tagLen = strlen($match[0][0]);

                $chunk = substr($markup, $pos, $tagStart - $pos);
                if ($hiddenAt === null) {
                    $visible[] = $chunk;
                } else {
                    $hiddenBuf[] = $chunk;
                }
                $pos = $tagStart + $tagLen;

                $closing = $match[1][0] === '/';
                $name = strtolower($match[2][0]);
                $rawAttrs = $match[3][0];

                if (isset(self::BLOCK_TAGS[$name])) {
                    if ($hiddenAt === null) {
                        $visible[] = "\n";
                    } else {
                        $hiddenBuf[] = "\n";
                    }
                }

                if ($closing) {
                    $targetIdx = null;
                    for ($i = count($stack) - 1; $i >= 0; $i--) {
                        if ($stack[$i] === $name) {
                            $targetIdx = $i;
                            break;
                        }
                    }

                    if ($targetIdx !== null) {
                        array_splice($stack, $targetIdx);
                        if ($hiddenAt !== null && $hiddenAt >= count($stack)) {
                            $raw = implode('', $hiddenBuf);
                            $block = $this->fold(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                            $hiddenBuf = [];
                            $hiddenAt = null;
                            if ($block !== '') {
                                $hiddenParts[] = $block;
                            }
                        }
                    }

                    continue;
                }

                if (isset(self::VOID_TAGS[$name])) {
                    continue;
                }

                $stackCount = count($stack);
                $shouldPop = false;
                if (isset(self::IMPLIED_END_TAGS[$name]) && $stackCount > 0 && $stack[$stackCount - 1] === $name) {
                    $shouldPop = true;
                } elseif (isset(self::CLOSES_P_TAGS[$name]) && $stackCount > 0 && $stack[$stackCount - 1] === 'p') {
                    $shouldPop = true;
                }

                if ($shouldPop) {
                    array_splice($stack, $stackCount - 1);
                    if ($hiddenAt !== null && $hiddenAt >= count($stack)) {
                        $raw = implode('', $hiddenBuf);
                        $block = $this->fold(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $hiddenBuf = [];
                        $hiddenAt = null;
                        if ($block !== '') {
                            $hiddenParts[] = $block;
                        }
                    }
                }

                $stack[] = $name;

                if ($hiddenAt === null && $this->isHidden($rawAttrs)) {
                    $hiddenAt = count($stack) - 1;
                    $hiddenBlocksCount++;
                }
            }
        }

        $remainingChunk = substr($markup, $pos);
        if ($hiddenAt === null) {
            $visible[] = $remainingChunk;
        } else {
            $hiddenBuf[] = $remainingChunk;
            $raw = implode('', $hiddenBuf);
            $block = $this->fold(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($block !== '') {
                $hiddenParts[] = $block;
            }
        }

        $cleanText = $this->fold(html_entity_decode(implode('', $visible), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $hiddenText = $this->fold(implode("\n", $hiddenParts));

        return [$cleanText, $hiddenText, $hiddenBlocksCount];
    }

    /**
     * Determines whether element attributes signal that it is hidden.
     */
    private function isHidden(string $rawAttrs): bool
    {
        $attrs = $this->parseAttributes($rawAttrs);

        // Strict boolean attribute hidden
        if (array_key_exists('hidden', $attrs)) {
            return true;
        }

        // Accessibility aria-hidden="true"
        if (strtolower(trim($attrs['aria-hidden'] ?? '')) === 'true') {
            return true;
        }

        // CSS inline style inspection
        $style = $this->normalizeStyle($attrs['style'] ?? '');
        if ($style === '') {
            return false;
        }

        if (preg_match(self::HIDDEN_STYLE_PATTERN, $style) === 1) {
            return true;
        }

        // Collapsed box requires BOTH zero dimensions AND clipped overflow
        return preg_match(self::COLLAPSED_BOX_PATTERN, $style) === 1
            && preg_match(self::CLIPPED_PATTERN, $style) === 1;
    }

    /**
     * Parses element attributes into key-value pairs.
     *
     * @return array<string, string>
     */
    private function parseAttributes(string $rawAttrs): array
    {
        $attrs = [];
        if (preg_match_all(self::ATTR_PATTERN, $rawAttrs, $matches, PREG_SET_ORDER)) {
            /** @var list<array<int, string>> $matches */
            foreach ($matches as $m) {
                $name = strtolower($m[1]);
                $val = '';
                if (isset($m[2]) && $m[2] !== '') {
                    $val = $m[2];
                } elseif (isset($m[3]) && $m[3] !== '') {
                    $val = $m[3];
                } elseif (isset($m[4])) {
                    $val = $m[4];
                }
                $attrs[$name] = $val;
            }
        }

        return $attrs;
    }

    /**
     * Decodes HTML entities and strips CSS comments from style declaration.
     */
    private function normalizeStyle(string $style): string
    {
        $decoded = html_entity_decode($style, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/\/\*.*?\*\//s', '', $decoded);
    }

    /**
     * Normalizes line breaks and whitespace sequences.
     */
    private function fold(string $text): string
    {
        $hasCrlf = str_contains($text, "\r\n");
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[ \t\x{00A0}\x{000B}\f]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $hasCrlf ? str_replace("\n", "\r\n", $text) : $text;
    }
}
