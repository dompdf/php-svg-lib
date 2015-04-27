<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg\Tag;

class Image extends AbstractTag
{
    protected $x = 0;
    protected $y = 0;
    protected $width = 0;
    protected $height = 0;
    protected $href = null;

    protected function before($attribs)
    {
        parent::before($attribs);

        $surface = $this->document->getSurface();
        $surface->save();

        $this->applyTransform($attribs);
    }

    public function start($attribs)
    {
        if (isset($attribs['x'])) {
            $this->x = $attribs['x'];
        }
        if (isset($attribs['y'])) {
            $this->y = $attribs['y'];
        }

        if (isset($attribs['width'])) {
            $this->width = $attribs['width'];
        }
        if (isset($attribs['height'])) {
            $this->height = $attribs['height'];
        }

        if (isset($attribs['xlink:href'])) {
            $this->href = $attribs['xlink:href'];
        }

        $this->document->getSurface()->drawImage($this->href, $this->x, $this->y, $this->width, $this->height);
    }

    protected function after()
    {
        $this->document->getSurface()->restore();
    }
} 