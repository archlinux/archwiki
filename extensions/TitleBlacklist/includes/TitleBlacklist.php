<?php
/**
 * Title Blacklist class
 * @author Victor Vasiliev
 * @copyright © 2007-2010 Victor Vasiliev et al
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\TitleBlacklist;

use BadMethodCallException;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\AtEase\AtEase;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @ingroup Extensions
 */

/**
 * Implements a title blacklist for MediaWiki
 */
class TitleBlacklist {
	/** @var TitleBlacklistEntry[]|null */
	private $mBlacklist = null;

	/** @var TitleBlacklistEntry[]|null */
	private $mWhitelist = null;

	/** @var TitleBlacklist|null */
	protected static $instance = null;

	/** Increase this to invalidate the cached copies of both blacklist and whitelist */
	public const VERSION = 4;

	/**
	 * Get an instance of this class
	 */
	public static function singleton(): self {
		self::$instance ??= new self();
		return self::$instance;
	}

	/**
	 * Destroy/reset the current singleton instance.
	 *
	 * This is solely for testing and will fail unless MW_PHPUNIT_TEST is
	 * defined.
	 */
	public static function destroySingleton() {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new BadMethodCallException(
				'Can not invoke ' . __METHOD__ . '() ' .
				'out of tests (MW_PHPUNIT_TEST not set).'
			);
		}

		self::$instance = null;
	}

	/**
	 * Load all configured blacklist sources
	 */
	public function load() {
		global $wgTitleBlacklistSources, $wgTitleBlacklistCaching;

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$this->mBlacklist = $cache->getWithSetCallback(
			$cache->makeKey( 'title_blacklist_entries' ),
			$wgTitleBlacklistCaching['expiry'],
			static function () use ( $wgTitleBlacklistSources ) {
				$sources = $wgTitleBlacklistSources;
				$sources['local'] = [ 'type' => 'message' ];
				$blacklist = [];
				foreach ( $sources as $sourceName => $source ) {
					$blacklist = array_merge(
						$blacklist,
						self::parseBlacklist(
							self::getBlacklistText( $source ),
							$sourceName
						)
					);
				}

				wfDebugLog( 'TitleBlacklist-cache',
					'Regenerated blacklist with ' . count( $blacklist ) . ' entries.' );
				return $blacklist;
			},
			[
				'version' => self::VERSION,
				'lockTSE' => 30,
				'staleTTL' => WANObjectCache::TTL_DAY,
			]
		);
	}

	/**
	 * Load local whitelist
	 */
	public function loadWhitelist() {
		global $wgTitleBlacklistCaching;

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$this->mWhitelist = $cache->getWithSetCallback(
			$cache->makeKey( 'title_whitelist_entries' ),
			$wgTitleBlacklistCaching['expiry'],
			static function () {
				return self::parseBlacklist(
					wfMessage( 'titlewhitelist' )->inContentLanguage()->text(),
					'whitelist'
				);
			},
			[
				'version' => self::VERSION,
				'lockTSE' => 30,
				'staleTTL' => WANObjectCache::TTL_DAY,
			]
		);
	}

	/**
	 * Get the text of a blacklist from a specified source
	 *
	 * @param array{type: string, src: ?string} $source A blacklist source from $wgTitleBlacklistSources
	 * @return string The content of the blacklist source as a string
	 */
	private static function getBlacklistText( $source ) {
		if ( !is_array( $source ) || !isset( $source['type'] ) ) {
			// Return empty string in error case
			return '';
		}

		if ( $source['type'] === 'message' ) {
			return wfMessage( 'titleblacklist' )->inContentLanguage()->text();
		}

		$src = $source['src'] ?? null;
		// All following types require the "src" element in the array
		if ( !$src ) {
			return '';
		}

		if ( $source['type'] === 'localpage' ) {
			$title = Title::newFromText( $src );
			if ( !$title ) {
				return '';
			}
			if ( $title->inNamespace( NS_MEDIAWIKI ) ) {
				$msg = wfMessage( $title->getText() )->inContentLanguage();
				return $msg->isDisabled() ? '' : $msg->text();
			} else {
				$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
				if ( $page->exists() ) {
					$content = $page->getContent();
					return ( $content instanceof TextContent ) ? $content->getText() : "";
				}
			}
		} elseif ( $source['type'] === 'url' ) {
			return self::getHttp( $src );
		} elseif ( $source['type'] === 'file' ) {
			return file_exists( $src ) ? file_get_contents( $src ) : '';
		}

		return '';
	}

	/**
	 * Parse blacklist from a string
	 *
	 * @param string $list Text of a blacklist source
	 * @param string $sourceName
	 * @return TitleBlacklistEntry[]
	 */
	public static function parseBlacklist( $list, $sourceName ) {
		$lines = preg_split( "/\r?\n/", $list );
		$result = [];
		foreach ( $lines as $line ) {
			$entry = TitleBlacklistEntry::newFromString( $line, $sourceName );
			if ( $entry ) {
				$result[] = $entry;
			}
		}

		return $result;
	}

	/**
	 * Check whether the blacklist restricts given user
	 * performing a specific action on the given Title
	 *
	 * @param Title $title Title to check
	 * @param User $user User to check
	 * @param string $action Action to check; 'edit' if unspecified
	 * @param bool $override If set to true, overrides work
	 * @return TitleBlacklistEntry|bool The corresponding TitleBlacklistEntry if
	 * blacklisted; otherwise false
	 */
	public function userCannot( $title, $user, $action = 'edit', $override = true ) {
		$entry = $this->isBlacklisted( $title, $action );
		if ( !$entry ) {
			return false;
		}
		$params = $entry->getParams();
		if ( isset( $params['autoconfirmed'] ) && $user->isAllowed( 'autoconfirmed' ) ) {
			return false;
		}
		if ( $override && self::userCanOverride( $user, $action ) ) {
			return false;
		}
		return $entry;
	}

	/**
	 * Check whether the blacklist restricts
	 * performing a specific action on the given Title
	 *
	 * @param Title $title Title to check
	 * @param string $action Action to check; 'edit' if unspecified
	 * @return TitleBlacklistEntry|bool The corresponding TitleBlacklistEntry if blacklisted;
	 *         otherwise FALSE
	 */
	public function isBlacklisted( $title, $action = 'edit' ) {
		if ( !( $title instanceof Title ) ) {
			$title = Title::newFromText( $title );
			if ( !$title ) {
				// The fact that the page name is invalid will stop whatever
				// action is going through. No sense in doing more work here.
				return false;
			}
		}

		$autoconfirmedItem = null;
		foreach ( $this->getBlacklist() as $item ) {
			if ( $item->matches( $title->getFullText(), $action ) ) {
				if ( $this->isWhitelisted( $title, $action ) ) {
					return false;
				}
				if ( !isset( $item->getParams()['autoconfirmed'] ) ) {
					return $item;
				}
				$autoconfirmedItem ??= $item;
			}
		}
		return $autoconfirmedItem ?? false;
	}

	/**
	 * Check whether it has been explicitly whitelisted that the
	 * current User may perform a specific action on the given Title
	 *
	 * @param Title $title Title to check
	 * @param string $action Action to check; 'edit' if unspecified
	 * @return bool True if whitelisted; otherwise false
	 */
	public function isWhitelisted( $title, $action = 'edit' ) {
		if ( !( $title instanceof Title ) ) {
			$title = Title::newFromText( $title );
			if ( !$title ) {
				return false;
			}
		}
		$whitelist = $this->getWhitelist();
		foreach ( $whitelist as $item ) {
			if ( $item->matches( $title->getFullText(), $action ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the current blacklist
	 *
	 * @return TitleBlacklistEntry[]
	 */
	public function getBlacklist() {
		if ( $this->mBlacklist === null ) {
			$this->load();
		}
		return $this->mBlacklist;
	}

	/**
	 * Get the current whitelist
	 *
	 * @return TitleBlacklistEntry[]
	 */
	public function getWhitelist() {
		if ( $this->mWhitelist === null ) {
			$this->loadWhitelist();
		}
		return $this->mWhitelist;
	}

	/**
	 * Get the text of a blacklist source via HTTP
	 *
	 * @param string $url URL of the blacklist source
	 * @return string The content of the blacklist source as a string
	 */
	private static function getHttp( $url ) {
		global $wgTitleBlacklistCaching;

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeGlobalKey( 'title_blacklist_source', md5( $url ) );
		$caller = __METHOD__;

		return $cache->getWithSetCallback(
			$key,
			$wgTitleBlacklistCaching['expiry'],
			static function ( $oldValue, &$ttl ) use ( $url, $caller ) {
				$httpRequestFactory = MediaWikiServices::getInstance()
					->getHttpRequestFactory();

				$result = $httpRequestFactory->get( $url, [], $caller );

				// Retry once on transient failure (T423162)
				if ( $result === null ) {
					usleep( 50_000 );
					$result = $httpRequestFactory->get( $url, [], $caller );
				}

				if ( is_string( $result ) ) {
					return $result;
				}

				// All attempts failed
				wfDebugLog( 'TitleBlacklist-cache',
					"Error loading title blacklist from $url" );

				// If we have a previous cached value, keep using it
				if ( is_string( $oldValue ) ) {
					$ttl = WANObjectCache::TTL_MINUTE;
					wfDebugLog( 'TitleBlacklist-cache',
						"Using previously cached value for $url" );
					return $oldValue;
				}

				// No previous value at all
				return '';
			},
			[
				'lockTSE' => 30,
				'staleTTL' => WANObjectCache::TTL_DAY,
				'busyValue' => '',
			]
		);
	}

	/**
	 * Invalidate the blacklist cache
	 */
	public function invalidate() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( $cache->makeKey( 'title_blacklist_entries' ) );
	}

	/**
	 * Validate a new blacklist
	 *
	 * @suppress PhanParamSuspiciousOrder The preg_match() params are in the correct order
	 * @param TitleBlacklistEntry[] $blacklist
	 * @return string[] List of invalid entries; empty array means blacklist is valid
	 */
	public function validate( array $blacklist ) {
		$badEntries = [];
		foreach ( $blacklist as $e ) {
			AtEase::suppressWarnings();
			$regex = $e->getRegex();
			// @phan-suppress-next-line SecurityCheck-ReDoS
			if ( preg_match( "/{$regex}/u", '' ) === false ) {
				$badEntries[] = $e->getRaw();
			}
			AtEase::restoreWarnings();
		}
		return $badEntries;
	}

	/**
	 * Indicates whether user can override blacklist on certain action.
	 *
	 * @param User $user
	 * @param string $action
	 *
	 * @return bool
	 */
	public static function userCanOverride( $user, $action ) {
		return $user->isAllowed( 'tboverride' ) ||
			( $action == 'new-account' && $user->isAllowed( 'tboverride-account' ) );
	}
}
