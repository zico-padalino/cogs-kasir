<?php

namespace App\Support;

/**
 * Pembuat PDF teks sederhana tanpa dependency eksternal (struk kasir).
 */
final class SimplePdf
{
    /** @var list<array{type:string,text?:string,left?:string,right?:string,size?:float,bold?:bool,align?:string,amount?:float}> */
    private array $ops = [];

    private int $pageWidth = 420;

    private int $margin = 24;

    private float $columnGap = 12;

    private float $checkboxGap = 14;

    public function title(string $text): self
    {
        $this->ops[] = ['type' => 'text', 'text' => $text, 'size' => 40.0, 'bold' => true, 'align' => 'C'];

        return $this;
    }

    public function line(string $text = '', float $size = 24, bool $bold = false, string $align = 'L'): self
    {
        $this->ops[] = ['type' => 'text', 'text' => $text, 'size' => $size, 'bold' => $bold, 'align' => $align];

        return $this;
    }

    public function spacer(float $amount = 12): self
    {
        $this->ops[] = ['type' => 'spacer', 'amount' => $amount];

        return $this;
    }

    public function separator(): self
    {
        $this->ops[] = ['type' => 'separator'];

        return $this;
    }

    public function twoColumns(string $left, string $right, float $size = 24): self
    {
        $this->ops[] = ['type' => 'columns', 'left' => $left, 'right' => $right, 'size' => $size];

        return $this;
    }

    /** Baris item dengan kotak ceklis di kanan (struk dapur). */
    public function checkItem(string $text, float $size = 28): self
    {
        $this->ops[] = ['type' => 'check', 'text' => $text, 'size' => $size];

        return $this;
    }

    public function render(): string
    {
        $contentHeight = $this->margin;
        foreach ($this->ops as $op) {
            $contentHeight += match ($op['type']) {
                'spacer' => $op['amount'] ?? 12,
                'separator' => 18,
                'columns' => $this->columnsHeight($op),
                'check' => $this->checkHeight($op),
                default => $this->textHeight($op),
            };
        }
        $contentHeight += $this->margin;

        $pageHeight = max(520, (int) ceil($contentHeight));
        $y = $pageHeight - $this->margin;
        $stream = [];

        foreach ($this->ops as $op) {
            if ($op['type'] === 'spacer') {
                $y -= $op['amount'] ?? 12;
                continue;
            }

            if ($op['type'] === 'separator') {
                $stream[] = sprintf('%.2F %.2F m %.2F %.2F l S', $this->margin, $y, $this->pageWidth - $this->margin, $y);
                $y -= 18;
                continue;
            }

            if ($op['type'] === 'columns') {
                $y = $this->drawColumns($stream, $op, $y);
                continue;
            }

            if ($op['type'] === 'check') {
                $y = $this->drawCheckItem($stream, $op, $y);
                continue;
            }

            $size = $op['size'] ?? 24;
            $bold = (bool) ($op['bold'] ?? false);
            $align = $op['align'] ?? 'L';
            $text = (string) ($op['text'] ?? '');
            $maxWidth = $this->pageWidth - (2 * $this->margin);
            $lines = $this->wrapText($text, $maxWidth, $size);

            foreach ($lines as $line) {
                $textY = $y - $size;
                $width = $this->textWidth($line, $size);
                $x = match ($align) {
                    'C' => ($this->pageWidth - $width) / 2,
                    'R' => $this->pageWidth - $this->margin - $width,
                    default => $this->margin,
                };
                $this->writeText($stream, $line, $x, $textY, $size, $bold);
                $y -= ($size + 8);
            }
        }

        $content = implode("\n", $stream)."\n";
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
            $this->pageWidth,
            $pageHeight,
        );
        $objects[] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $i => $object) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array{type:string,left?:string,right?:string,size?:float}  $op
     * @param  list<string>  $stream
     */
    private function drawColumns(array &$stream, array $op, float $y): float
    {
        $size = $op['size'] ?? 24;
        $left = (string) ($op['left'] ?? '');
        $right = (string) ($op['right'] ?? '');
        $rightWidth = $this->textWidth($right, $size);
        $maxLeft = max(20.0, $this->pageWidth - (2 * $this->margin) - $rightWidth - $this->columnGap);
        $leftLines = $this->wrapText($left, $maxLeft, $size);

        foreach ($leftLines as $index => $leftLine) {
            $textY = $y - $size;
            $this->writeText($stream, $leftLine, $this->margin, $textY, $size, false);

            if ($index === 0 && $right !== '') {
                $this->writeText(
                    $stream,
                    $right,
                    $this->pageWidth - $this->margin - $rightWidth,
                    $textY,
                    $size,
                    true,
                );
            }

            $y -= ($size + 8);
        }

        return $y;
    }

    /**
     * @param  array{type:string,left?:string,right?:string,size?:float}  $op
     */
    private function columnsHeight(array $op): float
    {
        $size = $op['size'] ?? 24;
        $left = (string) ($op['left'] ?? '');
        $right = (string) ($op['right'] ?? '');
        $rightWidth = $this->textWidth($right, $size);
        $maxLeft = max(20.0, $this->pageWidth - (2 * $this->margin) - $rightWidth - $this->columnGap);
        $lines = max(1, count($this->wrapText($left, $maxLeft, $size)));

        return $lines * ($size + 8);
    }

    /**
     * @param  array{type:string,text?:string,size?:float}  $op
     * @param  list<string>  $stream
     */
    private function drawCheckItem(array &$stream, array $op, float $y): float
    {
        $size = $op['size'] ?? 28;
        $text = (string) ($op['text'] ?? '');
        $box = $this->checkboxSize($size);
        $maxLeft = max(20.0, $this->pageWidth - (2 * $this->margin) - $box - $this->checkboxGap);
        $lines = $this->wrapText($text, $maxLeft, $size);

        foreach ($lines as $index => $line) {
            $textY = $y - $size;
            $this->writeText($stream, $line, $this->margin, $textY, $size, false);

            if ($index === 0) {
                $this->drawCheckbox($stream, $this->pageWidth - $this->margin, $textY, $size);
            }

            $y -= ($size + 8);
        }

        return $y;
    }

    /**
     * @param  array{type:string,text?:string,size?:float}  $op
     */
    private function checkHeight(array $op): float
    {
        $size = $op['size'] ?? 28;
        $text = (string) ($op['text'] ?? '');
        $box = $this->checkboxSize($size);
        $maxLeft = max(20.0, $this->pageWidth - (2 * $this->margin) - $box - $this->checkboxGap);
        $lines = max(1, count($this->wrapText($text, $maxLeft, $size)));

        return $lines * ($size + 8);
    }

    private function checkboxSize(float $size): float
    {
        return min($size * 0.85, 24.0);
    }

    /** @param list<string> $stream */
    private function drawCheckbox(array &$stream, float $right, float $baselineY, float $size): void
    {
        $box = $this->checkboxSize($size);
        $x = $right - $box;
        $y = $baselineY + (($size - $box) * 0.25);

        $stream[] = '2 w';
        $stream[] = sprintf('%.2F %.2F m', $x, $y);
        $stream[] = sprintf('%.2F %.2F l', $x + $box, $y);
        $stream[] = sprintf('%.2F %.2F l', $x + $box, $y + $box);
        $stream[] = sprintf('%.2F %.2F l', $x, $y + $box);
        $stream[] = 'h S';
        $stream[] = '1 w';
    }

    /**
     * @param  array{type:string,text?:string,size?:float}  $op
     */
    private function textHeight(array $op): float
    {
        $size = $op['size'] ?? 24;
        $text = (string) ($op['text'] ?? '');
        $maxWidth = $this->pageWidth - (2 * $this->margin);
        $lines = max(1, count($this->wrapText($text, $maxWidth, $size)));

        return $lines * ($size + 8);
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, float $maxWidth, float $size): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        if ($this->textWidth($text, $size) <= $maxWidth) {
            return [$text];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->textWidth($candidate, $size) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            if ($this->textWidth($word, $size) <= $maxWidth) {
                $current = $word;
                continue;
            }

            foreach ($this->chunkByWidth($word, $maxWidth, $size) as $chunk) {
                $lines[] = $chunk;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : [''];
    }

    /**
     * @return list<string>
     */
    private function chunkByWidth(string $text, float $maxWidth, float $size): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text);
        $chunks = [];
        $current = '';

        foreach ($chars as $char) {
            $candidate = $current.$char;
            if ($current !== '' && $this->textWidth($candidate, $size) > $maxWidth) {
                $chunks[] = $current;
                $current = $char;
                continue;
            }
            $current = $candidate;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks !== [] ? $chunks : [$text];
    }

    /** @param list<string> $stream */
    private function writeText(array &$stream, string $text, float $x, float $y, float $size, bool $bold): void
    {
        $font = $bold ? 'F2' : 'F1';
        $stream[] = 'BT';
        $stream[] = sprintf('/%s %.2F Tf', $font, $size);
        $stream[] = sprintf('1 0 0 1 %.2F %.2F Tm (%s) Tj', $x, $y, $this->escape($text));
        $stream[] = 'ET';
    }

    private function textWidth(string $text, float $size): float
    {
        return strlen($this->escape($text)) * $size * 0.5;
    }

    private function escape(string $text): string
    {
        // Map dulu karakter yang sering jadi "?" (· / bullet / dash tipografi).
        $text = strtr($text, [
            '·' => '-', '•' => '-', '●' => '-',
            '–' => '-', '—' => '-', '×' => 'x', '…' => '...',
            '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a',
            'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'É' => 'E', 'Á' => 'A', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);

        // IGNORE (bukan TRANSLIT) agar char asing dibuang, bukan diganti "?".
        $converted = @iconv('UTF-8', 'ASCII//IGNORE', $text);
        $text = $converted === false
            ? (preg_replace('/[^\x20-\x7E]+/u', '', $text) ?? $text)
            : $converted;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
