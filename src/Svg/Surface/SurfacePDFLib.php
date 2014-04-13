<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg\Surface;

use Svg\Style;
use Svg\TextStyle;

class SurfacePDFLib implements SurfaceInterface
{
    const DEBUG = false;

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

        $pdf = new \PDFlib();

        /* all strings are expected as utf8 */
        $pdf->set_option("stringformat=utf8");
        $pdf->set_option("errorpolicy=return");

        /*  open new PDF file; insert a file name to create the PDF on disk */
        if ($pdf->begin_document("", "") == 0) {
            die("Error: " . $pdf->get_errmsg());
        }
        $pdf->set_info("Creator", "PDFlib starter sample");
        $pdf->set_info("Title", "starter_graphics");

        $pdf->begin_page_ext($this->width, $this->height, "");

        $this->canvas = $pdf;
    }

    function out()
    {
        if (self::DEBUG) {
            echo __FUNCTION__ . "\n";
        }
        $this->canvas->end_page_ext("");
        $this->canvas->end_document("");

        return $this->canvas->get_buffer();
    }

    public function save()
    {
        if (self::DEBUG) {
            echo __FUNCTION__ . "\n";
        }
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
        $this->canvas->scale($x, $y);
    }

    public function rotate($angle)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->rotate($angle);
    }

    public function translate($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->translate($x, -$y);
    }

    public function transform($a, $b, $c, $d, $e, $f)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        var_dump(func_get_args());
        $this->canvas->concat($a, -$b, -$c, $d, $e, $f - $this->height);
    }

    public function setTransform($a, $b, $c, $d, $e, $f)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->setmatrix($a, -$b, -$c, $d, $e, -$f);
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
        $this->canvas->fill_stroke();
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

            $image = tempnam("", "svg");
            file_put_contents($image, $data);
        }

        $img = $this->canvas->load_image("auto", $image, "");

        $sy = $this->y($sy) - $sh;
        $this->canvas->fit_image($img, $sx, $sy, 'boxsize={' . "$sw $sh" . '} fitmethod=entire');
    }

    public function lineTo($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->lineto($x, $this->y($y));
    }

    public function moveTo($x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->moveto($x, $this->y($y));
    }

    public function quadraticCurveTo($cpx, $cpy, $x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        // TODO: Implement quadraticCurveTo() method.
    }

    public function bezierCurveTo($cp1x, $cp1y, $cp2x, $cp2y, $x, $y)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->curveto($cp1x, $this->y($cp1y), $cp2x, $this->y($cp2y), $x, $this->y($y));
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
        $this->canvas->circle($x, $this->y($y), $radius);
    }

    public function ellipse($x, $y, $radiusX, $radiusY, $rotation, $startAngle, $endAngle, $anticlockwise)
    {
        if (self::DEBUG) echo __FUNCTION__ . "\n";
        $this->canvas->ellipse($x, $this->y($y), $radiusX, $radiusY);
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
            $canvas->setcolor("stroke", "rgb", $stroke[0] / 255, $stroke[1] / 255, $stroke[2] / 255, null);
        }

        if ($fill = $style->fill) {
            $canvas->setcolor("fill", "rgb", $fill[0] / 255, $fill[1] / 255, $fill[2] / 255, null);
        }

        $opts = array();
        if ($style->strokeWidth > 0.000001) {
            $opts[] = "linewidth=$style->strokeWidth";
        }

        if (in_array($style->strokeLinecap, array("butt", "round", "projecting"))) {
            $opts[] = "linecap=$style->strokeLinecap";
        }

        if (in_array($style->strokeLinejoin, array("miter", "round", "bevel"))) {
            $opts[] = "linejoin=$style->strokeLinejoin";
        }

        $canvas->set_graphics_option(implode(" ", $opts));

        $font = $this->getFont($style->fontFamily, $style->fontStyle);
        $canvas->setfont($font, $style->fontSize);
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

        return $this->canvas->load_font($family, "unicode", "fontstyle=$style");
    }
}