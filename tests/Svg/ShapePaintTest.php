<?php declare(strict_types=1);

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\SurfaceInterface;

final class ShapePaintTest extends TestCase
{
    public static function paintProvider(): array
    {
        return [
            'line without stroke paints nothing' => [
                '<line x1="0" y1="20" x2="200" y2="20" stroke="none"/>',
                ['endPath'],
            ],
            'line with the default style paints nothing' => [
                '<line x1="0" y1="20" x2="200" y2="20"/>',
                ['endPath'],
            ],
            'line with a fill and no stroke paints nothing' => [
                '<line x1="0" y1="20" x2="200" y2="20" fill="red"/>',
                ['endPath'],
            ],
            'stroked line is only stroked' => [
                '<line x1="0" y1="20" x2="200" y2="20" stroke="#3366cc" stroke-width="4"/>',
                ['stroke'],
            ],
            'filled rect is filled' => [
                '<rect x="0" y="0" width="200" height="40" fill="#eeeeee"/>',
                ['fill'],
            ],
            'filled and stroked polyline is filled and stroked' => [
                '<polyline points="0,0 100,40 200,0" fill="#eeeeee" stroke="#000000"/>',
                ['fillStroke'],
            ],
        ];
    }

    /**
     * @dataProvider paintProvider
     * @param string $element
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('paintProvider')]
    public function testPaintOperations(string $element, array $expected)
    {
        $style = null;
        $calls = [];

        $surface = $this->createMock(SurfaceInterface::class);
        $surface->method('setStyle')->willReturnCallback(function ($newStyle) use (&$style) {
            $style = $newStyle;
        });
        $surface->method('getStyle')->willReturnCallback(function () use (&$style) {
            return $style;
        });
        foreach (['fill', 'stroke', 'fillStroke', 'endPath'] as $method) {
            $surface->method($method)->willReturnCallback(function () use (&$calls, $method) {
                $calls[] = $method;
            });
        }

        $file = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="40">' . $element . '</svg>');

        try {
            $document = new Document();
            $document->loadFile($file);
            $document->getDimensions();
            $document->render($surface);
        } finally {
            unlink($file);
        }

        $this->assertSame($expected, $calls);
    }
}
