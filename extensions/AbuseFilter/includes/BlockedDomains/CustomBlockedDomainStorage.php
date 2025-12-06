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
namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\JsonContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use StatusValue;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Hold and update information about blocked external domains
 *
 * @ingroup SpecialPage
 */
class CustomBlockedDomainStorage implements IBlockedDomainStorage, IDBAccessObject {
	public const TARGET_PAGE = 'BlockedExternalDomains.json';

	private RevisionLookup $revisionLookup;
	private BagOStuff $cache;
	private WikiPageFactory $wikiPageFactory;
	private BlockedDomainValidator $domainValidator;

	public function __construct(
		BagOStuff $cache,
		RevisionLookup $revisionLookup,
		WikiPageFactory $wikiPageFactory,
		BlockedDomainValidator $domainValidator
	) {
		$this->cache = $cache;
		$this->revisionLookup = $revisionLookup;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->domainValidator = $domainValidator;
	}

	/** @inheritDoc */
	public function loadConfig( int $flags = 0 ): StatusValue {
		if ( DBAccessObjectUtils::hasFlags( $flags, IDBAccessObject::READ_LATEST ) ) {
			return $this->fetchConfig( $flags );
		}

		// Load configuration from APCU
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'abusefilter-blockeddomains-config' ),
			BagOStuff::TTL_MINUTE * 5,
			function ( &$ttl ) use ( $flags ) {
				$result = $this->fetchConfig( $flags );
				if ( !$result->isGood() ) {
					// error should not be cached
					$ttl = BagOStuff::TTL_UNCACHEABLE;
				}
				return $result;
			}
		);
	}

	/** @inheritDoc */
	public function loadComputed(): array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'abusefilter-blockeddomains-computed' ),
			BagOStuff::TTL_MINUTE * 5,
			function () {
				$status = $this->loadConfig();
				if ( !$status->isGood() ) {
					return [];
				}
				$computedDomains = [];
				foreach ( $status->getValue() as $domain ) {
					if ( !( $domain['domain'] ?? null ) ) {
						continue;
					}
					$validatedDomain = $this->domainValidator->validateDomain( $domain['domain'] );
					if ( $validatedDomain ) {
						// It should be a map, benchmark at https://phabricator.wikimedia.org/P48956
						$computedDomains[$validatedDomain] = true;
					}
				}
				return $computedDomains;
			}
		);
	}

	/**
	 * Validate an input domain
	 *
	 * @deprecated since 1.45, use BlockedDomainValidator instead
	 * @see BlockedDomainValidator
	 * @param string|null $domain Domain such as foo.wikipedia.org
	 * @return string|false Parsed domain, or false otherwise
	 */
	public function validateDomain( $domain ) {
		// NOTE: Can be called on the deprecated class name, see the class_alias in the bottom
		wfDeprecated( __METHOD__, '1.45' );
		if ( !is_string( $domain ) && $domain !== null ) {
			// cannot be passed to BlockedDomainValidator
			return false;
		}
		return $this->domainValidator->validateDomain( $domain );
	}

	/**
	 * Fetch the contents of the configuration page, without caching.
	 *
	 * The result is not validated with a config validator.
	 *
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX; do NOT pass READ_UNCACHED
	 * @return StatusValue Status object, with the configuration (as JSON data) on success.
	 */
	private function fetchConfig( int $flags ): StatusValue {
		$revision = $this->revisionLookup->getRevisionByTitle( $this->getBlockedDomainPage(), 0,
			$flags );
		if ( !$revision ) {
			// The configuration page does not exist. Pretend it does not configure anything
			// specific (failure mode and empty-page behaviors are equal).
			return StatusValue::newGood( [] );
		}
		$content = $revision->getContent( SlotRecord::MAIN );
		if ( !$content instanceof JsonContent ) {
			return StatusValue::newFatal( 'modeleditnotsupported-text', $content->getModel() );
		}

		return FormatJson::parse( $content->getText(), FormatJson::FORCE_ASSOC );
	}

	/** @inheritDoc */
	public function addDomain( string $domain, string $notes, Authority $authority ): StatusValue {
		$contentStatus = $this->fetchLatestConfig();
		if ( !$contentStatus->isOK() ) {
			return $contentStatus;
		}
		$content = $contentStatus->getValue();
		$content[] = [ 'domain' => $domain, 'notes' => $notes, 'addedBy' => $authority->getUser()->getName() ];
		$comment = Message::newFromSpecifier( 'abusefilter-blocked-domains-domain-added-comment' )
			->params( $domain, $notes )
			->plain();
		return $this->saveContent( $content, $authority, $comment );
	}

	/** @inheritDoc */
	public function removeDomain( string $domain, string $notes, Authority $authority ): StatusValue {
		$contentStatus = $this->fetchLatestConfig();
		if ( !$contentStatus->isOK() ) {
			return $contentStatus;
		}
		$content = $contentStatus->getValue();
		foreach ( $content as $key => $value ) {
			if ( ( $value['domain'] ?? '' ) == $domain ) {
				unset( $content[$key] );
			}
		}
		$comment = Message::newFromSpecifier( 'abusefilter-blocked-domains-domain-removed-comment' )
			->params( $domain, $notes )
			->plain();
		return $this->saveContent( array_values( $content ), $authority, $comment );
	}

	/**
	 * @return StatusValue<array> Good status wrapping parsed JSON config as an array (empty array
	 *   when the page doesn't exist); error status on invalid JSON
	 */
	private function fetchLatestConfig(): StatusValue {
		$configPage = $this->getBlockedDomainPage();
		$revision = $this->revisionLookup->getRevisionByTitle( $configPage, 0, IDBAccessObject::READ_LATEST );
		if ( !$revision ) {
			return StatusValue::newGood( [] );
		}

		$revContent = $revision->getContent( SlotRecord::MAIN );
		if ( $revContent instanceof JsonContent ) {
			return FormatJson::parse( $revContent->getText(), FormatJson::FORCE_ASSOC );
		}
		return StatusValue::newFatal( 'modeleditnotsupported-text', $revContent->getModel() );
	}

	/**
	 * Save the provided content into the page
	 *
	 * @param array[] $content To be turned into JSON
	 * @param Authority $authority Performer
	 * @param string $comment Save comment
	 *
	 * @return StatusValue
	 */
	private function saveContent( array $content, Authority $authority, string $comment ): StatusValue {
		$configPage = $this->getBlockedDomainPage();
		$page = $this->wikiPageFactory->newFromTitle( $configPage );
		$updater = $page->newPageUpdater( $authority );
		$updater->setContent( SlotRecord::MAIN, new JsonContent( FormatJson::encode( $content ) ) );

		if ( $authority->isAllowed( 'autopatrol' ) ) {
			$updater->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
		}

		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( $comment )
		);
		return $updater->getStatus();
	}

	/**
	 * @return PageIdentity Title of the config JSON page
	 */
	private function getBlockedDomainPage(): PageIdentity {
		return Title::makeTitle( NS_MEDIAWIKI, self::TARGET_PAGE );
	}
}

// @deprecated since 1.44
class_alias( CustomBlockedDomainStorage::class, 'MediaWiki\Extension\AbuseFilter\BlockedDomainStorage' );
