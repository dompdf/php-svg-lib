<?php declare(strict_types=1);

namespace Svg\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Svg\Document;
use Svg\Surface\SurfaceCpdf;
use Svg\Tag\Path;

final class PathTest extends TestCase
{
    public function commandProvider(): array
    {
        return [
            'parse a relative arc with the shorthand format' => [
                'a12.083 12.083 0 01.665 6.479',
                [
                    [
                        'a',
                        '12.083',
                        '12.083',
                        '0',
                        '0',
                        '1',
                        '.665',
                        '6.479',
                    ],
                ],
            ],
            'parse an absolute arc with the shorthand format' => [
                'A12.083 12.083 0 01.665 6.479',
                [
                    [
                        'A',
                        '12.083',
                        '12.083',
                        '0',
                        '0',
                        '1',
                        '.665',
                        '6.479',
                    ],
                ],
            ],
            'parse two relative arcs that occur in sequence' => [
                'a2.43,2.43,0,0,1,.65-1.57,5.91,5.91,0,0,0,1-2.78',
                [
                    [
                        'a',
                        '2.43',
                        '2.43',
                        '0',
                        '0',
                        '1',
                        '.65',
                        '-1.57',
                    ],
                    [
                        'a',
                        '5.91',
                        '5.91',
                        '0',
                        '0',
                        '0',
                        '1',
                        '-2.78',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider commandProvider
     * @param string $commandSequence
     * @param array $expected
     */
    public function testParseCommands(string $commandSequence, array $expected)
    {
        $result = Path::parse($commandSequence);

        $this->assertSame($expected, $result);
    }
}
