<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg\Surface;

use Svg\Style;

class SurfaceCpdf implements SurfaceInterface
{
    const DEBUG = false;

    /** @var Cpdf */
    private $canvas;

    private $width;
    private $height;

    /** @var Style */
    private $style;

    public function __construct($w, $h)
    {
        if (self::DEBUG) {
            echo __FUNCTION__ . "\n";
        }
        $this->width = $w;
        $this->height = $h;

        $canvas = new CPdf(array(0, 0, $w, $h));

        $this->canvas = $canvas;
    }

    function out()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        return $this->canvas->output();
    }

    public function save()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->save();
    }

    public function restore()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->restore();
    }

    public function scale($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->scale($x, $y, 0, 0);
    }

    public function rotate($angle)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->rotate($angle, 0, 0);
    }

    public function translate($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->translate($x, -$y);
    }

    public function transform($a, $b, $c, $d, $e, $f)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->transform(array($a, -$b, -$c, $d, $e, -$f));
    }

    public function setTransform($a, $b, $c, $d, $e, $f)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->transform(array($a, -$b, -$c, $d, $e, -$f));
    }

    public function beginPath()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        // TODO: Implement beginPath() method.
    }

    public function closePath()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->closepath();
    }

    public function fillStroke()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->fillStroke();
    }

    public function clip()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->clip();
    }

    public function fillText($text, $x, $y, $maxWidth = null)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->set_text_pos($x, $this->y($y));
        $this->canvas->show($text);
    }

    private function y($y)
    {
        return $this->height - $y;
    }

    public function strokeText($text, $x, $y, $maxWidth = null)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        // TODO: Implement drawImage() method.
    }

    public function drawImage($image, $sx, $sy, $sw = null, $sh = null, $dx = null, $dy = null, $dw = null, $dh = null)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";

        if (strpos($image, "data:") === 0) {
            $data = substr($image, strpos($image, ";") + 1);
            if (strpos($data, "base64") === 0) {
                $data = base64_decode(substr($data, 7));
            }
        }
        else {
            $data = file_get_contents($image);
        }

        $image = tempnam("", "svg");
        file_put_contents($image, $data);

        $img = $this->image($image, $sx, $sy, $sw, $sh, "normal");


        unlink($image);
    }

    public static function getimagesize($filename)
    {
        static $cache = array();

        if (isset($cache[$filename])) {
            return $cache[$filename];
        }

        list($width, $height, $type) = getimagesize($filename);

        if ($width == null || $height == null) {
            $data = file_get_contents($filename, null, null, 0, 26);

            if (substr($data, 0, 2) === "BM") {
                $meta = unpack('vtype/Vfilesize/Vreserved/Voffset/Vheadersize/Vwidth/Vheight', $data);
                $width = (int)$meta['width'];
                $height = (int)$meta['height'];
                $type = IMAGETYPE_BMP;
            }
        }

        return $cache[$filename] = array($width, $height, $type);
    }

    function image($img, $x, $y, $w, $h, $resolution = "normal")
    {
        list($width, $height, $type) = $this->getimagesize($img);

        switch ($type) {
            case IMAGETYPE_JPEG:
                $this->canvas->addJpegFromFile($img, $x, $this->y($y) - $h, $w, $h);
                break;

            case IMAGETYPE_GIF:
            case IMAGETYPE_BMP:
                // @todo use cache for BMP and GIF
                $img = $this->_convert_gif_bmp_to_png($img, $type);

            case IMAGETYPE_PNG:
                $this->canvas->addPngFromFile($img, $x, $this->y($y) - $h, $w, $h);
                break;

            default:
        }
    }

    public function lineTo($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->lineTo($x, $this->y($y));
    }

    public function moveTo($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->moveTo($x, $this->y($y));
    }

    public function quadraticCurveTo($cpx, $cpy, $x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        // TODO: Implement quadraticCurveTo() method.
    }

    public function bezierCurveTo($cp1x, $cp1y, $cp2x, $cp2y, $x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->curveTo($cp1x, $this->y($cp1y), $cp2x, $this->y($cp2y), $x, $this->y($y));
    }

    public function arcTo($x1, $y1, $x2, $y2, $radius)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
    }

    public function arc($x, $y, $radius, $startAngle, $endAngle, $anticlockwise = false)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->arc($x, $this->y($y), $radius, $startAngle, $endAngle);
    }

    public function circle($x, $y, $radius)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->ellipse($x, $this->y($y), $radius, $radius, 0, 8, 0, 360, true, false, false, false);
    }

    public function ellipse($x, $y, $radiusX, $radiusY, $rotation, $startAngle, $endAngle, $anticlockwise)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->ellipse($x, $this->y($y), $radiusX, $radiusY, 0, 8, 0, 360, false, false, false, false);
    }

    public function fillRect($x, $y, $w, $h)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->rect($x, $this->y($y), $w, $h);
        $this->fill();
    }

    public function rect($x, $y, $w, $h)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->rect($x, $this->y($y), $w, -$h);
    }

    public function fill()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->fill();
    }

    public function strokeRect($x, $y, $w, $h)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->rect($x, $this->y($y), $w, $h);
        $this->stroke();
    }

    public function stroke()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->stroke();
    }

    public function endPath()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->endPath();
    }

    public function measureText($text)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $style = $this->getStyle();
        $font = $this->getFont($style->fontFamily, $style->fontStyle);

        return $this->canvas->stringwidth($text, $font, $this->getStyle()->fontSize);
    }

    public function getStyle()
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";

        return $this->style;
    }

    public function setStyle(Style $style)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";

        $this->style = $style;
        $canvas = $this->canvas;

        if ($stroke = $style->stroke) {
            $canvas->setStrokeColor(array($stroke[0]/255, $stroke[1]/255, $stroke[2]/255), true);
        }

        if ($fill = $style->fill) {
            $canvas->setColor(array($fill[0]/255, $fill[1]/255, $fill[2]/255), true);
        }

        $canvas->setLineStyle(
            $style->strokeWidth,
            $style->strokeLinecap,
            $style->strokeLinejoin
        );

        //$font = $this->getFont($style->fontFamily, $style->fontStyle);
        //$canvas->setfont($font, $style->fontSize);
    }

    private function getFont($family, $style)
    {
        $map = array(
            "serif"      => "Times",
            "sans-serif" => "Helvetica",
            "fantasy"    => "Symbol",
            "cursive"    => "serif",
            "monospance" => "Courier",
        );

        $family = strtolower($family);
        if (isset($map[$family])) {
            $family = $map[$family];
        }

        //return $this->canvas->load_font($family, "unicode", "fontstyle=$style");
    }
}