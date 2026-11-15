<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\CPdf;
use Svg\Surface\SurfaceCpdf;

final class UseTest extends TestCase
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

    public static function containerProvider(): array
    {
        return [
            "group" => ['<defs><g id="ref">%s</g></defs><use href="#ref"/>'],
            "nested groups" => ['<defs><g id="ref"><g>%s</g></g></defs><use href="#ref"/>'],
            "symbol" => ['<defs><symbol id="ref">%s</symbol></defs><use href="#ref"/>'],
        ];
    }

    /**
     * @dataProvider containerProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('containerProvider')]
    public function testReferencedShapesArePaintedSeparately(string $container): void
    {
        $shapes = '<rect width="20" height="10" fill="#f00"/><rect x="30" width="20" height="10" fill="#00f"/>';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . sprintf($container, $shapes) . '</svg>';
        $output = $this->render($svg);

        // Each shape is filled with its own color before the next one starts
        $this->assertSame(1, preg_match(
            "/1\\.000 0\\.000 0\\.000 rg[^q]*0\\.000 0\\.000 20\\.000 10\\.000 re\nf\nQ\n"
            . "q\n0\\.000 0\\.000 1\\.000 rg[^q]*30\\.000 0\\.000 20\\.000 10\\.000 re\nf\nQ/",
            $output
        ));
        $this->assertSame(substr_count($output, "\nq\n"), substr_count($output, "\nQ"));
    }
}
