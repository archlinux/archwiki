<?php

namespace MediaWiki\Extension\AbuseFilter;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

/**
 * This service allows "linking" the edit filter hook and the page save hook
 */
class EditRevUpdater {
	public const SERVICE_NAME = 'AbuseFilterEditRevUpdater';

	/** @var CentralDBManager */
	private $centralDBManager;
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var ILoadBalancer */
	private $loadBalancer;
	/** @var string */
	private $wikiID;

	/** @var WikiPage|null */
	private $wikiPage;
	/**
	 * @var int[][][] IDs of logged filters like [ page title => [ 'local' => [ids], 'global' => [ids] ] ].
	 * @phan-var array<string,array{local:int[],global:int[]}>
	 */
	private $logIds = [];

	/**
	 * @param CentralDBManager $centralDBManager
	 * @param RevisionLookup $revisionLookup
	 * @param ILoadBalancer $loadBalancer
	 * @param string $wikiID
	 */
	public function __construct(
		CentralDBManager $centralDBManager,
		RevisionLookup $revisionLookup,
		ILoadBalancer $loadBalancer,
		string $wikiID
	) {
		$this->centralDBManager = $centralDBManager;
		$this->revisionLookup = $revisionLookup;
		$this->loadBalancer = $loadBalancer;
		$this->wikiID = $wikiID;
	}

	/**
	 * Set the WikiPage object used for the ongoing edit
	 *
	 * @param WikiPage $page
	 */
	public function setLastEditPage( WikiPage $page ): void {
		$this->wikiPage = $page;
	}

	/**
	 * Clear the WikiPage object used for the ongoing edit
	 */
	public function clearLastEditPage(): void {
		$this->wikiPage = null;
	}

	/**
	 * @param LinkTarget $target
	 * @param int[][] $logIds
	 * @phan-param array{local:int[],global:int[]} $logIds
	 */
	public function setLogIdsForTarget( LinkTarget $target, array $logIds ): void {
		if ( count( $logIds ) !== 2 || array_diff( array_keys( $logIds ), [ 'local', 'global' ] ) ) {
			throw new InvalidArgumentException( 'Wrong keys; got: ' . implode( ', ', array_keys( $logIds ) ) );
		}
		$key = $this->getCacheKey( $target );
		$this->logIds[$key] = $logIds;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param RevisionRecord $revisionRecord
	 * @return bool Whether the DB was updated
	 */
	public function updateRev( WikiPage $wikiPage, RevisionRecord $revisionRecord ): bool {
		$key = $this->getCacheKey( $wikiPage->getTitle() );
		if ( !isset( $this->logIds[ $key ] ) || $wikiPage !== $this->wikiPage ) {
			// This isn't the edit $this->logIds was set for
			$this->logIds = [];
			return false;
		}

		// Ignore null edit.
		$parentRevId = $revisionRecord->getParentId();
		if ( $parentRevId !== null ) {
			$parentRev = $this->revisionLookup->getRevisionById( $parentRevId );
			if ( $parentRev && $revisionRecord->hasSameContent( $parentRev ) ) {
				$this->logIds = [];
				return false;
			}
		}

		$this->clearLastEditPage();

		$ret = false;
		$logs = $this->logIds[ $key ];
		if ( $logs[ 'local' ] ) {
			$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
			$dbw->update( 'abuse_filter_log',
				[ 'afl_rev_id' => $revisionRecord->getId() ],
				[ 'afl_id' => $logs['local'] ],
				__METHOD__
			);
			$ret = true;
		}

		if ( $logs[ 'global' ] ) {
			$fdb = $this->centralDBManager->getConnection( DB_PRIMARY );
			$fdb->update( 'abuse_filter_log',
				[ 'afl_rev_id' => $revisionRecord->getId() ],
				[ 'afl_id' => $logs['global'], 'afl_wiki' => $this->wikiID ],
				__METHOD__
			);
			$ret = true;
		}
		return $ret;
	}

	/**
	 * @param LinkTarget $target
	 * @return string
	 */
	private function getCacheKey( LinkTarget $target ): string {
		return $target->getNamespace() . '|' . $target->getText();
	}
}
