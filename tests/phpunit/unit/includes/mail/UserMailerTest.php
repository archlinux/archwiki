<?php

class UserMailerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers UserMailer::quotedPrintable
	 */
	public function testQuotedPrintable() {
		$this->assertEquals(
			"=?UTF-8?Q?=C4=88u=20legebla=3F?=",
			UserMailer::quotedPrintable( "\xc4\x88u legebla?", "UTF-8" )
		);

		$this->assertEquals(
			"=?UTF-8?Q?F=C3=B6o=2EBar?=",
			UserMailer::quotedPrintable( "Föo.Bar", "UTF-8" )
		);

		$this->assertEquals(
			"Foo.Bar",
			UserMailer::quotedPrintable( "Foo.Bar", "UTF-8" )
		);
	}
}
