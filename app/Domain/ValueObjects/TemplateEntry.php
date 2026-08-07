<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * One positioned text stamp on a PDF-with-background template ("underlay"
 * mode). Everything here exists because a real pre-printed form demanded
 * it — this is a micro-layout language, not a styling system. Coordinates
 * are millimetres from the top-left corner of the page, everywhere:
 * storage, renderer, and (in the frontend) the canvas inspector/cursor
 * readout. Never convert units anywhere else in the pipeline.
 *
 * Typed and validated on construction — unlike the legacy system this is
 * ported from, which stored every attribute as a string and only
 * discovered malformed entries at render time.
 */
class TemplateEntry
{
    private const VALID_FORMATS = ['int', 'floor', 'ceil', 'round'];

    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly string $text,
        public readonly int $page,
        public readonly ?float $size = null,
        public readonly ?string $color = null,
        public readonly bool $bold = false,
        public readonly bool $italic = false,
        public readonly ?float $width = null,
        // Comb spacing: draw each character $space mm apart instead of one
        // run — fills boxed government forms, one glyph per box. Switches
        // the font to monospace. If the resolved text is longer than
        // $boxes, comb mode is abandoned and the text draws normally
        // rather than overflowing the form.
        public readonly ?float $space = null,
        public readonly ?int $boxes = null,
        public readonly ?string $format = null,
        // Conditional render: "{field}:value" — skipped unless the
        // resolved field equals the literal. Parsed at render time, kept
        // as a raw string here.
        public readonly ?string $if = null,
        // "a:b" substring of the resolved text, negative indices allowed.
        public readonly ?string $slice = null,
        // Renders a numeric month as a month name. Selects language *and*
        // casing: fr, Fr, FR, f, F (and the en/es equivalents).
        public readonly ?string $month = null,
        public readonly bool $digits = false,
        public readonly ?string $bg = null,
        // Preview-only: forces the text red in the generated preview so
        // the author can find it on a busy form. Never affects real output.
        public readonly bool $highlight = false,
    ) {
        if ($this->format !== null && ! in_array($this->format, self::VALID_FORMATS, true)) {
            throw new InvalidArgumentException(
                "Invalid TemplateEntry format '{$this->format}' — must be one of: ".implode(', ', self::VALID_FORMATS)
            );
        }

        if ($this->boxes !== null && $this->boxes < 0) {
            throw new InvalidArgumentException('TemplateEntry boxes must not be negative.');
        }
    }

    public static function fromArray(array $data): self
    {
        foreach (['x', 'y', 'text', 'page'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw new InvalidArgumentException("TemplateEntry is missing required field '{$required}'.");
            }
        }

        return new self(
            x: (float) $data['x'],
            y: (float) $data['y'],
            text: (string) $data['text'],
            page: (int) $data['page'],
            size: isset($data['size']) ? (float) $data['size'] : null,
            color: $data['color'] ?? null,
            bold: (bool) ($data['bold'] ?? false),
            italic: (bool) ($data['italic'] ?? false),
            width: isset($data['width']) ? (float) $data['width'] : null,
            space: isset($data['space']) ? (float) $data['space'] : null,
            boxes: isset($data['boxes']) ? (int) $data['boxes'] : null,
            format: $data['format'] ?? null,
            if: $data['if'] ?? null,
            slice: $data['slice'] ?? null,
            month: $data['month'] ?? null,
            digits: (bool) ($data['digits'] ?? false),
            bg: $data['bg'] ?? null,
            highlight: (bool) ($data['highlight'] ?? false),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'x' => $this->x,
            'y' => $this->y,
            'text' => $this->text,
            'page' => $this->page,
            'size' => $this->size,
            'color' => $this->color,
            'bold' => $this->bold ?: null,
            'italic' => $this->italic ?: null,
            'width' => $this->width,
            'space' => $this->space,
            'boxes' => $this->boxes,
            'format' => $this->format,
            'if' => $this->if,
            'slice' => $this->slice,
            'month' => $this->month,
            'digits' => $this->digits ?: null,
            'bg' => $this->bg,
            'highlight' => $this->highlight ?: null,
        ], fn ($value) => $value !== null);
    }
}
