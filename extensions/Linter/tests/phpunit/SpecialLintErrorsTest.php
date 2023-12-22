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
 */

namespace MediaWiki\Linter\Test;

use ContentHandler;
use Exception;
use MediaWiki\Linter\CategoryManager;
use MediaWiki\Linter\Database;
use MediaWiki\Linter\RecordLintJob;
use MediaWiki\Linter\SpecialLintErrors;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\Linter\SpecialLintErrors
 *
 * @group Database
 */
class SpecialLintErrorsTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return new SpecialLintErrors();
	}

	public function testExecute() {
		$category = ( new CategoryManager() )->getVisibleCategories()[0];

		// Basic
		$html = $this->executeSpecialPage( '', null, 'qqx' )[0];
		$this->assertStringContainsString( '(linterrors-summary)', $html );
		$this->assertStringContainsString( "(linter-category-$category)", $html );

		$this->assertStringContainsString(
			"(linter-category-$category-desc)",
			$this->executeSpecialPage( $category, null, 'qqx' )[0]
		);

		$this->overrideConfigValue( 'LinterUserInterfaceTagAndTemplateStage', true );
		// Verify new tag and template interfaces are present
		$html = $this->executeSpecialPage( 'misnested-tag', null, 'qqx' )[0];
		$this->assertStringContainsString( 'linter-form-template', $html );
		$this->assertStringContainsString( 'linter-form-tag', $html );
	}

	/**
	 * @param string $titleText
	 * @param int|null $ns
	 * @return array
	 */
	private function createTitleAndPage(
		string $titleText = 'SpecialLintErrorsTest test page',
		?int $ns = null
	): array {
		$ns ??= $this->getDefaultWikitextNS();
		$title = Title::newFromText( $titleText, $ns );
		$page = $this->getExistingTestPage( $title );

		return [
			'title' => $title,
			'pageID' => $page->getRevisionRecord()->getPageId(),
			'revID' => $page->getRevisionRecord()->getID(),
			'page' => $page,
		];
	}

	public function testContentModelChange() {
		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => [],
			'dbid' => null,
		];
		$titleAndPage = $this->createTitleAndPage();
		$job = new RecordLintJob( $titleAndPage['title'], [
			'errors' => [ $error ],
			'revision' => $titleAndPage['revID']
		] );
		$this->assertTrue( $job->run() );

		$db = new Database( $titleAndPage['pageID'] );

		$errorsFromDb = array_values( $db->getForPage() );
		$this->assertCount( 1, $errorsFromDb );

		$cssText = 'css content model change test page content';
		$content = ContentHandler::makeContent(
			$cssText,
			$titleAndPage['title'],
			'css'
		);
		$page = $titleAndPage['page'];
		$this->editPage(
			$page,
			$content,
			"update with css content model to trigger onRevisionFromEditComplete hook"
		);

		$errorsFromDb = array_values( $db->getForPage() );
		$this->assertCount( 0, $errorsFromDb );
	}

	public function testContentModelChangeWithBlankPage() {
		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => [],
			'dbid' => null,
		];
		$titleAndPage = $this->createTitleAndPage();
		$job = new RecordLintJob( $titleAndPage['title'], [
			'errors' => [ $error ],
			'revision' => $titleAndPage['revID']
		] );
		$this->assertTrue( $job->run() );

		$db = new Database( $titleAndPage['pageID'] );

		$errorsFromDb = array_values( $db->getForPage() );
		$this->assertCount( 1, $errorsFromDb );

		// This test recreates the bug mentioned in T280193 of not
		// calling the onRevisionFromEditComplete hook with the "mw-contentmodelchange"
		// tag set when the new content text is literally blank.
		$blankText = '';
		$content = ContentHandler::makeContent(
			$blankText,
			$titleAndPage['title'],
			'text'
		);
		$page = $titleAndPage['page'];
		$this->editPage(
			$page,
			$content,
			"update with blank text content model to trigger onRevisionFromEditComplete hook"
		);

		$errorsFromDb = array_values( $db->getForPage() );
		$this->assertCount( 0, $errorsFromDb );
	}

	/**
	 * @param array $pageData
	 */
	private function createPagesWithLintErrorsFromData( array $pageData ) {
		foreach ( $pageData as $data ) {
			$titleAndPage = $this->createTitleAndPage( $data[ 'name' ], $data[ 'ns' ] );
			$errors = [];
			foreach ( $data[ 'lintErrors' ] as $lintError ) {
				$errors[] = [
					'type' => $lintError[ 'type' ],
					'location' => $lintError[ 'location' ],
					'params' => [],
					'dbid' => null
				];
			}
			$job = new RecordLintJob( $titleAndPage[ 'title' ], [
				'errors' => $errors,
				'revision' => $titleAndPage[ 'revID' ]
			] );
			$job->run();
		}
	}

	/**
	 * @return array
	 */
	private function createTitleAndPageAndLintErrorData(): array {
		$pageData = [];
		$pageData[] = [ 'name' => 'Lint Error One', 'ns' => 0,
			'lintErrors' => [
				[ 'type' => 'obsolete-tag', 'location' => [ 0, 10 ] ],
				[ 'type' => 'misnested-tag', 'location' => [ 20, 30 ] ]
			]
		];
		$pageData[] = [ 'name' => 'LintErrorTwo', 'ns' => 3,
			'lintErrors' => [ [ 'type' => 'obsolete-tag', 'location' => [ 0, 10 ] ] ]
		];
		$pageData[] = [ 'name' => 'NotANamespace:LintErrorThree', 'ns' => 0,
			'lintErrors' => [
				[ 'type' => 'obsolete-tag', 'location' => [ 0, 10 ] ],
				[ 'type' => 'misnested-tag', 'location' => [ 20, 30 ] ]
			]
		];
		$pageData[] = [ 'name' => 'NotANamespace:LintErrorFour', 'ns' => 0,
			'lintErrors' => [
				[ 'type' => 'obsolete-tag', 'location' => [ 0, 10 ] ],
				[ 'type' => 'misnested-tag', 'location' => [ 20, 30 ] ]
			]
		];
		$pageData[] = [ 'name' => 'Some other page', 'ns' => 0,
			'lintErrors' => [ [ 'type' => 'bogus-image-options', 'location' => [ 30, 40 ] ] ]
		];
		$pageData[] = [ 'name' => 'FooBar:ErrorFive', 'ns' => 3,
			'lintErrors' => [ [ 'type' => 'obsolete-tag', 'location' => [ 0, 10 ] ] ]
		];
		return $pageData;
	}

	// drop-down namespaces specified: all, Talk and User talk (defined in config as null, int 0, 1 and 3)
	// namespace invert: true and false, some true cases make no sense like invert against all namespaces
	// Titles exact matched and searched by tests include: empty - "", "L", "Lint Error One", "User Talk:L",
	// "User talk:LintErrorTwo", "NotANamespace:L", "NotANamespace:LintErrorThree", "NotANamespace:LintErrorFour"
	//
	// Tests are grouped into three categories: empty title for all tests, namespace all for half and User talk for
	// the rest, with invert and exact match booleans cycling.
	//
	// The second group is similar, the title being either "NotANamespace:L" or "NotANamespace:LintErrorThree" or
	// "NotANamespace:LintErrorFour" with half namespace set all or User talk and invert and exact match booleans
	// cycling.
	//
	// The third group is composed of tests against main, talk, User Talk and all namespaces. This test also includes
	// titles with and without namespace prefixes, some which match the drop-down namespace and some which conflict
	// depending on the invert setting and the combination of namespace definitions.
	//
	// The forth test covers the use of ':title' (main namespace) as the search text to ensure 'all' and 'main'
	// are handled properly.
	//
	// The fifth test covers the user of an editor defined, non wiki defined namespace, without a namespace ID, but
	// which was created in the User_talk wiki defined namespace ID 3.
	//
	// The sixth test covers accessing the search mechanism through the misnested-tag subpage. It verifies that
	// LintErrorTwo, which has no misnested-tag errors is not in any search results, but other searches are as expected.

	/**
	 * @param string|null $subpage
	 * @return array
	 */
	private function createLinterSearchTestConfigurations( ?string $subpage ): array {
		$testConfigurations = [];
		if ( $subpage !== 'misnested-tag' ) {
			$testConfigurations[ 1 ] = [
				'namespaces' => [ 0, 3 ],
				'titles' => [ '' ],
				'cases' => [ [ 'iterations' => [ 0, 1, 2, 3, 4, 5, 6, 7 ], 'message' => 'linter-invalid-title' ]
				]
			];
			$testConfigurations[ 2 ] = [
				'namespaces' => [ 0, 3 ],
				'titles' => [ 'NotANamespace:L', 'NotANamespace:LintErrorFour' ],
				'cases' => [
					[ 'iterations' => [ 1, 11 ], 'message' => 'NotANamespace:LintErrorThree' ],
					[ 'iterations' => [ 1, 4, 5, 11, 14, 15 ], 'message' => 'NotANamespace:LintErrorFour' ],
					[ 'iterations' => [ 0, 2, 3, 6, 7, 8, 9, 10, 12, 13 ], 'message' => 'table_pager_empty' ]
				]
			];
			$testConfigurations[ 3 ] = [
				'namespaces' => [ 0, 1, 3, null ],
				'titles' => [ 'L', 'Lint Error One', 'LintErrorTwo', 'User talk:L', 'User talk:LintErrorTwo',
					'Talk:L' ],
				'cases' => [
					[ 'iterations' => [ 1, 4, 5, 27, 30, 31, 47, 51, 54, 55, 63, 73, 76, 77, 87, 95 ],
						'message' => 'Lint Error One' ],
					[ 'iterations' => [ 3, 10, 11, 27, 34, 35, 47, 49, 56, 57, 61, 64, 65, 73, 80, 81, 85, 88, 89, 95 ],
						'message' => 'LintErrorTwo' ],
					[ 'iterations' => [ 0, 2, 6, 7, 8, 9, 24, 25, 26, 28, 29, 32, 33, 44, 45, 46, 48, 50, 52, 53, 59,
						58, 60, 62, 66, 67, 72, 84, 86, 90, 91, 92, 93, 94 ],
						'message' => 'table_pager_empty' ],
					[ 'iterations' => [ 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 36, 37, 38, 39, 40, 41, 42,
						43, 68, 69, 70, 71 ],
						'message' => 'linter-namespace-mismatch' ],
					[ 'iterations' => [ 74, 75, 78, 79, 82, 83 ],
						'message' => 'linter-namespace-invert-error' ]
				]
			];
			$testConfigurations[ 4 ] = [
				'namespaces' => [ 0, 3, null ],
				'titles' => [ ':Lint Error One' ],
				'cases' => [
					[ 'iterations' => [ 0, 1, 8, 9 ],
						'message' => 'Lint Error One' ],
					[ 'iterations' => [ 2, 3, 10, 11 ],
						'message' => 'table_pager_empty' ],
					[ 'iterations' => [ 4, 5, 6, 7 ],
						'message' => 'linter-namespace-mismatch' ]
				]
			];
			$testConfigurations[ 5 ] = [
				'namespaces' => [ 0, 3, null ],
				'titles' => [ 'FooBar:ErrorFive' ],
				'cases' => [
					[ 'iterations' => [ 2, 3, 4, 5, 8, 9 ],
						'message' => 'FooBar:ErrorFive' ],
					[ 'iterations' => [ 0, 1, 6, 7 ],
						'message' => 'table_pager_empty' ],
					[ 'iterations' => [ 10, 11 ],
					'message' => 'linter-namespace-invert-error' ]
				]
			];
		} else {
			$testConfigurations[ 6 ] = [
				'namespaces' => [ 0, 3 ],
				'titles' => [ 'L', 'Lint Error One', "NotANamespace:L" ],
				'cases' => [
					[ 'iterations' => [ 1, 4, 5, 15, 18, 19 ], 'message' => 'title="Lint Error One">' ],
					[ 'iterations' => [], 'message' => 'title="LintErrorTwo">' ],
					[ 'iterations' => [ 9, 23 ], 'message' => 'title="NotANamespace:LintErrorThree">' ],
					[ 'iterations' => [ 9, 23 ], 'message' => 'title="NotANamespace:LintErrorFour">' ],
					[ 'iterations' => [ 0, 2, 3, 6, 7, 8, 10, 11, 12, 13, 14, 16, 17, 20, 21, 22 ],
						'message' => '(table_pager_empty)' ]
				]
			];
		}
		return $testConfigurations;
	}

	/**
	 * @param array $testConfig
	 * @param string|null $subPage
	 * @param string $titleSearchString
	 * @return void
	 * @throws Exception
	 */
	private function performLinterSearchTests( array $testConfig, ?string $subPage, string $titleSearchString ): void {
		foreach ( $testConfig as $configIndex => $group ) {
			$testIndex = 0;
			foreach ( $group[ 'namespaces' ] as $namespace ) {
				foreach ( $group[ 'titles' ] as $title ) {
					$invert = false;
					do {
						$exact = true;
						do {
							$params = [ 'namespace' => $namespace, 'invert' => $invert,
								$titleSearchString => $title, 'exactmatch' => $exact ];
							$webRequest = new FauxRequest( $params );

							$html = $this->executeSpecialPage( $subPage, $webRequest, 'qqx' )[ 0 ];
							foreach ( $group[ 'cases' ] as $cases ) {
								$invertString = [ 'unselected', 'selected' ][ $invert ];
								$exactString = [ 'prefix', 'exact' ][ $exact ];
								$message = $cases[ 'message' ];
								$descriptionNamespace = $namespace ?? 'all';
								$description = "On config [$configIndex], iteration [$testIndex] " .
									"namespace [$descriptionNamespace], invert checkbox [$invertString], " .
									"for a [$exactString] match, with search title [$title], and test text [$message] ";

								if ( in_array( $testIndex, $cases[ 'iterations' ] ) ) {
									$this->assertStringContainsString( $message, $html, $description .
										"was not found." );
								} else {
									$this->assertStringNotContainsString( $message, $html, $description .
										"was not supposed to be found." );
								}
							}
							$testIndex++;
							$exact = !$exact;
						} while ( !$exact );
						$invert = !$invert;
					} while ( $invert );
				}
			}
		}
	}

	/**
	 * @throws Exception
	 */
	public function testLinterSearchVariations(): void {
		$this->createTitleAndPage();
		$pageData = $this->createTitleAndPageAndLintErrorData();
		$this->createPagesWithLintErrorsFromData( $pageData );

		$testConfigurations = $this->createLinterSearchTestConfigurations( null );
		$this->performLinterSearchTests( $testConfigurations, null, 'titlesearch' );

		$testConfigurations = $this->createLinterSearchTestConfigurations( 'misnested-tag' );
		$this->performLinterSearchTests( $testConfigurations, 'misnested-tag', 'titlecategorysearch' );
	}
}
