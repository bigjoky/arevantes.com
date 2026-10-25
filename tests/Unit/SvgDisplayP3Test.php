<?php

namespace Tests\Unit;

use Dompdf\Dompdf;
use PHPUnit\Framework\TestCase;
use Svg\Style;

class SvgDisplayP3Test extends TestCase
{
    public function test_official_colors_are_interpreted_in_the_pdf_output_color_space(): void
    {
        // CSS Color 4 D65 matrix conversion; expected 8-bit sRGB display values.
        $this->assertSame([0, 82, 59, 1], array_map(fn ($value) => (int) round($value), Style::parseColor('color(display-p3 0.129 0.318 0.239)')));
        $this->assertSame([134, 208, 44, 1], array_map(fn ($value) => (int) round($value), Style::parseColor('color(display-p3 0.592 0.808 0.298)')));
        $this->assertSame([255, 0, 0, 1.0], Style::parseColor('#ff0000'));
        $this->assertSame('none', Style::parseColor('none'));
    }

    public function test_original_svg_is_embedded_as_colored_vectors_without_rasterization(): void
    {
        $bytes = file_get_contents(__DIR__.'/../../public/brand/arevantes-logo.svg');
        $this->assertSame('ccf9d9cb2ef4a56ed3ba9e89f8f87cd51ff378a10e8671ff971dbc7607a10394', hash('sha256', $bytes));
        $pdf = new Dompdf;
        $pdf->loadHtml('<img style="width:180mm" src="data:image/svg+xml;base64,'.base64_encode($bytes).'">');
        $pdf->render();
        $output = $pdf->output(['compress' => false]);
        $this->assertStringNotContainsString('/Subtype /Image', $output);
        $this->assertStringContainsString('0.527 0.816 0.171 rg', $output);
        $this->assertStringContainsString('0.000 0.323 0.233 rg', $output);
    }
}
