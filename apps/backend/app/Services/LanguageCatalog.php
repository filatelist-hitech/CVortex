<?php

namespace App\Services;

/** Shared language-name recognition for source validation and matching. */
final class LanguageCatalog
{
    /** @var array<string, list<string>> */
    private const NAMES = [
        'afrikaans' => ['afrikaans'], 'albanian' => ['albanian'], 'amharic' => ['amharic'],
        'arabic' => ['arabic'], 'armenian' => ['armenian'], 'assamese' => ['assamese'],
        'azerbaijani' => ['azerbaijani'], 'basque' => ['basque'], 'belarusian' => ['belarusian'],
        'bengali' => ['bengali'], 'bosnian' => ['bosnian'], 'bulgarian' => ['bulgarian'],
        'burmese' => ['burmese', 'myanmar'], 'catalan' => ['catalan'],
        'chinese' => ['chinese', 'mandarin', 'cantonese'], 'croatian' => ['croatian'],
        'czech' => ['czech'], 'danish' => ['danish'], 'dutch' => ['dutch'],
        'english' => ['english', 'английск'], 'estonian' => ['estonian'],
        'finnish' => ['finnish'], 'french' => ['french', 'французск'],
        'georgian' => ['georgian'], 'german' => ['german', 'немецк'], 'greek' => ['greek'],
        'gujarati' => ['gujarati'], 'haitian creole' => ['haitian creole'], 'hausa' => ['hausa'],
        'hebrew' => ['hebrew'], 'hindi' => ['hindi'], 'hungarian' => ['hungarian'],
        'icelandic' => ['icelandic'], 'indonesian' => ['indonesian'], 'irish' => ['irish'],
        'italian' => ['italian'], 'japanese' => ['japanese'], 'javanese' => ['javanese'],
        'kannada' => ['kannada'], 'kazakh' => ['kazakh'], 'khmer' => ['khmer'],
        'korean' => ['korean'], 'kurdish' => ['kurdish'], 'kyrgyz' => ['kyrgyz'],
        'lao' => ['lao'], 'latin' => ['latin'], 'latvian' => ['latvian'],
        'lithuanian' => ['lithuanian'], 'macedonian' => ['macedonian'], 'malay' => ['malay'],
        'malayalam' => ['malayalam'], 'marathi' => ['marathi'], 'mongolian' => ['mongolian'],
        'nepali' => ['nepali'], 'norwegian' => ['norwegian'], 'odia' => ['odia'],
        'pashto' => ['pashto'], 'persian' => ['persian', 'farsi'], 'polish' => ['polish'],
        'portuguese' => ['portuguese'], 'punjabi' => ['punjabi'], 'romanian' => ['romanian'],
        'russian' => ['russian', 'русск'], 'serbian' => ['serbian'], 'sinhala' => ['sinhala'],
        'slovak' => ['slovak'], 'slovenian' => ['slovenian'], 'somali' => ['somali'],
        'spanish' => ['spanish', 'испанск'], 'sundanese' => ['sundanese'], 'swahili' => ['swahili'],
        'swedish' => ['swedish'], 'tagalog' => ['tagalog', 'filipino'], 'tamil' => ['tamil'],
        'telugu' => ['telugu'], 'thai' => ['thai'], 'turkish' => ['turkish'],
        'ukrainian' => ['ukrainian', 'украинск'], 'urdu' => ['urdu'], 'uzbek' => ['uzbek'],
        'vietnamese' => ['vietnamese'], 'welsh' => ['welsh'], 'yiddish' => ['yiddish'],
        'zulu' => ['zulu'],
    ];

    /** @var array<string, string>|null */
    private static ?array $patterns = null;

    public function tokenFromText(string $text): ?string
    {
        foreach ($this->patterns() as $language => $pattern) {
            if (preg_match('/(?<![\pL\pN])(?:'.$pattern.')(?![\pL\pN])/iu', $text) === 1) {
                return $language;
            }
        }

        return null;
    }

    public function pattern(string $language): ?string
    {
        return $this->patterns()[mb_strtolower($language)] ?? null;
    }

    /** @return array<string, string> */
    private function patterns(): array
    {
        if (self::$patterns === null) {
            self::$patterns = [];
            foreach (self::NAMES as $language => $aliases) {
                $patterns = array_map(static function (string $alias): string {
                    $quoted = preg_quote($alias, '/');

                    return str_ends_with($alias, 'ск') ? $quoted.'\\pL*' : $quoted;
                }, $aliases);
                self::$patterns[$language] = '(?:'.implode('|', $patterns).')';
            }
        }

        return self::$patterns;
    }
}
