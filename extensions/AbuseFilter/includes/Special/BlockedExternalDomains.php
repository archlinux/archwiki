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
namespace MediaWiki\Extension\AbuseFilter\Special;

use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainConfigProvider;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainEditor;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainValidator;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\IBlockedDomainStorage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * List and manage blocked external domains
 *
 * @ingroup SpecialPage
 */
class BlockedExternalDomains extends SpecialPage {
	private IBlockedDomainStorage $blockedDomainStorage;
	private BlockedDomainValidator $blockedDomainValidator;
	private WANObjectCache $wanCache;

	public function __construct(
		IBlockedDomainStorage $blockedDomainStorage,
		BlockedDomainValidator $blockedDomainValidator,
		WANObjectCache $wanCache
	) {
		parent::__construct( 'BlockedExternalDomains' );
		$this->blockedDomainStorage = $blockedDomainStorage;
		$this->blockedDomainValidator = $blockedDomainValidator;
		$this->wanCache = $wanCache;
	}

	/** @inheritDoc */
	public function execute( $par ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CommunityConfiguration' ) ) {
			$this->getOutput()->redirect(
				$this->getSpecialPageFactory()
					->getTitleForAlias( 'CommunityConfiguration' )
					->getSubpage( BlockedDomainConfigProvider::PROVIDER_ID )
					->getLocalURL()
			);
			return;
		}

		$this->setHeaders();
		$this->outputHeader();

		$editor = new BlockedDomainEditor(
			$this->getContext(), $this->getPageTitle(),
			$this->wanCache, $this->getLinkRenderer(),
			$this->blockedDomainStorage, $this->blockedDomainValidator
		);
		$editor->execute( $par );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'spam';
	}

	/** @inheritDoc */
	public function isListed() {
		return $this->getConfig()->get( 'AbuseFilterEnableBlockedExternalDomain' );
	}
}
