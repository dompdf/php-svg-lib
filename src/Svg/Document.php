<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg;

use Svg\Surface\SurfaceInterface;
use Svg\Tag\AbstractTag;
use Svg\Tag\Anchor;
use Svg\Tag\Circle;
use Svg\Tag\Ellipse;
use Svg\Tag\Group;
use Svg\Tag\Image;
use Svg\Tag\Line;
use Svg\Tag\LinearGradient;
use Svg\Tag\Path;
use Svg\Tag\Polygon;
use Svg\Tag\Polyline;
use Svg\Tag\Rect;
use Svg\Tag\Stop;
use Svg\Tag\Text;

class Document extends AbstractTag
{
    protected $filename;
    protected $inDefs = false;

    protected $x;
    protected $y;
    protected $width;
    protected $height;

    protected $subPathInit;
    protected $pathBBox;
    protected $viewBox;

    /** @var SurfaceInterface */
    protected $surface;

    /** @var AbstractTag[] */
    protected $stack = array();

    public function loadFile($filename)
    {
        $this->filename = $filename;
    }

    public function __construct() {
        $this->setStyle(new DefaultStyle());
    }

    /**
     * @return SurfaceInterface
     */
    public function getSurface()
    {
        return $this->surface;
    }

    public function getStack()
    {
        return $this->stack;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function render(SurfaceInterface $surface)
    {
        $this->surface = $surface;

        $this->inDefs = false;
        $parser = xml_parser_create("utf-8");
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler(
            $parser,
            array($this, "_tagStart"),
            array($this, "_tagEnd")
        );
        xml_set_character_data_handler(
            $parser,
            array($this, "_charData")
        );

        $fp = fopen($this->filename, "r");
        while ($line = fread($fp, 8192)) {
            xml_parse($parser, $line, false);
        }

        xml_parse($parser, "", true);
    }

    protected function svgOffset($attributes)
    {
        $this->attributes = $attributes;

        if (isset($attributes['viewBox'])) {
            $viewBox = preg_split('/[\s,]+/is', trim($attributes['viewBox']));
            if (count($viewBox) == 4) {
                $this->x = $viewBox[0];
                $this->y = $viewBox[1];
                $this->width = $viewBox[2];
                $this->height = $viewBox[3];
            }
        }
    }

    private function _tagStart($parser, $name, $attributes)
    {
        $this->x = 0;
        $this->y = 0;

        $tag = null;

        switch (strtolower($name)) {
            case 'defs':
                $this->inDefs = true;
                return;

            case 'svg':
                $tag = $this;
                $this->svgOffset($attributes);
                break;

            case 'path':
                $tag = new Path($this);
                break;

            case 'rect':
                $tag = new Rect($this);
                break;

            case 'circle':
                $tag = new Circle($this);
                break;

            case 'ellipse':
                $tag = new Ellipse($this);
                break;

            case 'image':
                $tag = new Image($this);
                break;

            case 'line':
                $tag = new Line($this);
                break;

            case 'polyline':
                $tag = new Polyline($this);
                break;

            case 'polygon':
                $tag = new Polygon($this);
                break;

            case 'lineargradient':
                $tag = new LinearGradient($this);
                break;

            case 'radialgradient':
                $tag = new LinearGradient($this);
                break;

            case 'stop':
                $tag = new Stop($this);
                break;

            case 'a':
                $tag = new Anchor($this);
                break;

            case 'g':
                $tag = new Group($this);
                break;

            case 'text':
                $tag = new Text($this);
                break;
        }

        if ($tag) {
            $this->stack[] = $tag;
            $tag->handle($attributes);
        } else {
            echo "Unknown: '$name'\n";
        }
    }

    function _charData($parser, $data)
    {
        $stack_top = end($this->stack);

        if ($stack_top instanceof Text) {
            $stack_top->appendText($data);
        }
    }

    function _tagEnd($parser, $name)
    {
        /** @var AbstractTag $tag */
        $tag = null;
        switch (strtolower($name)) {
            case 'defs':
                $this->inDefs = false;
                return;

            case 'svg':
            case 'path':
            case 'rect':
            case 'circle':
            case 'ellipse':
            case 'image':
            case 'line':
            case 'polyline':
            case 'polygon':
            case 'radialgradient':
            case 'lineargradient':
            case 'stop':
            case 'text':
            case 'g':
            case 'a':
                $tag = array_pop($this->stack);
                break;
        }

        if ($tag) {
            $tag->handleEnd();
        }
    }
} 