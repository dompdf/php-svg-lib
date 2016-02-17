<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg\Tag;

class Text extends Shape
{
    protected $x = 0;
    protected $y = 0;
    protected $text = "";

    public function start($attributes)
    {
        $document = $this->document;
        $height = $this->document->getHeight();
        $this->y = $height;

        if (isset($attributes['x'])) {
            $this->x = $attributes['x'];
        }
        if (isset($attributes['y'])) {
            $this->y = $height - $attributes['y'];
        }

        $document->getSurface()->transform(1, 0, 0, -1, 0, $height);
    }

    public function end()
    {
        $surface = $this->document->getSurface();
        $x = $this->x;
        $y = $this->y;

        $width = $surface->measureText($this->text);

        if ($surface->getStyle()->textAnchor == "middle") {
            $x -= $width / 2;
        }
        if ($surface->getStyle()->textAnchor == "end") {
            $x -= $width;
        }

        $surface->fillText($this->getText(), $x, $y);
    }

    protected function after()
    {
        $this->document->getSurface()->restore();
    }

    public function appendText($text)
    {
        $this->text .= $text;
    }

    public function getText()
    {
        return trim($this->text);
    }
} 
