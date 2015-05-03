<pre><?php
/**
 * @package php-svg-lib
 * @link    http://github.com/PhenX/php-svg-lib
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

$file = "svg/".basename($_GET["file"]);

if (!file_exists($file)) {
    return;
}

require __DIR__."/../src/autoload.php";

ob_start();

$doc = new \Svg\Document();
$doc->loadFile($file);

$surface = new \Svg\Surface\SurfaceCpdf($doc);
//$surface = new \Svg\Surface\SurfacePDFLib($doc);

$doc->render($surface);

$pdf = $surface->out();

$out = ob_get_clean();

header("Content-Type: application/pdf");
echo $pdf;

file_put_contents("log.htm", $out);