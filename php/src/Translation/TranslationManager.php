<?php
declare(strict_types=1);

namespace AIO\Translation;

/**
 * Resolves the active language and loads translations from a flat JSON file.
 *
 * Language resolution order:
 *   1. PHP session  ($_SESSION['aio_user_language'])
 *   2. Accept-Language HTTP header (first matching tag that has a JSON file)
 *   3. Hardcoded fallback: "en"
 *
 * English is the implicit source language — the key itself is the English
 * string, so no en.json is required.
 *
 * Translation files live at:
 *   <project-root>/php/translations/{lang}.json
 * Each file is a flat JSON object:  {"some_key": "Translated string", ...}
 */
final class TranslationManager
{
    private const TRANSLATIONS_DIR = __DIR__ . '/../../translations';
    private const FALLBACK_LANGUAGE = 'en';
    private const SESSION_KEY = 'aio_user_language';

    /** @var array<string, string> */
    private array $strings = [];

    private string $currentLanguage;

    /** @var list<string>|null  Lazily populated from the filesystem. */
    private ?array $supportedLanguages = null;

    public function __construct()
    {
        $this->currentLanguage = $this->resolveLanguage();
        $this->loadStrings($this->currentLanguage);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the translated string for $key, or $key itself when no
     * translation is available (English pass-through behaviour).
     */
    public function translate(string $key): string
    {
        return $this->strings[$key] ?? $key;
    }

    /**
     * The language code that is currently active (e.g. "de", "fr", "en").
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * All language codes for which a translations/*.json file exists.
     * The list is sorted alphabetically and always includes "en".
     *
     * @return list<string>
     */
    public function getSupportedLanguages(): array
    {
        if ($this->supportedLanguages !== null) {
            return $this->supportedLanguages;
        }

        $languages = ['en'];

        $pattern = self::TRANSLATIONS_DIR . '/*.json';
        $files = glob($pattern);
        foreach ($files !== false ? $files : [] as $file) {
            $code = basename($file, '.json');
            if ($code !== 'en' && $this->isValidLanguageCode($code)) {
                $languages[] = $code;
            }
        }

        sort($languages);
        $this->supportedLanguages = $languages;

        return $this->supportedLanguages;
    }

    // -------------------------------------------------------------------------
    // Language resolution
    // -------------------------------------------------------------------------

    private function resolveLanguage(): string
    {
        // 1. Session preference set by the user via the language switcher.
        if (
            isset($_SESSION[self::SESSION_KEY])
            && is_string($_SESSION[self::SESSION_KEY])
            && $this->isValidLanguageCode($_SESSION[self::SESSION_KEY])
        ) {
            $lang = $this->normalise($_SESSION[self::SESSION_KEY]);
            if ($this->hasTranslationFile($lang) || $lang === self::FALLBACK_LANGUAGE) {
                return $lang;
            }
        }

        // 2. Accept-Language header — try each tag in quality order.
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLanguage !== '') {
            $candidate = $this->resolveFromAcceptLanguage($acceptLanguage);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        // 3. Hardcoded fallback.
        return self::FALLBACK_LANGUAGE;
    }

    /**
     * Parse an Accept-Language header value and return the best matching
     * language code for which we have a translation file, or null.
     *
     * Example header: "de-AT,de;q=0.9,en-US;q=0.8,en;q=0.7"
     */
    private function resolveFromAcceptLanguage(string $header): ?string
    {
        // Split on comma, sort by quality weight (highest first).
        $tags = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $quality = 1.0;
            if (str_contains($part, ';q=')) {
                $segments = explode(';q=', $part, 2);
                $quality = (float) ($segments[1] ?? '1');
                $part = trim($segments[0]);
            }
            $tags[] = ['tag' => $part, 'q' => $quality];
        }
        usort($tags, static fn(array $a, array $b): int => $b['q'] <=> $a['q']);

        foreach ($tags as $entry) {
            $tag = $entry['tag'];

            // Try the exact tag first (e.g. "de-AT"), then the primary subtag
            // (e.g. "de"), then a case-insensitive match against known files.
            foreach ($this->candidatesFor($tag) as $candidate) {
                if ($candidate === self::FALLBACK_LANGUAGE) {
                    return self::FALLBACK_LANGUAGE;
                }
                if ($this->hasTranslationFile($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Return the normalised language code candidates to try for a given
     * Accept-Language tag, from most specific to least specific.
     *
     * @return list<string>
     */
    private function candidatesFor(string $tag): array
    {
        $candidates = [];

        $normalised = $this->normalise($tag);
        if ($this->isValidLanguageCode($normalised)) {
            $candidates[] = $normalised;
        }

        // If the tag contains a region/script subtag, also try just the
        // primary language subtag (e.g. "de-AT" → "de").
        if (str_contains($normalised, '-')) {
            $primary = explode('-', $normalised, 2)[0];
            if ($this->isValidLanguageCode($primary)) {
                $candidates[] = $primary;
            }
        }

        return $candidates;
    }

    // -------------------------------------------------------------------------
    // Translation file loading
    // -------------------------------------------------------------------------

    private function loadStrings(string $language): void
    {
        if ($language === self::FALLBACK_LANGUAGE) {
            // English: key == translation, nothing to load.
            $this->strings = [];
            return;
        }

        $path = $this->translationFilePath($language);
        if (!file_exists($path)) {
            $this->strings = [];
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->strings = [];
            return;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            $this->strings = [];
            return;
        }

        /** @var array<string, string> $strings */
        $strings = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $strings[$key] = $value;
            }
        }
        $this->strings = $strings;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function hasTranslationFile(string $language): bool
    {
        return file_exists($this->translationFilePath($language));
    }

    private function translationFilePath(string $language): string
    {
        return self::TRANSLATIONS_DIR . '/' . $language . '.json';
    }

    /**
     * Normalise a language tag to lowercase with hyphens
     * (e.g. "de_AT" → "de-at", "ZH-Hans" → "zh-hans").
     */
    private function normalise(string $tag): string
    {
        return strtolower(str_replace('_', '-', $tag));
    }

    /**
     * Sanity-check that the string looks like a BCP-47 language tag and
     * cannot be used for path traversal.
     */
    private function isValidLanguageCode(string $code): bool
    {
        return (bool) preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/', $code);
    }
}