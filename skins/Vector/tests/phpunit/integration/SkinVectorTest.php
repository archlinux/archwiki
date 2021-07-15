<?php
namespace MediaWiki\Skins\Vector\Tests\Integration;

use MediaWikiIntegrationTestCase;
use RequestContext;
use SkinVector;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * Class VectorTemplateTest
 * @package MediaWiki\Skins\Vector\Tests\Unit
 * @group Vector
 * @group Skins
 *
 * @coversDefaultClass \SkinVector
 */
class SkinVectorTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return \VectorTemplate
	 */
	private function provideVectorTemplateObject() {
		$template = new SkinVector( [ 'name' => 'vector' ] );
		return $template;
	}

	/**
	 * @param string $nodeString an HTML of the node we want to verify
	 * @param string $tag Tag of the element we want to check
	 * @param string $attribute Attribute of the element we want to check
	 * @param string $search Value of the attribute we want to verify
	 * @return bool
	 */
	private function expectNodeAttribute( $nodeString, $tag, $attribute, $search ) {
		$node = new \DOMDocument();
		$node->loadHTML( $nodeString );
		$element = $node->getElementsByTagName( $tag )->item( 0 );
		if ( !$element ) {
			return false;
		}

		$values = explode( ' ', $element->getAttribute( $attribute ) );
		return in_array( $search, $values );
	}

	/**
	 * @covers ::getTemplateData
	 */
	public function testGetTemplateData() {
		$title = Title::newFromText( 'SkinVector' );
		$context = RequestContext::getMain();
		$context->setTitle( $title );
		$context->setLanguage( 'fr' );
		$vectorTemplate = $this->provideVectorTemplateObject();
		$this->setTemporaryHook( 'PersonalUrls', [
			function ( &$personal_urls, &$title, $skin ) {
				$personal_urls = [
					'pt-1' => [ 'text' => 'pt1' ],
				];
			}
		] );
		$this->setTemporaryHook( 'SkinTemplateNavigation', [
			function ( &$skinTemplate, &$content_navigation ) {
				$content_navigation['actions'] = [
					'action-1' => []
				];
				$content_navigation['namespaces'] = [
					'ns-1' => []
				];
				$content_navigation['variants'] = [
					'variant-1' => []
				];
				$content_navigation['views'] = [];
			}
		] );
		$openVectorTemplate = TestingAccessWrapper::newFromObject( $vectorTemplate );

		$props = $openVectorTemplate->getTemplateData()['data-portlets'];
		$views = $props['data-views'];
		$namespaces = $props['data-namespaces'];

		$this->assertSame(
			[
				// Provided by core
				'id' => 'p-views',
				'class' => 'mw-portlet mw-portlet-views emptyPortlet vector-menu vector-menu-tabs',
				'html-tooltip' => '',
				'html-items' => '',
				'html-after-portal' => '',
				'label' => $context->msg( 'views' )->text(),
				'heading-class' => 'vector-menu-heading',
				'is-dropdown' => false,
			],
			$views
		);

		$variants = $props['data-variants'];
		$actions = $props['data-actions'];
		$this->assertSame(
			'mw-portlet mw-portlet-namespaces vector-menu vector-menu-tabs',
			$namespaces['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-variants vector-menu vector-menu-dropdown',
			$variants['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-cactions vector-menu vector-menu-dropdown',
			$actions['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-personal vector-menu',
			$props['data-personal']['class']
		);
	}

}
