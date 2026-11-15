<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\CPdf;
use Svg\Surface\SurfaceCpdf;

final class StylePrecedenceTest extends TestCase
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

    public static function precedenceProvider(): array
    {
        return [
            "presentation attribute" => ['<rect width="10" height="10" fill="#f00"/>', "1.000 0.000 0.000 rg"],
            "style sheet over presentation attribute" => [
                '<rect class="green" width="10" height="10" fill="#f00"/>',
                "0.000 1.000 0.000 rg"
            ],
            "style attribute over style sheet" => [
                '<rect class="green" width="10" height="10" fill="#f00" style="fill: #00f"/>',
                "0.000 0.000 1.000 rg"
            ],
            "style attribute over presentation attribute" => [
                '<rect width="10" height="10" fill="#f00" style="fill: #00f"/>',
                "0.000 0.000 1.000 rg"
            ],
        ];
    }

    /**
     * @dataProvider precedenceProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('precedenceProvider')]
    public function testFillPrecedence(string $element, string $expected): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . '<style>.green { fill: #0f0 }</style>' . $element . '</svg>';
        $output = $this->render($svg);

        // The default fill color, and the fill color of the element
        $this->assertSame(2, substr_count($output, " rg\n"));
        $this->assertStringContainsString($expected, $output);
    }
}
