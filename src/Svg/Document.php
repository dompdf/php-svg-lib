<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/dompdf/php-svg-lib
 * @license GNU LGPLv3+ http://www.gnu.org/copyleft/lesser.html
 */

namespace Svg;

use Svg\Surface\SurfaceInterface;
use Svg\Tag\AbstractTag;
use Svg\Tag\Anchor;
use Svg\Tag\Circle;
use Svg\Tag\Ellipse;
use Svg\Tag\Group;
use Svg\Tag\ClipPath;
use Svg\Tag\Image;
use Svg\Tag\Line;
use Svg\Tag\LinearGradient;
use Svg\Tag\Path;
use Svg\Tag\Polygon;
use Svg\Tag\Polyline;
use Svg\Tag\Rect;
use Svg\Tag\Stop;
use Svg\Tag\Symbol;
use Svg\Tag\Text;
use Svg\Tag\StyleTag;
use Svg\Tag\UseTag;

class Document extends AbstractTag
{
    protected $filename;
    protected $_defs_depth = 0;
    public $inDefs = false;

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

    /** @var AbstractTag[] */
    protected $defs = array();

    /** @var \Sabberworm\CSS\CSSList\Document[] */
    protected $styleSheets = array();

    /** @var array[]|null Gradients by id, collected on first use */
    protected $gradients;

    public $allowExternalReferences = true;

    public function loadFile($filename)
    {
        $this->filename = $filename;
        $this->gradients = null;
    }

    protected function initParser() {
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

        return $parser;
    }

    public function __construct() {

    }

    /**
     * Increase the nesting level for defs-like elements
     *
     * @return int
     */
    public function enterDefs () {
        $this->_defs_depth++;
        $this->inDefs = true;
        return $this->_defs_depth;
    }

    /**
     * Decrease the nesting level for defs-like elements
     *
     * @return int
     */
    public function exitDefs () {
        $this->_defs_depth--;
        if ($this->_defs_depth < 0) {
            $this->_defs_depth = 0;
        }
        $this->inDefs = ($this->_defs_depth > 0 ? true : false);
        return $this->_defs_depth;
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

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getDiagonal()
    {
        return sqrt(($this->width)**2 + ($this->height)**2) / sqrt(2);
    }

    public function getDimensions() {
        $rootAttributes = null;

        $parser = xml_parser_create("utf-8");
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler(
            $parser,
            function ($parser, $name, $attributes) use (&$rootAttributes) {
                if ($name === "svg" && $rootAttributes === null) {
                    $attributes = array_change_key_case($attributes, CASE_LOWER);

                    $rootAttributes = $attributes;
                }
            },
            function ($parser, $name) {}
        );

        $fp = fopen($this->filename, "r");
        while ($line = fread($fp, 8192)) {
            xml_parse($parser, $line, false);

            if ($rootAttributes !== null) {
                break;
            }
        }
        xml_parse($parser, "", true);

        if (PHP_MAJOR_VERSION < 8) {
            xml_parser_free($parser);
        }

        return $this->handleSizeAttributes($rootAttributes);
    }

    public function handleSizeAttributes($attributes){
        if ($this->width === null) {
            if (isset($attributes["width"])) {
                $width = $this->convertSize($attributes["width"], 400);
                $this->width  = $width;
            }

            if (isset($attributes["height"])) {
                $height = $this->convertSize($attributes["height"], 300);
                $this->height = $height;
            }

            if (isset($attributes['viewbox'])) {
                $viewBox = preg_split('/[\s,]+/is', trim($attributes['viewbox']));
                if (count($viewBox) == 4) {
                    $this->x = $viewBox[0];
                    $this->y = $viewBox[1];

                    if (!$this->width) {
                        $this->width = $viewBox[2];
                    }
                    if (!$this->height) {
                        $this->height = $viewBox[3];
                    }
                }
            }
        }

        return array(
            0        => $this->width,
            1        => $this->height,

            "width"  => $this->width,
            "height" => $this->height,
        );
    }

    public function getDocument(){
        return $this;
    }

    /**
     * Append a style sheet
     *
     * @param \Sabberworm\CSS\CSSList\Document $stylesheet
     */
    public function appendStyleSheet($stylesheet) {
        $this->styleSheets[] = $stylesheet;
    }

    /**
     * Get the document style sheets
     *
     * @return \Sabberworm\CSS\CSSList\Document[]
     */
    public function getStyleSheets() {
        return $this->styleSheets;
    }

    protected function before($attributes)
    {
        $surface = $this->getSurface();

        $style = new DefaultStyle($this);
        $style->inherit($this);
        $style->fromAttributes($attributes);

        $this->setStyle($style);

        $surface->setStyle($style);
    }

    public function render(SurfaceInterface $surface)
    {
        $this->_defs_depth = 0;
        $this->inDefs = false;
        $this->surface = $surface;

        $parser = $this->initParser();

        if ($this->x || $this->y) {
            $surface->translate(-$this->x, -$this->y);
        }

        $fp = fopen($this->filename, "r");
        while ($line = fread($fp, 8192)) {
            xml_parse($parser, $line, false);
        }

        xml_parse($parser, "", true);

        if (PHP_MAJOR_VERSION < 8) {
            xml_parser_free($parser);
        }
    }

    protected function svgOffset($attributes)
    {
        $this->attributes = $attributes;

        $this->handleSizeAttributes($attributes);
    }

    public function getDef($id) {
        $id = ltrim($id, "#");

        return isset($this->defs[$id]) ? $this->defs[$id] : null;
    }

    /**
     * Get a gradient by its paint reference
     *
     * @param mixed $ref A paint reference as returned by {@see Style::parseColor()}, e.g. `#id`
     *
     * @return array|null The gradient with keys `type` (`lineargradient` or `radialgradient`),
     *                    `attributes` (lowercase names, including inherited ones), `stops`
     *                    (list of `[offset, [r, g, b], alpha]`, values from 0 to 1), and
     *                    `color` (the average stop color as `[r, g, b]` from 0 to 255)
     */
    public function getGradient($ref)
    {
        if (!is_string($ref) || strlen($ref) < 2 || $ref[0] !== "#") {
            return null;
        }

        if ($this->gradients === null) {
            $this->gradients = $this->collectGradients();
        }

        $id = substr(explode(" ", $ref, 2)[0], 1);

        return $this->gradients[$id] ?? null;
    }

    /**
     * Collect all gradients in a separate pass, as they can be defined after
     * the elements referencing them
     *
     * @return array[]
     */
    protected function collectGradients()
    {
        $gradients = array();
        $styles = array();
        $current = null;
        $stack = array();

        $parser = xml_parser_create("utf-8");
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler(
            $parser,
            function ($parser, $name, $attributes) use (&$gradients, &$styles, &$current, &$stack) {
                $name = strtolower($name);
                $attributes = array_change_key_case($attributes, CASE_LOWER);
                $stack[] = array($name, $attributes);

                if ($name === "style") {
                    $styles[] = "";
                } elseif ($name === "lineargradient" || $name === "radialgradient") {
                    $current = $attributes["id"] ?? "";
                    $gradients[$current] = array("type" => $name, "attributes" => $attributes, "stops" => array());
                } elseif ($name === "stop" && $current !== null) {
                    // Resolved once all style sheets are known
                    $gradients[$current]["stops"][] = $stack;
                }
            },
            function ($parser, $name) use (&$current, &$stack) {
                array_pop($stack);
                $name = strtolower($name);

                if ($name === "lineargradient" || $name === "radialgradient") {
                    $current = null;
                }
            }
        );
        xml_set_character_data_handler($parser, function ($parser, $data) use (&$styles, &$stack) {
            if ($stack && end($stack)[0] === "style") {
                $styles[count($styles) - 1] .= $data;
            }
        });

        $fp = fopen($this->filename, "r");
        while ($line = fread($fp, 8192)) {
            xml_parse($parser, $line, false);
        }
        xml_parse($parser, "", true);
        fclose($fp);

        if (PHP_MAJOR_VERSION < 8) {
            xml_parser_free($parser);
        }

        // Resolve the stop properties along the ancestors of each stop
        $styleSheets = array();
        foreach ($styles as $css) {
            $styleSheets[] = (new \Sabberworm\CSS\Parser($css))->parse();
        }
        foreach ($gradients as $id => $gradient) {
            foreach ($gradient["stops"] as $i => $ancestors) {
                $props = array("color" => array(0, 0, 0, 1), "stop-color" => array(0, 0, 0, 1), "stop-opacity" => 1.0);
                foreach ($ancestors as list($name, $attributes)) {
                    $props = $this->getStopProperties($name, $attributes, $props, $styleSheets);
                }
                $offset = trim($attributes["offset"] ?? "0");
                $color = $props["stop-color"];
                $gradients[$id]["stops"][$i] = array(
                    substr($offset, -1) === "%" ? (float) $offset / 100 : (float) $offset,
                    array($color[0] / 255, $color[1] / 255, $color[2] / 255),
                    max(0, min(1, $props["stop-opacity"] * $color[3]))
                );
            }
        }

        foreach ($gradients as $id => $gradient) {
            // Inherit attributes and stops from referenced gradients
            $seen = array($id => true);
            $ref = ltrim($gradient["attributes"]["href"] ?? $gradient["attributes"]["xlink:href"] ?? "", "#");

            while (isset($gradients[$ref]) && !isset($seen[$ref])) {
                $seen[$ref] = true;
                $parent = $gradients[$ref];
                $gradient["attributes"] += $parent["attributes"];
                $gradient["stops"] = $gradient["stops"] ?: $parent["stops"];
                $ref = ltrim($parent["attributes"]["href"] ?? $parent["attributes"]["xlink:href"] ?? "", "#");
            }

            // Offsets are clamped, and never decrease
            $max = 0.0;
            foreach ($gradient["stops"] as $i => $stop) {
                $max = $gradient["stops"][$i][0] = max($max, min(1.0, $stop[0]));
            }

            $colors = array_column($gradient["stops"], 1) ?: array(array(0, 0, 0));
            $gradient["color"] = array_map(function (...$values) {
                return array_sum($values) / count($values) * 255;
            }, ...$colors);

            $gradients[$id] = $gradient;
        }

        return $gradients;
    }

    /**
     * Resolve the properties relevant for gradient stops of an element
     *
     * @param string $name        The element name
     * @param array  $attributes  The element attributes
     * @param array  $parent      The properties of the parent element
     * @param array  $styleSheets The style sheets of the document
     *
     * @return array `color` and `stop-color` as `[r, g, b, a]`, and `stop-opacity`
     */
    protected function getStopProperties($name, $attributes, array $parent, array $styleSheets)
    {
        // Presentation attributes, style sheet rules, and inline styles, in
        // increasing precedence
        $declarations = array_merge(
            $attributes,
            Style::getStyleSheetRules(new Stop($this, $name), $attributes, $styleSheets),
            Style::parseCssStyle($attributes["style"] ?? "")
        );
        $value = function ($property, $initial) use ($declarations) {
            $value = strtolower(trim($declarations[$property] ?? ""));
            return $value === "" ? $initial : $value;
        };

        // `color` is inherited, `stop-color` and `stop-opacity` only explicitly
        $color = Style::parseColor($value("color", "inherit"));
        $props = array("color" => is_array($color) ? $color : $parent["color"]);

        $stopColor = $value("stop-color", "black");
        if ($stopColor === "inherit") {
            $props["stop-color"] = $parent["stop-color"];
        } elseif ($stopColor === "currentcolor") {
            $props["stop-color"] = $props["color"];
        } else {
            $stopColor = Style::parseColor($stopColor);
            $props["stop-color"] = is_array($stopColor) ? $stopColor : array(0, 0, 0, 1);
        }

        $opacity = $value("stop-opacity", "1");
        if ($opacity === "inherit") {
            $props["stop-opacity"] = $parent["stop-opacity"];
        } else {
            $props["stop-opacity"] = substr($opacity, -1) === "%" ? (float) $opacity / 100
                : (is_numeric($opacity) ? (float) $opacity : 1.0);
        }

        return $props;
    }

    private function _tagStart($parser, $name, $attributes)
    {
        $this->x = 0;
        $this->y = 0;

        $tag = null;

        $attributes = array_change_key_case($attributes, CASE_LOWER);

        switch (strtolower($name)) {
            case 'defs':
                $this->enterDefs();
                return;

            case 'svg':
                if (count($this->attributes)) {
                    $tag = new Group($this, $name);
                }
                else {
                    $tag = $this;
                    $this->svgOffset($attributes);
                }
                break;

            case 'path':
                $tag = new Path($this, $name);
                break;

            case 'rect':
                $tag = new Rect($this, $name);
                break;

            case 'circle':
                $tag = new Circle($this, $name);
                break;

            case 'ellipse':
                $tag = new Ellipse($this, $name);
                break;

            case 'image':
                $tag = new Image($this, $name);
                break;

            case 'line':
                $tag = new Line($this, $name);
                break;

            case 'polyline':
                $tag = new Polyline($this, $name);
                break;

            case 'polygon':
                $tag = new Polygon($this, $name);
                break;

            case 'lineargradient':
                $tag = new LinearGradient($this, $name);
                break;

            case 'radialgradient':
                $tag = new LinearGradient($this, $name);
                break;

            case 'stop':
                $tag = new Stop($this, $name);
                break;

            case 'style':
                $tag = new StyleTag($this, $name);
                break;

            case 'a':
                $tag = new Anchor($this, $name);
                break;

            case 'g':
                $tag = new Group($this, $name);
                break;

            case 'symbol':
                $this->enterDefs();
                $tag = new Symbol($this, $name);
                break;
    
            case 'clippath':
                $tag = new ClipPath($this, $name);
                break;

            case 'use':
                $tag = new UseTag($this, $name);
                break;

            case 'text':
                $tag = new Text($this, $name);
                break;

            case 'desc':
                return;
        }

        if ($tag) {
            if (isset($attributes["id"])) {
                $this->defs[$attributes["id"]] = $tag;
            }
            else {
                /** @var AbstractTag $top */
                $top = end($this->stack);
                if ($top && $top != $tag) {
                    $top->children[] = $tag;
                }
            }

            $this->stack[] = $tag;

            $tag->handle($attributes);
        }
    }

    function _charData($parser, $data)
    {
        $stack_top = end($this->stack);

        if ($stack_top instanceof Text || $stack_top instanceof StyleTag) {
            $stack_top->appendText($data);
        }
    }

    function _tagEnd($parser, $name)
    {
        /** @var AbstractTag $tag */
        $tag = null;
        switch (strtolower($name)) {
            case 'defs':
                $this->exitDefs();
                return;

            case 'symbol':
                $this->exitDefs();
                $tag = array_pop($this->stack);
                break;
    
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
            case 'style':
            case 'text':
            case 'g':
            case 'clippath':
            case 'use':
            case 'a':
                $tag = array_pop($this->stack);
                break;
        }

        if ((!$this->inDefs && $tag) || $tag instanceof StyleTag) {
            $tag->handleEnd();
        }
    }
} 
