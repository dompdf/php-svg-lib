<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\CPdf;
use Svg\Surface\SurfaceCpdf;

final class TransformTest extends TestCase
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

    public static function argumentsProvider(): array
    {
        return [
            "spaces" => ["translate(50 -40)", "1.000 0.000 0.000 1.000 50.000 -40.000 cm"],
            "comma" => ["translate(50,-40)", "1.000 0.000 0.000 1.000 50.000 -40.000 cm"],
            "surrounding spaces" => ["translate( 50 , -40 )", "1.000 0.000 0.000 1.000 50.000 -40.000 cm"],
            "sign as separator" => ["translate(50-40)", "1.000 0.000 0.000 1.000 50.000 -40.000 cm"],
            "decimal point as separator" => ["scale(.5.25)", "0.500 0.000 0.000 0.250 0.000 0.000 cm"],
            "exponent" => ["translate(5e1-4E1)", "1.000 0.000 0.000 1.000 50.000 -40.000 cm"],
            "matrix" => ["matrix(1 0 0 1 50-40)", "1.000 0.000 0.000 1.000 50.000 -40.000 cm"],
        ];
    }

    /**
     * @dataProvider argumentsProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('argumentsProvider')]
    public function testTransformArguments(string $transform, string $expected): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . '<rect width="20" height="20" transform="' . $transform . '"/></svg>';

        $this->assertStringContainsString($expected, $this->render($svg));
    }
}
