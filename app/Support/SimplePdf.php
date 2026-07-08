<?php

namespace App\Support;

/**
 * Pembuat PDF teks sederhana tanpa dependency eksternal (struk kasir).
 */
final class SimplePdf
{
    /** @var list<array{type:string,text?:string,left?:string,right?:string,size?:float,bold?:bool,align?:string,amount?:float}> */
    private array $ops = [];

    private int $pageWidth = 226;

    private int $margin = 20;

    public function title(string $text): self
    {
        $this->ops[] = ['type' => 'text', 'text' => $text, 'size' => 13.0, 'bold' => true, 'align' => 'C'];

        return $this;
    }

    public function line(string $text = '', float $size = 9.5, bool $bold = false, string $align = 'L'): self
    {
        $this->ops[] = ['type' => 'text', 'text' => $text, 'size' => $size, 'bold' => $bold, 'align' => $align];

        return $this;
    }

    public function spacer(float $amount = 6): self
    {
        $this->ops[] = ['type' => 'spacer', 'amount' => $amount];

        return $this;
    }

    public function separator(): self
    {
        $this->ops[] = ['type' => 'separator'];

        return $this;
    }

    public function twoColumns(string $left, string $right, float $size = 9.5): self
    {
        $this->ops[] = ['type' => 'columns', 'left' => $left, 'right' => $right, 'size' => $size];

        return $this;
    }

    public function render(): string
    {
        $contentHeight = $this->margin;
        foreach ($this->ops as $op) {
            $contentHeight += match ($op['type']) {
                'spacer' => $op['amount'] ?? 6,
                'separator' => 10,
                'columns' => ($op['size'] ?? 9.5) + 4,
                default => ($op['size'] ?? 9.5) + 4,
            };
        }
        $contentHeight += $this->margin;

        $pageHeight = max(320, (int) ceil($contentHeight));
        $y = $pageHeight - $this->margin;
        $stream = [];

        foreach ($this->ops as $op) {
            if ($op['type'] === 'spacer') {
                $y -= $op['amount'] ?? 6;
                continue;
            }

            if ($op['type'] === 'separator') {
                $stream[] = sprintf('%.2F %.2F m %.2F %.2F l S', $this->margin, $y, $this->pageWidth - $this->margin, $y);
                $y -= 10;
                continue;
            }

            if ($op['type'] === 'columns') {
                $size = $op['size'] ?? 9.5;
                $textY = $y - $size;
                $left = (string) ($op['left'] ?? '');
                $right = (string) ($op['right'] ?? '');
                $this->writeText($stream, $left, $this->margin, $textY, $size, false);
                $rightWidth = $this->textWidth($right, $size);
                $this->writeText($stream, $right, $this->pageWidth - $this->margin - $rightWidth, $textY, $size, true);
                $y -= ($size + 4);
                continue;
            }

            $size = $op['size'] ?? 9.5;
            $bold = (bool) ($op['bold'] ?? false);
            $align = $op['align'] ?? 'L';
            $text = (string) ($op['text'] ?? '');
            $textY = $y - $size;
            $width = $this->textWidth($text, $size);
            $x = match ($align) {
                'C' => ($this->pageWidth - $width) / 2,
                'R' => $this->pageWidth - $this->margin - $width,
                default => $this->margin,
            };
            $this->writeText($stream, $text, $x, $textY, $size, $bold);
            $y -= ($size + 4);
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
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $converted === false ? (preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text) : $converted;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
