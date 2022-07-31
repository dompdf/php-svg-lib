<?php

namespace Svg\Tests;

use Svg\CssLength;
use PHPUnit\Framework\TestCase;

class CssLengthTest extends TestCase
{
    public function test_toPixels()
    {
        $convert = function(string $size, float $reference = 11.0, float $dpi = 96.0) {
            return (new CssLength($size))->toPixels($reference, $dpi);
        };

        // Absolute lengths
        $this->assertEquals(1, $convert('1'));
        $this->assertEquals(10, $convert("10px"));
        $this->assertEquals((10 * 96) / 72 , $convert("10pt"));
        $this->assertEquals((10 * 72) / 72 , $convert("10pt", 11, 72));
        $this->assertEquals(8, $convert("80%", 10, 72));
        $this->assertEquals((10 * 96) / 2.54, $convert("10cm"));
        $this->assertEquals((10 * 96) / 25.4, $convert("10mm"));
        $this->assertEquals(10 * 96, $convert("10in"));
        $this->assertEquals((10 * 96) / 6, $convert("10pc"));

        // Relative lengths
        $this->assertEquals(200, $convert("10em", 20));
        $this->assertEquals(200, $convert("10ex", 20));
        $this->assertEquals(200, $convert("10ch", 20));
        $this->assertEquals(200, $convert("10rem", 20));
        $this->assertEquals(2, $convert("10vw", 20));
        $this->assertEquals(2, $convert("10vh", 20));
        $this->assertEquals(2, $convert("10vmin", 20));
        $this->assertEquals(2, $convert("10vmax", 20));
    }

    public function test_getUnit()
    {
        $this->assertEquals('em', (new CssLength('30em'))->getUnit());
        $this->assertEquals('%', (new CssLength('100%'))->getUnit());
        $this->assertEquals('vmin', (new CssLength('40vmin'))->getUnit());
        $this->assertEquals('q', (new CssLength('50Q'))->getUnit());
        $this->assertEquals('', (new CssLength('50GB'))->getUnit());
        $this->assertEquals('rem', (new CssLength('44.5435rem'))->getUnit());
    }

}
 
