<?php

namespace App\Application\Services;

use App\Domain\ValueObjects\TemplateEntry;

/**
 * Resolves one TemplateEntry's final drawable text — substitution, `if`
 * gating, and the digits/format/month/slice transform chain. Pure (no PDF
 * dependency) so it's unit-testable directly, separate from PdfRenderService
 * actually drawing it. This is where "the entry object's whole complexity
 * lives" per the module's design.
 */
class TemplateEntryTextResolver
{
    private const MONTHS = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    ];

    /**
     * @param array<string, string> $values Keyed with braces, e.g. '{last_name}' => 'Sample'.
     * @return string|null null means the entry is skipped entirely (its `if` condition didn't match).
     */
    public function resolve(TemplateEntry $entry, array $values): ?string
    {
        if ($entry->if !== null && ! $this->evaluateIf($entry->if, $values)) {
            return null;
        }

        $text = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($values) {
            return $values[$matches[0]] ?? '';
        }, $entry->text);

        if ($entry->digits) {
            $text = preg_replace('/\D+/', '', $text);
        }

        if ($entry->format !== null && is_numeric($text)) {
            $text = match ($entry->format) {
                'int' => (string) (int) $text,
                'floor' => (string) floor((float) $text),
                'ceil' => (string) ceil((float) $text),
                'round' => (string) round((float) $text),
                default => $text,
            };
        }

        if ($entry->month !== null && is_numeric($text)) {
            $text = $this->monthName((int) $text, $entry->month) ?? $text;
        }

        if ($entry->slice !== null) {
            $text = $this->applySlice($text, $entry->slice);
        }

        return $text;
    }

    /**
     * "{field}:value" — the entry is drawn only if the resolved field
     * equals the literal exactly.
     */
    private function evaluateIf(string $if, array $values): bool
    {
        if (! preg_match('/^\{([a-zA-Z0-9_]+)\}:(.*)$/', $if, $matches)) {
            return true; // malformed condition - fail open rather than silently hide the entry
        }

        $resolved = $values['{'.$matches[1].'}'] ?? '';

        return $resolved === $matches[2];
    }

    /**
     * "a:b" substring, negative indices allowed (splits a date/account
     * number across separate boxes).
     */
    private function applySlice(string $text, string $slice): string
    {
        if (! preg_match('/^(-?\d+):(-?\d+)$/', $slice, $matches)) {
            return $text;
        }

        $start = (int) $matches[1];
        $end = (int) $matches[2];

        // PHP's substr($str, $start, $length) takes a LENGTH, not an end
        // index — a negative length already means "stop that many
        // characters before the end of the string", which is exactly what
        // a negative end index means here. Only a non-negative end needs
        // converting to a length (end - start).
        $length = $end >= 0 ? $end - $start : $end;

        return substr($text, $start, $length);
    }

    /**
     * Only 2-letter codes (any casing: fr/Fr/FR) are fully supported — the
     * spec's single-letter abbreviated codes ("f, F, and the same for
     * en/es") are under-specified without the legacy source to check
     * against, so single-letter codes return null (caller keeps the raw
     * number) rather than guessing a matrix that might be wrong.
     */
    private function monthName(int $monthNumber, string $code): ?string
    {
        if ($monthNumber < 1 || $monthNumber > 12 || strlen($code) < 2) {
            return null;
        }

        $lang = strtolower(substr($code, 0, 2));

        if (! isset(self::MONTHS[$lang])) {
            return null;
        }

        $name = self::MONTHS[$lang][$monthNumber - 1];

        return match (true) {
            $code === mb_strtoupper($code) => mb_strtoupper($name),
            $code === mb_strtolower($code) => mb_strtolower($name),
            default => mb_convert_case($name, MB_CASE_TITLE),
        };
    }
}
