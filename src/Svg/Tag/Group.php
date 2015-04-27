<?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Svg\Tag;

use Svg\Style;

class Group extends AbstractTag
{
    public function start($attribs)
    {
        $surface = $this->document->getSurface();

        $style = new Style();

        $group = $this->getParentGroup();
        if ($group) {
            $style->fromGroupAttributes($group->attributes);
        }

        $style->fromAttributes($attribs);
        $surface->setStyle($style);
    }

    protected function before($attribs)
    {
        parent::before($attribs);

        $this->document->getSurface()->save();

        $this->applyTransform($attribs);
    }

    protected function after()
    {
        $this->document->getSurface()->restore();
    }
} 