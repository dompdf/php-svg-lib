<?php
/**
 * Created by PhpStorm.
 * User: Fabien
 * Date: 13/04/14
 * Time: 17:42
 */

namespace Svg\Tests;

use Svg\Style;
use PHPUnit\Framework\TestCase;

class StyleTest extends TestCase
{

    public function test_parseColor()
    {
        $this->assertEquals("none", Style::parseColor("none"));
        $this->assertEquals("currentcolor", Style::parseColor("currentcolor"));
        $this->assertEquals(array(0.0, 0.0, 0.0, 0.0), Style::parseColor("transparent"));
        $this->assertEquals(array(255.0, 0.0, 0.0, 1.0), Style::parseColor("RED"));
        $this->assertEquals(array(0.0, 0.0, 255.0, 1.0), Style::parseColor("blue"));
        $this->assertEquals(array(0.0, 0.0, 0.0, 1.0), Style::parseColor("black"));
        $this->assertEquals(array(255.0, 255.0, 255.0, 1.0), Style::parseColor("white"));

        $this->assertEquals(null, Style::parseColor("foo"));

        $this->assertEquals(array(0.0, 0.0, 0.0, 1.0), Style::parseColor("#000000"));
        $this->assertEquals(array(255.0, 255.0, 255.0, 1.0), Style::parseColor("#ffffff"));
        $this->assertEquals(array(0.0, 0.0, 0.0, .5), Style::parseColor("#00000080"));

        $this->assertEquals(array(0.0, 0.0, 0.0, 1.0), Style::parseColor("rgb(0,0,0)"));
        $this->assertEquals(array(255.0, 255.0, 255.0, 1.0), Style::parseColor("rgb(255,255,255)"));
        $this->assertEquals(array(0.0, 0.0, 0.0, 1.0), Style::parseColor("rgb(0, 0, 0)"));
        $this->assertEquals(array(255.0, 255.0, 255.0, 1.0), Style::parseColor("rgb(255, 255, 255)"));
        $this->assertEquals(array(255.0, 255.0, 255.0, .5), Style::parseColor("rgb(255, 255, 255, .5)"));

        $this->assertEquals(array(255.0, 0.0, 0.0, 1.0), Style::parseColor("hsl(0, 100%, 50%)"));
        $this->assertEquals(array(255.0, 0.0, 0.0, .5), Style::parseColor("hsl(0, 100%, 50%, .5)"));
    }

    public function test_fromAttributes()
    {
        $style = new Style();

        $attributes = array(
            "color" => "blue",
            "fill" => "#fff",
            "stroke" => "none",
        );

        $style->fromAttributes($attributes);

        $this->assertEquals(array(0.0, 0.0, 255.0, 1.0), $style->color);
        $this->assertEquals(array(255.0, 255.0, 255.0, 1.0), $style->fill);
        $this->assertEquals("none", $style->stroke);
    }

}
 
