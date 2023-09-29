<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author This, that and the other
 */

use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;

/**
 * @covers SubpageImportTitleFactory
 *
 * @group Title
 *
 * TODO convert to Unit tests
 */
class SubpageImportTitleFactoryTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'en',
			MainConfigNames::NamespacesWithSubpages => [ 0 => false, 2 => true ],
		] );
	}

	private function newSubpageImportTitleFactory( Title $rootPage ) {
		return new SubpageImportTitleFactory(
			$this->getServiceContainer()->getNamespaceInfo(),
			$this->getServiceContainer()->getTitleFactory(),
			$rootPage
		);
	}

	public function basicProvider() {
		return [
			[
				new ForeignTitle( 0, '', 'MainNamespaceArticle' ),
				Title::newFromText( 'User:Graham' ),
				Title::newFromText( 'User:Graham/MainNamespaceArticle' )
			],
			[
				new ForeignTitle( 1, 'Discussion', 'Nice_talk' ),
				Title::newFromText( 'User:Graham' ),
				Title::newFromText( 'User:Graham/Discussion:Nice_talk' )
			],
			[
				new ForeignTitle( 0, '', 'Bogus:Nice_talk' ),
				Title::newFromText( 'User:Graham' ),
				Title::newFromText( 'User:Graham/Bogus:Nice_talk' )
			],
		];
	}

	/**
	 * @dataProvider basicProvider
	 */
	public function testBasic( ForeignTitle $foreignTitle, Title $rootPage,
		Title $title
	) {
		$factory = $this->newSubpageImportTitleFactory( $rootPage );
		$testTitle = $factory->createTitleFromForeignTitle( $foreignTitle );

		$this->assertTrue( $testTitle->equals( $title ) );
	}

	public function testInvalidNamespace() {
		$this->expectException( InvalidArgumentException::class );
		$this->newSubpageImportTitleFactory( Title::newFromText( 'Graham' ) );
	}
}
