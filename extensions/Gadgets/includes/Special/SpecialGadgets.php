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

namespace MediaWiki\Extension\Gadgets\Special;

use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * Wrapper page for Special:Gadgets subpages
 */
class SpecialGadgets extends SpecialPage {
	/**
	 * List of subpage names to the subclass of ActionPage which handles them.
	 */
	private const SUBPAGE_LIST = [
		'list' => [
			'class' => ListPage::class,
			'services' => [
				'GadgetsRepo',
				'ContentLanguage',
				'SkinFactory'
			],
		],
		'export' => [
			'class' => ExportPage::class,
			'services' => [
				'GadgetsRepo'
			]
		]
	];

	public function __construct( private readonly ObjectFactory $objectFactory ) {
		parent::__construct( 'Gadgets' );
	}

	/**
	 * Return title for Special:Gadgets heading and Special:Specialpages link.
	 *
	 * @return Message
	 */
	public function getDescription() {
		return $this->msg( 'special-gadgets' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}

	/**
	 * @param string|null $par Parameters passed to the page
	 */
	public function execute( $par ) {
		$parts = $par ? explode( '/', $par, 1 ) : [];
		$subPage = $parts[0] ?? 'list';

		$params = explode( '/', $subPage );
		$pageName = array_shift( $params );
		$page = $this->getSubpage( $pageName );
		if ( !$page ) {
			$this->getOutput()->setStatusCode( 404 );
			throw new ErrorPageError( 'error', 'gadgets-subpage-invalid', [ $pageName ] );
		}

		if ( !( $page instanceof ListPage ) ) {
			$this->setSubtitle();
		}

		$this->setHeaders();
		$this->addHelpLink( 'Extension:Gadgets' );
		$page->execute( $params );
	}

	/**
	 * Get a _ActionPage subclass object for the given subpage name
	 * @param string $name
	 * @return null|ActionPage
	 */
	private function getSubpage( string $name ) {
		if ( !isset( self::SUBPAGE_LIST[$name] ) ) {
			return null;
		}
		/** @var ActionPage $page */
		$page = $this->objectFactory->createObject(
			self::SUBPAGE_LIST[$name],
			[
				'extraArgs' => [ $this ],
				'assertClass' => ActionPage::class,
			]
		);
		return $page;
	}

	/**
	 * Set a navigation subtitle.
	 */
	private function setSubtitle() {
		$title = $this->getPageTitle();
		$linkRenderer = $this->getLinkRenderer();
		$subtitle = '&lt; ' . $linkRenderer->makeKnownLink( $title, $this->msg( 'gadgets-title' )->text() );
		$this->getOutput()->setSubtitle( $subtitle );
	}
}
