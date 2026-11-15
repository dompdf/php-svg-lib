<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\CPdf;
use Svg\Surface\SurfaceCpdf;

final class SurfaceCpdfTest extends TestCase
{
    private function render(string $svg): string
    {
        $file = tempnam(sys_get_temp_dir(), "svg");
        file_put_contents($file, $svg);

        try {
            $doc = new Document();
            $doc->loadFile($file);
            $canvas = new CPdf([0, 0, 100, 100]);
            $doc->render(new SurfaceCpdf($doc, $canvas));
        } finally {
            unlink($file);
        }

        return $canvas->output(true);
    }

    public function testStrokeDasharrayNone(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <path d="M 10 10 L 90 10" style="fill:none;stroke:#000;stroke-dasharray:none"/>
  <path d="M 10 20 L 90 20" style="fill:none;stroke:#000;stroke-dasharray:4,2"/>
</svg>
SVG;
        $output = $this->render($svg);

        // `none` is not a dash pattern
        $this->assertStringNotContainsString("none", $output);
        $this->assertSame(1, substr_count($output, " 0 d\n"));
        $this->assertStringContainsString("[ 4 2 ] 0 d\n", $output);
    }
}
