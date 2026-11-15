<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/dompdf/php-svg-lib
 * @license GNU LGPLv3+ http://www.gnu.org/copyleft/lesser.html
 */

namespace Svg\Tag;

use Svg\Style;

class Shape extends AbstractTag
{
    /**
     * A focal point outside of the end circle of a radial gradient is moved
     * onto its edge (SVG 1.1). Slightly inside, as a focal point exactly on
     * the edge produces a cone in PDF shadings
     */
    const FOCAL_EDGE = 0.99;

    protected function before($attributes)
    {
        $surface = $this->document->getSurface();

        $surface->save();
        $surface->beginPath();

        $style = $this->makeStyle($attributes);

        $this->setStyle($style);
        $surface->setStyle($style);

        $this->applyTransform($attributes);
    }

    protected function after()
    {
        $surface = $this->document->getSurface();

        if ($this->hasShape) {
            $style = $surface->getStyle();

            // Gradients are only supported by surfaces that implement `fillGradient()`.
            // A gradient that is not applicable to the shape uses the fallback color,
            // if any. Without stops, it is treated like `none`
            $paints = array("fill" => null, "stroke" => null);
            if (method_exists($surface, "fillGradient")) {
                $bbox = null;
                foreach ($paints as $name => $paint) {
                    $gradient = $this->document->getGradient($style->$name);
                    if ($gradient) {
                        $bbox = $bbox ?? $surface->getPathBBox();
                        $paints[$name] = $this->resolveGradient($gradient, $bbox);
                        if (!$paints[$name] && $gradient["stops"] && strpos($style->$name, " ") !== false) {
                            $fallback = Style::parseColor(strstr($style->$name, " "));
                            $style->$name = $fallback === "currentcolor" ? $style->color : $fallback;
                            $surface->setStyle($style);
                        }
                    }
                }
            }

            $fill   = is_array($style->fill) || $paints["fill"];
            $stroke = is_array($style->stroke) || $paints["stroke"];

            if ($paints["fill"]) {
                $surface->fillGradient($paints["fill"], $stroke);
            }
            elseif ($fill) {
                if ($stroke) {
                    $surface->fillStroke(false);
                } else {
                    $surface->fill();
                }
            }
            elseif ($stroke) {
                $surface->stroke(false);
            }
            else {
                $surface->endPath();
            }
        }

        $surface->restore();
    }

    /**
     * Resolve the geometry of a gradient for the current shape
     *
     * @param array|null   $gradient The gradient, see {@see \Svg\Document::getGradient()}
     * @param float[]|null $bbox     The bounding box of the shape as `[x1, y1, x2, y2]`
     *
     * @return array|null The paint with keys `type` (2 for linear, 3 for radial), `coords`,
     *                    `stops`, `matrix` (from gradient space to user space), `spread`,
     *                    and `bbox`, or null if the gradient is not applicable
     */
    protected function resolveGradient($gradient, $bbox)
    {
        if (!$gradient || !$gradient["stops"]) {
            return null;
        }

        $attributes = $gradient["attributes"];
        $userSpace = ($attributes["gradientunits"] ?? "") === "userSpaceOnUse";
        list($x1, $y1, $x2, $y2) = $bbox;

        if ($userSpace) {
            // Percentages refer to the viewBox, or the document size
            $matrix = array(1, 0, 0, 1, 0, 0);
            $viewBox = preg_split('/[\s,]+/', trim($this->document->attributes["viewbox"] ?? ""));
            $width = (float) (count($viewBox) === 4 ? $viewBox[2] : $this->document->getWidth());
            $height = (float) (count($viewBox) === 4 ? $viewBox[3] : $this->document->getHeight());
        } else {
            // Not applicable to shapes without width or height
            if ($x2 - $x1 == 0 || $y2 - $y1 == 0) {
                return null;
            }

            $matrix = array($x2 - $x1, 0, 0, $y2 - $y1, $x1, $y1);
            $width = 1;
            $height = 1;
        }

        if (isset($attributes["gradienttransform"])) {
            $matrix = self::multiplyMatrix(self::parseTransform($attributes["gradienttransform"]), $matrix);
        }

        // Percentages refer to the bounding box or the viewport
        $length = function ($name, $default, $reference) use ($attributes) {
            return $this->convertSize(trim($attributes[$name] ?? $default), $reference);
        };

        if ($gradient["type"] === "lineargradient") {
            $type = 2;
            $coords = array(
                $length("x1", "0%", $width),
                $length("y1", "0%", $height),
                $length("x2", "100%", $width),
                $length("y2", "0%", $height)
            );
        } else {
            $type = 3;
            $diagonal = sqrt(($width ** 2 + $height ** 2) / 2);
            $cx = $length("cx", "50%", $width);
            $cy = $length("cy", "50%", $height);
            $r = $length("r", "50%", $diagonal);
            $fx = $length("fx", $attributes["cx"] ?? "50%", $width);
            $fy = $length("fy", $attributes["cy"] ?? "50%", $height);
            $fr = $length("fr", "0%", $diagonal);

            if ($r < 0) {
                return null;
            }

            // A focal point outside of the end circle is moved onto its edge
            $distance = hypot($fx - $cx, $fy - $cy);
            if ($distance > $r * self::FOCAL_EDGE && $r > 0) {
                $fx = $cx + ($fx - $cx) * $r * self::FOCAL_EDGE / $distance;
                $fy = $cy + ($fy - $cy) * $r * self::FOCAL_EDGE / $distance;
            }

            $coords = array($fx, $fy, $fr, $cx, $cy, $r);
        }

        // A gradient without extent is painted with the color of the last stop
        $stops = $gradient["stops"];
        if ($type === 2 ? $coords[0] == $coords[2] && $coords[1] == $coords[3] : $coords[5] == 0) {
            $type = 2;
            $coords = array(0, 0, 1, 0);
            $stops = array(end($stops));
        }

        return array(
            "type" => $type,
            "coords" => $coords,
            "stops" => $stops,
            "matrix" => $matrix,
            "spread" => $attributes["spreadmethod"] ?? "pad",
            "bbox" => $bbox
        );
    }

    /**
     * Parse a transform attribute into a matrix
     *
     * @param string $transform
     *
     * @return float[] The matrix as `[a, b, c, d, e, f]`
     */
    protected static function parseTransform($transform)
    {
        $matrix = array(1, 0, 0, 1, 0, 0);

        preg_match_all(
            '/(matrix|translate|scale|rotate|skewX|skewY)\s*\((.*?)\)/is',
            $transform,
            $matches,
            PREG_SET_ORDER
        );

        // The transformations are applied from right to left
        foreach (array_reverse($matches) as $match) {
            $t = array_map("floatval", preg_split('/[\s,]+/', trim($match[2])));

            switch (strtolower($match[1])) {
                case "matrix":
                    $m = array_pad($t, 6, 0.0);
                    break;
                case "translate":
                    $m = array(1, 0, 0, 1, $t[0], $t[1] ?? 0);
                    break;
                case "scale":
                    $m = array($t[0], 0, 0, $t[1] ?? $t[0], 0, 0);
                    break;
                case "rotate":
                    $cos = cos(deg2rad($t[0]));
                    $sin = sin(deg2rad($t[0]));
                    $cx = $t[1] ?? 0;
                    $cy = $t[2] ?? 0;
                    $m = array($cos, $sin, -$sin, $cos, $cx - $cos * $cx + $sin * $cy, $cy - $sin * $cx - $cos * $cy);
                    break;
                case "skewx":
                    $m = array(1, 0, tan(deg2rad($t[0])), 1, 0, 0);
                    break;
                case "skewy":
                    $m = array(1, tan(deg2rad($t[0])), 0, 1, 0, 0);
                    break;
            }

            $matrix = self::multiplyMatrix($matrix, $m);
        }

        return $matrix;
    }

    /**
     * Combine two transformation matrices
     *
     * @param float[] $m1 The matrix to apply first
     * @param float[] $m2 The matrix to apply second
     *
     * @return float[]
     */
    protected static function multiplyMatrix(array $m1, array $m2)
    {
        return array(
            $m1[0] * $m2[0] + $m1[1] * $m2[2],
            $m1[0] * $m2[1] + $m1[1] * $m2[3],
            $m1[2] * $m2[0] + $m1[3] * $m2[2],
            $m1[2] * $m2[1] + $m1[3] * $m2[3],
            $m1[4] * $m2[0] + $m1[5] * $m2[2] + $m2[4],
            $m1[4] * $m2[1] + $m1[5] * $m2[3] + $m2[5]
        );
    }
} 