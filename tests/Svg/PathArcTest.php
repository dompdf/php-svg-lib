<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Style;
use Svg\Surface\SurfaceInterface;

final class PathArcTest extends TestCase
{
    public static function arcProvider(): array
    {
        return [
            'an arc whose x and y displacements cancel out is drawn' => [
                'M 0 20 A 20 20 0 0 1 20 0',
                [[20.0, 0.0]],
            ],
            'an arc with equal displacements is drawn' => [
                'M 0 0 A 20 20 0 0 1 20 20',
                [[20.0, 20.0]],
            ],
            'a zero-length arc draws nothing' => [
                'M 10 10 A 5 5 0 0 1 10 10',
                [],
            ],
            'a rounded rectangle keeps all four corners' => [
                'M 20 0 H 60 A 20 20 0 0 1 80 20 V 60 A 20 20 0 0 1 60 80 H 20 A 20 20 0 0 1 0 60 V 20 A 20 20 0 0 1 20 0 Z',
                [[80.0, 20.0], [60.0, 80.0], [0.0, 60.0], [20.0, 0.0]],
            ],
        ];
    }

    /**
     * @param string $d
     * @param array $expectedEndPoints the end point of each arc, in order
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('arcProvider')]
    public function testArcsAreConvertedToBezierCurves(string $d, array $expectedEndPoints)
    {
        $endPoints = [];

        $surface = $this->createMock(SurfaceInterface::class);
        $surface->method('getStyle')->willReturn(new Style());
        $surface->method('bezierCurveTo')->willReturnCallback(
            function ($cp1x, $cp1y, $cp2x, $cp2y, $x, $y) use (&$endPoints) {
                $endPoints[] = [round($x, 6), round($y, 6)];
            }
        );

        $this->render(sprintf('<path d="%s"/>', $d), $surface);

        // An arc may be split into several curves; only the last one of each arc ends where the arc does.
        $arcEndPoints = array_values(array_filter(
            $endPoints,
            function ($point) use ($expectedEndPoints) {
                return in_array($point, $expectedEndPoints, true);
            }
        ));

        $this->assertSame($expectedEndPoints, $arcEndPoints);
        if ($expectedEndPoints === []) {
            $this->assertSame([], $endPoints);
        }
    }

    private function render(string $body, SurfaceInterface $surface): void
    {
        $file = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents(
            $file,
            '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">' . $body . '</svg>'
        );

        try {
            $document = new Document();
            $document->loadFile($file);
            $document->render($surface);
        } finally {
            unlink($file);
        }
    }
}
