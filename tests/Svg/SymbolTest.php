<?php

namespace Svg\Tests;

use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\SurfaceInterface;

class SymbolTest extends TestCase
{
    private function renderSvg($body)
    {
        $file = tempnam(sys_get_temp_dir(), 'svg-symbol-');
        file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="300">' . $body . '</svg>');
        $transforms = [];
        $surface = $this->createMock(SurfaceInterface::class);
        $surface->method('getStyle')->willReturn(new \Svg\Style());
        foreach (['translate', 'scale'] as $method) {
            $surface->method($method)->willReturnCallback(function ($x, $y) use (&$transforms, $method) {
                $transforms[] = [$method, (float) $x, (float) $y];
            });
        }
        $depth = 0;
        $surface->method('save')->willReturnCallback(function () use (&$depth) { $depth++; });
        $surface->method('restore')->willReturnCallback(function () use (&$depth) {
            $this->assertGreaterThan(0, $depth, 'A restore must have a matching save');
            $depth--;
        });
        $document = new Document();
        try {
            $document->loadFile($file);
            $document->getDimensions();
            $document->render($surface);
        } finally {
            unlink($file);
        }
        $this->assertSame(0, $depth);
        return [$document, $transforms];
    }

    public function testUseDimensionsAreAppliedPerInstance()
    {
        list($document, $transforms) = $this->renderSvg(
            '<defs><symbol id="s" viewBox="-5 -10 10 20"><rect width="10" height="20"/></symbol></defs>' .
            '<use href="#s" x="7" y="9" width="20" height="40"/>' .
            '<use href="#s" width="30px" height="60px"/>' .
            '<use href="#s"/>'
        );
        $scales = array_values(array_filter($transforms, function ($t) { return $t[0] === 'scale'; }));
        $this->assertEquals([['scale', 2, 2], ['scale', 3, 3], ['scale', 15, 15]], $scales);
        $this->assertContains(['translate', 7.0, 9.0], $transforms);
        $this->assertContains(['translate', 10.0, 20.0], $transforms);
        $this->assertArrayNotHasKey('width', $document->getDef('#s')->getAttributes());
        $this->assertArrayNotHasKey('height', $document->getDef('#s')->getAttributes());
    }

    public function testSymbolOutsideDefsPreservesTheGraphicsState()
    {
        $this->renderSvg(
            '<symbol id="s" viewBox="0 0 10 10"><rect width="10" height="10"/></symbol>' .
            '<use href="#s" width="20" height="20"/>'
        );
    }

    public function testPreserveAspectRatioAlignment()
    {
        foreach ([
            ['xMidYMid meet', 20, 40, 0, 10, 2, 2],
            ['xMaxYMax meet', 20, 40, 0, 20, 2, 2],
            ['xMidYMid slice', 20, 40, -10, 0, 4, 4],
            ['none', 20, 40, 0, 0, 2, 4],
            ['xMidYMid meet', 40, 20, 10, 0, 2, 2],
        ] as $case) {
            list($align, $width, $height, $x, $y, $sx, $sy) = $case;
            list(, $transforms) = $this->renderSvg(
                '<defs><symbol id="s" viewBox="0 0 10 10" preserveAspectRatio="' . $align . '"><rect width="10" height="10"/></symbol></defs>' .
                '<use href="#s" width="' . $width . '" height="' . $height . '"/>'
            );
            $this->assertEquals([['translate', 0, 0], ['translate', $x, $y], ['scale', $sx, $sy]], $transforms, $align);
        }
    }
}
