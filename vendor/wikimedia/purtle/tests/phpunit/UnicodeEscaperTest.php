<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\UnicodeEscaper;

/**
 * @covers Wikimedia\Purtle\UnicodeEscaper
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class UnicodeEscaperTest extends \PHPUnit_Framework_TestCase {

	public function provideEscapeString() {
		return [
			'control characters' => [
				"\x00...\x08\x0B\x0C\x0E...\x19",
				'\u0000...\u0008\u000B\u000C\u000E...\u0019'
			],
			'whitespace' => [
				" \t\n\r",
				' \t\n\r'
			],
			'non-special ASCII characters' => [
				'!#$%&\'()*+,-./0...9:;<=>?@A...Z[\\]^_`a...z{|}~',
				'!#$%&\'()*+,-./0...9:;<=>?@A...Z[\\]^_`a...z{|}~'
			],
			// No longer quoting double quote - this leads to double-quoting on NTriples
			// Encompassing format should quote it instead
			'double quote' => [
				'"',
				'"'
			],
			'4-digit hex below U+10000' => [
				"\x7F...\xEF\xBF\xBF",
				'\u007F...\uFFFF'
			],
			'8-digit hex below U+110000' => [
				"\xF0\x90\x80\x80...\xF4\x8F\xBF\xBF",
				'\U00010000...\U0010FFFF'
			],
			'ignore U+110000 and above' => [
				"\xF4\x8F\xBF\xC0",
				''
			],
			[
				'Hello World',
				'Hello World'
			],
			[
				"Hello\nWorld",
				'Hello\nWorld'
			],
			[
				'Здравствулте мир',
				'\u0417\u0434\u0440\u0430\u0432\u0441\u0442\u0432\u0443\u043B\u0442\u0435 '
				. '\u043C\u0438\u0440'
			],
			[
				'여보세요 세계',
				'\uC5EC\uBCF4\uC138\uC694 \uC138\uACC4'
			],
			[
				'你好世界',
				'\u4F60\u597D\u4E16\u754C'
			],
			[
				"\xF0\x90\x8C\x80\xF0\x90\x8C\x81\xF0\x90\x8C\x82",
				'\U00010300\U00010301\U00010302'
			]
		];
	}

	/**
	 * @dataProvider provideEscapeString
	 */
	public function testEscapeString( $input, $expected ) {
		$escaper = new UnicodeEscaper();
		$this->assertSame( $expected, $escaper->escapeString( $input ) );
	}

}
