<?php

use KzykHys\Pygments\Pygments;
use Symfony\Component\Finder\Finder;

/**
 * @author Kazuyuki Hayashi <hayashi@valnur.net>
 */
class PygmentsTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideSamples
     */
    public function testHighlight($input, $expected, $expectedL, $lexer)
    {
        $pygments = new Pygments();

        $this->assertEquals($expected, $pygments->highlight($input, null, 'html'));
        $this->assertEquals($expected, $pygments->highlight($input, $lexer, 'html'));
        $this->assertEquals($expectedL, $pygments->highlight($input, null, 'html', array('linenos' => 1)));
    }

    /**
     * @dataProvider provideCss
     */
    public function testGetCss($expected, $expectedA, $style)
    {
        $pygments = new Pygments();

        $this->assertEquals($expected, $pygments->getCss($style));
        $this->assertEquals($expectedA, $pygments->getCss($style, '.syntax'));
    }

    public function testGetLexers()
    {
        $pygments = new Pygments();
        $lexers = $pygments->getLexers();

        $this->assertArrayHasKey('python', $lexers);
    }

    public function testGetFormatters()
    {
        $pygments = new Pygments();
        $formatters = $pygments->getFormatters();

        $this->assertArrayHasKey('html', $formatters);
    }

    public function testGetStyles()
    {
        $pygments = new Pygments();
        $styles = $pygments->getStyles();

        $this->assertArrayHasKey('monokai', $styles);
    }

    public function testGuessLexer()
    {
        $pygments = new Pygments();

        $this->assertEquals('php', $pygments->guessLexer('index.php'));
        $this->assertEquals('go', $pygments->guessLexer('main.go'));
    }

    public function provideSamples()
    {
        $finder = new Finder();
        $finder
            ->in(__DIR__ . '/Resources/example')
            ->name("*.in")
            ->notName('*.linenos.out')
            ->files()
            ->ignoreVCS(true);

        $samples = array();

        /* @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            $samples[] = array(
                $file->getContents(),
                file_get_contents(str_replace('.in', '.out', $file->getPathname())),
                file_get_contents(str_replace('.in', '.linenos.out', $file->getPathname())),
                preg_replace('/\..*/', '', $file->getFilename())
            );
        }

        return $samples;
    }

    public function provideCss()
    {
        $finder = new Finder();
        $finder
            ->in(__DIR__ . '/Resources/css')
            ->files()
            ->ignoreVCS(true)
            ->name('*.css')
            ->notName('*.prefix.css');

        $css = array();

        /* @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            $css[] = array(
                $file->getContents(),
                file_get_contents(str_replace('.css', '.prefix.css', $file->getPathname())),
                str_replace('.css', '', $file->getFilename())
            );
        }

        return $css;
    }

} 