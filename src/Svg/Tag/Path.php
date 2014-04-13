<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg\Tag;

class Path extends Shape
{
    public function start($attribs)
    {
        $commands = array();
        preg_match_all('/([MZLHVCSQTAmzlhvcsqta])([eE ,\-.\d]+)*/', $attribs['d'], $commands, PREG_SET_ORDER);

        $path = array();
        foreach ($commands as $c) {
            if (count($c) == 3) {
                $arguments = array();
                preg_match_all('/[\-^]?[\d.]+(e[\-]?[\d]+){0,1}/i', $c[2], $arguments, PREG_PATTERN_ORDER);

                $item = $arguments[0];
                array_unshift($item, $c[1]);
            } else {
                $item = array($c[1]);
            }

            $path[] = $item;
        }

        $surface = $this->document->getSurface();

        // From https://github.com/kangax/fabric.js/blob/master/src/shapes/path.class.js
        $current = null; // current instruction
        $previous = null;
        $x = 0; // current x
        $y = 0; // current y
        $controlX = 0; // current control point x
        $controlY = 0; // current control point y
        $tempX = null;
        $tempY = null;
        $tempControlX = null;
        $tempControlY = null;
        $l = 0; //-((this.width / 2) + $this.pathOffset.x),
        $t = 0; //-((this.height / 2) + $this.pathOffset.y),
        $methodName = null;

        foreach ($path as $current) {
            switch ($current[0]) { // first letter
                case 'l': // lineto, relative
                    $x += $current[1];
                    $y += $current[2];
                    $surface->lineTo($x + $l, $y + $t);
                    break;

                case 'L': // lineto, absolute
                    $x = $current[1];
                    $y = $current[2];
                    $surface->lineTo($x + $l, $y + $t);
                    break;

                case 'h': // horizontal lineto, relative
                    $x += $current[1];
                    $surface->lineTo($x + $l, $y + $t);
                    break;

                case 'H': // horizontal lineto, absolute
                    $x = $current[1];
                    $surface->lineTo($x + $l, $y + $t);
                    break;

                case 'v': // vertical lineto, relative
                    $y += $current[1];
                    $surface->lineTo($x + $l, $y + $t);
                    break;

                case 'V': // verical lineto, absolute
                    $y = $current[1];
                    $surface->lineTo($x + $l, $y + $t);
                    break;

                case 'm': // moveTo, relative
                    $x += $current[1];
                    $y += $current[2];
                    // draw a line if previous command was moveTo as well (otherwise, it will have no effect)
                    $methodName = ($previous && ($previous[0] === 'm' || $previous[0] === 'M'))
                        ? 'lineTo'
                        : 'moveTo';
                    $surface->$methodName($x + $l, $y + $t);
                    break;

                case 'M': // moveTo, absolute
                    $x = $current[1];
                    $y = $current[2];
                    // draw a line if previous command was moveTo as well (otherwise, it will have no effect)
                    $methodName = ($previous && ($previous[0] === 'm' || $previous[0] === 'M'))
                        ? 'lineTo'
                        : 'moveTo';
                    $surface->$methodName($x + $l, $y + $t);
                    break;

                case 'c': // bezierCurveTo, relative
                    $tempX = $x + $current[5];
                    $tempY = $y + $current[6];
                    $controlX = $x + $current[3];
                    $controlY = $y + $current[4];
                    $surface->bezierCurveTo(
                        $x + $current[1] + $l, // x1
                        $y + $current[2] + $t, // y1
                        $controlX + $l, // x2
                        $controlY + $t, // y2
                        $tempX + $l,
                        $tempY + $t
                    );
                    $x = $tempX;
                    $y = $tempY;
                    break;

                case 'C': // bezierCurveTo, absolute
                    $x = $current[5];
                    $y = $current[6];
                    $controlX = $current[3];
                    $controlY = $current[4];
                    $surface->bezierCurveTo(
                        $current[1] + $l,
                        $current[2] + $t,
                        $controlX + $l,
                        $controlY + $t,
                        $x + $l,
                        $y + $t
                    );
                    break;

                case 's': // shorthand cubic bezierCurveTo, relative

                    // transform to absolute x,y
                    $tempX = $x + $current[3];
                    $tempY = $y + $current[4];

                    // calculate reflection of previous control points
                    $controlX = $controlX ? (2 * $x - $controlX) : $x;
                    $controlY = $controlY ? (2 * $y - $controlY) : $y;

                    $surface->bezierCurveTo(
                        $controlX + $l,
                        $controlY + $t,
                        $x + $current[1] + $l,
                        $y + $current[2] + $t,
                        $tempX + $l,
                        $tempY + $t
                    );
                    // set control point to 2nd one of this command
                    // "... the first control point is assumed to be
                    // the reflection of the second control point on
                    // the previous command relative to the current point."
                    $controlX = $x + $current[1];
                    $controlY = $y + $current[2];

                    $x = $tempX;
                    $y = $tempY;
                    break;

                case 'S': // shorthand cubic bezierCurveTo, absolute
                    $tempX = $current[3];
                    $tempY = $current[4];
                    // calculate reflection of previous control points
                    $controlX = 2 * $x - $controlX;
                    $controlY = 2 * $y - $controlY;
                    $surface->bezierCurveTo(
                        $controlX + $l,
                        $controlY + $t,
                        $current[1] + $l,
                        $current[2] + $t,
                        $tempX + $l,
                        $tempY + $t
                    );
                    $x = $tempX;
                    $y = $tempY;

                    // set control point to 2nd one of this command
                    // "... the first control point is assumed to be
                    // the reflection of the second control point on
                    // the previous command relative to the current point."
                    $controlX = $current[1];
                    $controlY = $current[2];

                    break;

                case 'q': // quadraticCurveTo, relative
                    // transform to absolute x,y
                    $tempX = $x + $current[3];
                    $tempY = $y + $current[4];

                    $controlX = $x + $current[1];
                    $controlY = $y + $current[2];

                    $surface->quadraticCurveTo(
                        $controlX + $l,
                        $controlY + $t,
                        $tempX + $l,
                        $tempY + $t
                    );
                    $x = $tempX;
                    $y = $tempY;
                    break;

                case 'Q': // quadraticCurveTo, absolute
                    $tempX = $current[3];
                    $tempY = $current[4];

                    $surface->quadraticCurveTo(
                        $current[1] + $l,
                        $current[2] + $t,
                        $tempX + $l,
                        $tempY + $t
                    );
                    $x = $tempX;
                    $y = $tempY;
                    $controlX = $current[1];
                    $controlY = $current[2];
                    break;

                case 't': // shorthand quadraticCurveTo, relative

                    // transform to absolute x,y
                    $tempX = $x + $current[1];
                    $tempY = $y + $current[2];

                    if (preg_match("/[QqTt]/", $previous[0])) {
                        // If there is no previous command or if the previous command was not a Q, q, T or t,
                        // assume the control point is coincident with the current point
                        $controlX = $x;
                        $controlY = $y;
                    } else {
                        if ($previous[0] === 't') {
                            // calculate reflection of previous control points for t
                            $controlX = 2 * $x - $tempControlX;
                            $controlY = 2 * $y - $tempControlY;
                        } else {
                            if ($previous[0] === 'q') {
                                // calculate reflection of previous control points for q
                                $controlX = 2 * $x - $controlX;
                                $controlY = 2 * $y - $controlY;
                            }
                        }
                    }

                    $tempControlX = $controlX;
                    $tempControlY = $controlY;

                    $surface->quadraticCurveTo(
                        $controlX + $l,
                        $controlY + $t,
                        $tempX + $l,
                        $tempY + $t
                    );
                    $x = $tempX;
                    $y = $tempY;
                    $controlX = $x + $current[1];
                    $controlY = $y + $current[2];
                    break;

                case 'T':
                    $tempX = $current[1];
                    $tempY = $current[2];

                    // calculate reflection of previous control points
                    $controlX = 2 * $x - $controlX;
                    $controlY = 2 * $y - $controlY;
                    $surface->quadraticCurveTo(
                        $controlX + $l,
                        $controlY + $t,
                        $tempX + $l,
                        $tempY + $t
                    );
                    $x = $tempX;
                    $y = $tempY;
                    break;

                case 'a':
                    // TODO: optimize this
                    /*drawArc(
                        $surface,
                        $x + $l,
                        $y + $t,
                        array(
                            $current[1],
                            $current[2],
                            $current[3],
                            $current[4],
                            $current[5],
                            $current[6] + $x + $l,
                            $current[7] + $y + $t
                        )
                    );
                    $x += $current[6];
                    $y += $current[7];*/
                    break;

                case 'A':
                    // TODO: optimize this
                    /*drawArc(
                        $surface,
                        $x + $l,
                        $y + $t,
                        array(
                            $current[1],
                            $current[2],
                            $current[3],
                            $current[4],
                            $current[5],
                            $current[6] + $l,
                            $current[7] + $t
                        )
                    );
                    $x = $current[6];
                    $y = $current[7];*/
                    break;

                case 'z':
                case 'Z':
                    $surface->closePath();
                    break;
            }
            $previous = $current;
        }
    }
} 