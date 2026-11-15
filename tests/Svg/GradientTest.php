<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\CPdf;
use Svg\Surface\SurfaceCpdf;

/**
 * Canvas that records gradient and stroke calls
 */
class GradientCanvas extends CPdf
{
    public $gradients = [];
    public $strokes = 0;

    public function clip(string $rule = "nonzero")
    {
    }

    public function stroke(bool $close = false)
    {
        $this->strokes++;
    }

    public function fillStroke(bool $close = false)
    {
        $this->strokes++;
    }

    public function gradient(
        int $type,
        array $coords,
        array $stops,
        array $bbox,
        ?array $matrix = null,
        string $spread = "pad"
    ): void {
        $this->gradients[] = compact("type", "coords", "stops", "bbox", "matrix", "spread");
    }
}

/**
 * Canvas without gradient support, like the Cpdf of older dompdf versions
 */
class LegacyCanvas
{
    private $pdf;

    public function __construct(CPdf $pdf)
    {
        $this->pdf = $pdf;
    }

    public function __call($name, $arguments)
    {
        return $this->pdf->$name(...$arguments);
    }

    public function __get($name)
    {
        return $this->pdf->$name;
    }

    public function __set($name, $value)
    {
        $this->pdf->$name = $value;
    }
}

final class GradientTest extends TestCase
{
    private $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    private function document(string $svg): Document
    {
        $file = tempnam(sys_get_temp_dir(), "svg");
        file_put_contents($file, $svg);
        $this->files[] = $file;

        $doc = new Document();
        $doc->loadFile($file);

        return $doc;
    }

    /**
     * @return array[] The recorded gradient calls
     */
    private function render(string $svg, $canvas = null): array
    {
        $doc = $this->document($svg);
        $canvas = $canvas ?? new GradientCanvas([0, 0, 100, 100]);
        $doc->render(new SurfaceCpdf($doc, $canvas));

        return $canvas instanceof GradientCanvas ? $canvas->gradients : [];
    }

    private function assertArrayEqualsWithDelta(array $expected, array $actual): void
    {
        $this->assertCount(count($expected), $actual);
        foreach ($expected as $i => $value) {
            $this->assertEqualsWithDelta($value, $actual[$i], 0.0001);
        }
    }

    public function testGetGradientParsesStops(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="Grad">
    <stop offset="10%" stop-color="red"/>
    <stop offset="0.05" style="stop-color: rgba(0, 0, 255, 0.5); stop-opacity: 0.5"/>
    <stop offset="2" stop-color="currentColor" stop-opacity="inherit"/>
  </linearGradient>
</svg>
SVG;
        $doc = $this->document($svg);

        $gradient = $doc->getGradient("#Grad");

        $this->assertSame("lineargradient", $gradient["type"]);
        $this->assertEquals([
            [0.1, [1, 0, 0], 1],
            [0.1, [0, 0, 1], 0.25],
            [1, [0, 0, 0], 1],
        ], $gradient["stops"]);
        $this->assertArrayEqualsWithDelta([85, 0, 85], $gradient["color"]);

        $this->assertNull($doc->getGradient("#grad"));
        $this->assertNull($doc->getGradient("#unknown"));
        $this->assertNull($doc->getGradient("none"));
        $this->assertNull($doc->getGradient([0, 0, 0, 1]));
    }

    public function testGetGradientInheritsFromReferences(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="100">
  <rect width="10" height="10" fill="url(#c)"/>
  <defs>
    <radialGradient id="c" xlink:href="#b" r="10%"/>
    <linearGradient id="b" href="#a" gradientUnits="userSpaceOnUse" r="20%"/>
    <linearGradient id="a" x1="5" gradientUnits="objectBoundingBox">
      <stop offset="0" stop-color="#fff"/>
      <stop offset="1" stop-color="#000"/>
    </linearGradient>
    <linearGradient id="loop1" href="#loop2"/>
    <linearGradient id="loop2" href="#loop1"/>
  </defs>
</svg>
SVG;
        $doc = $this->document($svg);

        $gradient = $doc->getGradient("#c");

        $this->assertSame("radialgradient", $gradient["type"]);
        $this->assertSame("10%", $gradient["attributes"]["r"]);
        $this->assertSame("5", $gradient["attributes"]["x1"]);
        $this->assertSame("userSpaceOnUse", $gradient["attributes"]["gradientunits"]);
        $this->assertCount(2, $gradient["stops"]);
        $this->assertSame([], $doc->getGradient("#loop1")["stops"]);
    }

    public function testLinearGradientInBoundingBoxUnits(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <rect x="10" y="20" width="50" height="40" fill="url(#g)"/>
  <linearGradient id="g" x2="0" y2="100%">
    <stop offset="0" stop-color="red" stop-opacity="0.5"/>
    <stop offset="1" stop-color="blue"/>
  </linearGradient>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertCount(1, $calls);
        $this->assertSame(2, $calls[0]["type"]);
        $this->assertArrayEqualsWithDelta([0, 0, 0, 1], $calls[0]["coords"]);
        $this->assertArrayEqualsWithDelta([10, 20, 60, 60], $calls[0]["bbox"]);
        $this->assertArrayEqualsWithDelta([50, 0, 0, 40, 10, 20], $calls[0]["matrix"]);
        $this->assertEquals(0.5, $calls[0]["stops"][0][2]);
    }

    public function testRadialGradientInUserSpace(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <radialGradient id="g" gradientUnits="userSpaceOnUse" cx="50%" cy="20" r="10" fx="80" fy="20"
      gradientTransform="translate(5 10) scale(2)">
    <stop offset="0" stop-color="red"/>
    <stop offset="1" stop-color="blue"/>
  </radialGradient>
  <circle cx="50" cy="50" r="40" fill="url(#g)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertCount(1, $calls);
        $this->assertSame(3, $calls[0]["type"]);
        // The focal point is moved onto the end circle
        $this->assertArrayEqualsWithDelta([59.9, 20, 0, 50, 20, 10], $calls[0]["coords"]);
        $this->assertArrayEqualsWithDelta([10, 10, 90, 90], $calls[0]["bbox"]);
        $this->assertArrayEqualsWithDelta([2, 0, 0, 2, 5, 10], $calls[0]["matrix"]);
    }

    public function testGradientTransformOrder(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g" gradientUnits="userSpaceOnUse" gradientTransform="rotate(90, 10, 0) translate(10)">
    <stop offset="0" stop-color="red"/>
  </linearGradient>
  <rect width="100" height="100" fill="url(#g)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertArrayEqualsWithDelta([0, 1, -1, 0, 10, 0], $calls[0]["matrix"]);
    }

    public function testGradientNotApplicable(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="empty"/>
  <linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient>
  <rect width="100" height="100" fill="url(#empty)"/>
  <rect width="100" height="100" fill="url(#unknown)"/>
  <line x1="0" y1="10" x2="100" y2="10" fill="url(#g)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertSame([], $calls);
    }

    public function testSpreadMethodAndViewBox(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200" viewBox="0 0 100 50">
  <linearGradient id="g" gradientUnits="userSpaceOnUse" x2="50%" y2="100%" spreadMethod="reflect">
    <stop offset="0" stop-color="red"/>
  </linearGradient>
  <rect width="100" height="50" fill="url(#g)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertSame("reflect", $calls[0]["spread"]);
        $this->assertArrayEqualsWithDelta([0, 0, 50, 50], $calls[0]["coords"]);
    }

    public function testCurveBoundingBox(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient>
  <path d="M 10 50 C 10 0, 90 0, 90 50 Q 50 100 10 50 Z" fill="url(#g)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        // The curves reach y = 12.5 and y = 75, their control points go further
        $this->assertArrayEqualsWithDelta([10, 12.5, 90, 75], $calls[0]["bbox"]);
    }

    public function testGradientStrokeNotApplicable(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient>
  <line x1="0" y1="10" x2="100" y2="10" stroke="url(#g)"/>
  <line x1="0" y1="10" x2="100" y2="20" stroke="url(#g)"/>
</svg>
SVG;
        $canvas = new GradientCanvas([0, 0, 100, 100]);
        $this->render($svg, $canvas);

        $this->assertSame(1, $canvas->strokes);
    }

    public function testStopStyles(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <style>.a { stop-color: red } .b { stop-color: blue; stop-opacity: 50% }</style>
  <g color="lime" stop-color="#ff0" stop-opacity="0.5">
    <linearGradient id="css"><stop class="a"/><stop class="b" offset="1"/></linearGradient>
    <linearGradient id="current"><stop stop-color="currentColor"/></linearGradient>
    <linearGradient id="inherit" stop-color="inherit">
      <stop stop-color="inherit" stop-opacity="inherit"/>
      <stop offset="1"/>
    </linearGradient>
  </g>
  <rect width="10" height="10" fill="url(#css)"/>
</svg>
SVG;
        $doc = $this->document($svg);
        $doc->render(new SurfaceCpdf($doc, new GradientCanvas([0, 0, 100, 100])));

        $this->assertEquals([[0, [1, 0, 0], 1], [1, [0, 0, 1], 0.5]], $doc->getGradient("#css")["stops"]);
        $this->assertEquals([[0, [0, 1, 0], 1]], $doc->getGradient("#current")["stops"]);
        // stop-color and stop-opacity are only inherited explicitly
        $this->assertEquals([[0, [1, 1, 0], 1], [1, [0, 0, 0], 1]], $doc->getGradient("#inherit")["stops"]);
    }

    public function testPaintReferences(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <style>.paint { fill: url("#G") }</style>
  <linearGradient id="G"><stop stop-color="red"/></linearGradient>
  <linearGradient id="g"><stop stop-color="blue"/></linearGradient>
  <rect class="paint" width="10" height="10"/>
  <rect width="10" height="10" fill="url(#g) lime"/>
  <rect width="10" height="10" fill="url(#missing) lime"/>
  <rect width="10" height="10" fill="url(#missing)"/>
</svg>
SVG;
        $canvas = new GradientCanvas([0, 0, 100, 100]);
        $this->render($svg, $canvas);

        // Fallback colors are only used for missing gradients, which are `none` otherwise
        $this->assertSame([[1, 0, 0]], array_column($canvas->gradients[0]["stops"], 1));
        $this->assertSame([[0, 0, 1]], array_column($canvas->gradients[1]["stops"], 1));
        $this->assertCount(2, $canvas->gradients);
        $this->assertStringContainsString("0.000 1.000 0.000 rg", $canvas->output(true));
        $this->assertSame(1, substr_count($canvas->output(true), "\nf"));
    }

    public function testStopStylePrecedenceAndLateStyleSheet(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g"><stop class="hot" stop-color="blue"/><stop class="late" offset="1"/></linearGradient>
  <rect width="10" height="10" fill="url(#g)"/>
  <style>.hot { stop-color: red } .late { stop-color: lime }</style>
</svg>
SVG;
        $calls = $this->render($svg);

        // Style sheet rules take precedence over presentation attributes, also
        // when the style sheet comes after the gradient
        $this->assertEquals([[0, [1, 0, 0], 1], [1, [0, 1, 0], 1]], $calls[0]["stops"]);
    }

    public function testFallbackCurrentColor(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <rect width="10" height="10" color="#f00" fill="url(#missing) currentColor"/>
</svg>
SVG;
        $canvas = new GradientCanvas([0, 0, 100, 100]);
        $this->render($svg, $canvas);

        $this->assertStringContainsString("1.000 0.000 0.000 rg", $canvas->output(true));
    }

    public function testLengthUnits(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100">
  <linearGradient id="g" gradientUnits="userSpaceOnUse" x2="6cm"><stop stop-color="red"/></linearGradient>
  <rect width="400" height="100" fill="url(#g)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertArrayEqualsWithDelta([0, 0, 6 / 2.54 * 96, 0], $calls[0]["coords"]);
    }

    public function testGradientWithoutExtentUsesLastStop(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="l" x2="0">
    <stop stop-color="red"/><stop offset="1" stop-color="blue" stop-opacity="0.5"/>
  </linearGradient>
  <radialGradient id="r" r="0"><stop stop-color="red"/><stop offset="1" stop-color="blue"/></radialGradient>
  <radialGradient id="negative" r="-1"><stop stop-color="red"/></radialGradient>
  <rect width="10" height="10" fill="url(#l)"/>
  <rect width="10" height="10" fill="url(#r)"/>
  <rect width="10" height="10" fill="url(#negative)"/>
</svg>
SVG;
        $calls = $this->render($svg);

        $this->assertCount(2, $calls);
        $this->assertEquals([[1, [0, 0, 1], 0.5]], $calls[0]["stops"]);
        $this->assertEquals([[1, [0, 0, 1], 1]], $calls[1]["stops"]);
    }

    public function testFallbackToAverageColor(): void
    {
        $canvas = new LegacyCanvas(new CPdf([0, 0, 100, 100]));
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g">
    <stop offset="0" stop-color="red"/>
    <stop offset="1" stop-color="blue"/>
  </linearGradient>
  <rect width="100" height="100" fill="url(#g)"/>
</svg>
SVG;
        $this->render($svg, $canvas);

        $output = $canvas->output(true);
        $this->assertStringContainsString("0.500 0.000 0.500 rg", $output);
        $this->assertStringContainsString("100.000 100.000 re\nf", $output);
        $this->assertStringNotContainsString("/ShadingType", $output);
    }

    public function testLinearGradientShading(): void
    {
        $canvas = new CPdf([0, 0, 100, 100]);
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g">
    <stop offset="0" stop-color="red"/>
    <stop offset="0.25" stop-color="lime"/>
    <stop offset="1" stop-color="blue"/>
  </linearGradient>
  <rect width="100" height="100" fill="url(#g)" stroke="black"/>
</svg>
SVG;
        $this->render($svg, $canvas);
        $output = $canvas->output(true);

        // The shading is painted inside the shape, and defined as a resource
        $this->assertStringContainsString("100.000 100.000 re\nq\nW n", $output);
        $this->assertStringContainsString("/Sh1 sh", $output);
        $this->assertSame(1, preg_match("/\/Shading << \n\/Sh1 (\d+) 0 R/", $output, $match));
        $this->assertStringContainsString("\n$match[1] 0 obj\n<< /ShadingType 2 /ColorSpace /DeviceRGB", $output);
        $this->assertStringContainsString("/Coords [0.0000 0.0000 1.0000 0.0000] /Extend [true true]", $output);
        $this->assertStringContainsString("/Bounds [0.2500]", $output);
        $this->assertStringContainsString("/C0 [1.0000 0.0000 0.0000] /C1 [0.0000 1.0000 0.0000]", $output);
        $this->assertStringContainsString("/C0 [0.0000 1.0000 0.0000] /C1 [0.0000 0.0000 1.0000]", $output);

        // Without transparent stops, no soft mask is needed
        $this->assertStringNotContainsString("/SMask", $output);

        // The path is drawn again for the stroke
        $this->assertSame(2, substr_count($output, "100.000 100.000 re"));
        $this->assertSame(1, substr_count($output, "\nS"));
    }

    public function testRadialGradientShadingWithOpacity(): void
    {
        $canvas = new CPdf([0, 0, 100, 100]);
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <radialGradient id="g">
    <stop offset="0" stop-color="red"/>
    <stop offset="1" stop-color="blue" stop-opacity="0.5"/>
  </radialGradient>
  <path d="M 0 0 H 100 V 100 H 0 Z M 25 25 H 75 V 75 H 25 Z" fill="url(#g)" fill-rule="evenodd"/>
</svg>
SVG;
        $this->render($svg, $canvas);
        $output = $canvas->output(true);

        $this->assertStringContainsString("\nW* n", $output);
        $this->assertStringContainsString("/ShadingType 3 /ColorSpace /DeviceRGB", $output);
        $this->assertStringContainsString("/Coords [0.5000 0.5000 0.0000 0.5000 0.5000 0.5000]", $output);

        // The stop opacity is applied with a soft mask
        $this->assertSame(1, preg_match("/\/SMask << \/Type \/Mask \/S \/Luminosity \/G (\d+) 0 R >>/", $output, $match));
        $this->assertStringContainsString("\n$match[1] 0 obj\n<< /Type /XObject\n/Subtype /Form", $output);
        $this->assertStringContainsString("/ShadingType 3 /ColorSpace /DeviceGray", $output);
        $this->assertStringContainsString("/C0 [1.0000] /C1 [0.5000]", $output);
        $this->assertSame(1, preg_match("/\/GS\d+ gs\n[^\n]+ cm\n\/Sh1 sh\nQ/", $output));
    }

    public function testSpreadMethodRepeatsStops(): void
    {
        $canvas = new CPdf([0, 0, 100, 100]);
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <linearGradient id="g" x2="0.5" spreadMethod="reflect">
    <stop offset="0" stop-color="red"/>
    <stop offset="1" stop-color="blue"/>
  </linearGradient>
  <rect width="100" height="100" fill="url(#g)"/>
</svg>
SVG;
        $this->render($svg, $canvas);
        $output = $canvas->output(true);

        // Two repetitions over the width of the shape, the second one mirrored
        $this->assertStringContainsString("/Coords [0.0000 0.0000 1.0000 0.0000]", $output);
        $this->assertStringContainsString("/Bounds [0.5000]", $output);
        $this->assertStringContainsString("/C0 [1.0000 0.0000 0.0000] /C1 [0.0000 0.0000 1.0000]", $output);
        $this->assertStringContainsString("/C0 [0.0000 0.0000 1.0000] /C1 [1.0000 0.0000 0.0000]", $output);
    }
}
