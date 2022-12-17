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

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Storage\BlobStore;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Local repository that stores files in the local filesystem and registers them
 * in the wiki's own database.
 *
 * This is the most commonly used file repository class.
 *
 * @ingroup FileRepo
 * @method LocalFile|null newFile( $title, $time = false )
 */
class LocalRepo extends FileRepo {
	/** @var callable */
	protected $fileFactory = [ LocalFile::class, 'newFromTitle' ];
	/** @var callable */
	protected $fileFactoryKey = [ LocalFile::class, 'newFromKey' ];
	/** @var callable */
	protected $fileFromRowFactory = [ LocalFile::class, 'newFromRow' ];
	/** @var callable */
	protected $oldFileFromRowFactory = [ OldLocalFile::class, 'newFromRow' ];
	/** @var callable */
	protected $oldFileFactory = [ OldLocalFile::class, 'newFromTitle' ];
	/** @var callable */
	protected $oldFileFactoryKey = [ OldLocalFile::class, 'newFromKey' ];

	/** @var string DB domain of the repo wiki */
	protected $dbDomain;
	/** @var bool Whether shared cache keys are exposed/accessible */
	protected $hasAccessibleSharedCache;

	/** @var BlobStore */
	protected $blobStore;

	/** @var bool */
	protected $useJsonMetadata = true;

	/** @var bool */
	protected $useSplitMetadata = false;

	/** @var int|null */
	protected $splitMetadataThreshold = 1000;

	/** @var bool */
	protected $updateCompatibleMetadata = false;

	/** @var bool */
	protected $reserializeMetadata = false;

	public function __construct( array $info = null ) {
		parent::__construct( $info );

		$this->dbDomain = WikiMap::getCurrentWikiDbDomain();
		$this->hasAccessibleSharedCache = true;

		$this->hasSha1Storage = ( $info['storageLayout'] ?? null ) === 'sha1';

		if ( $this->hasSha1Storage() ) {
			$this->backend = new FileBackendDBRepoWrapper( [
				'backend'         => $this->backend,
				'repoName'        => $this->name,
				'dbHandleFactory' => $this->getDBFactory()
			] );
		}

		foreach (
			[
				'useJsonMetadata',
				'useSplitMetadata',
				'splitMetadataThreshold',
				'updateCompatibleMetadata',
				'reserializeMetadata',
			] as $option
		) {
			if ( isset( $info[$option] ) ) {
				$this->$option = $info[$option];
			}
		}
	}

	/**
	 * @throws MWException
	 * @param stdClass $row
	 * @return LocalFile
	 */
	public function newFileFromRow( $row ) {
		if ( isset( $row->img_name ) ) {
			return call_user_func( $this->fileFromRowFactory, $row, $this );
		} elseif ( isset( $row->oi_name ) ) {
			return call_user_func( $this->oldFileFromRowFactory, $row, $this );
		} else {
			throw new MWException( __METHOD__ . ': invalid row' );
		}
	}

	/**
	 * @param PageIdentity|LinkTarget|string $title
	 * @param string $archiveName
	 * @return OldLocalFile
	 */
	public function newFromArchiveName( $title, $archiveName ) {
		$title = File::normalizeTitle( $title );
		return OldLocalFile::newFromArchiveName( $title, $this, $archiveName );
	}

	/**
	 * Delete files in the deleted directory if they are not referenced in the
	 * filearchive table. This needs to be done in the repo because it needs to
	 * interleave database locks with file operations, which is potentially a
	 * remote operation.
	 *
	 * @param string[] $storageKeys
	 *
	 * @return Status
	 */
	public function cleanupDeletedBatch( array $storageKeys ) {
		if ( $this->hasSha1Storage() ) {
			wfDebug( __METHOD__ . ": skipped because storage uses sha1 paths" );
			return Status::newGood();
		}

		$backend = $this->backend; // convenience
		$root = $this->getZonePath( 'deleted' );
		$dbw = $this->getPrimaryDB();
		$status = $this->newGood();
		$storageKeys = array_unique( $storageKeys );
		foreach ( $storageKeys as $key ) {
			$hashPath = $this->getDeletedHashPath( $key );
			$path = "$root/$hashPath$key";
			$dbw->startAtomic( __METHOD__ );
			// Check for usage in deleted/hidden files and preemptively
			// lock the key to avoid any future use until we are finished.
			$deleted = $this->deletedFileHasKey( $key, 'lock' );
			$hidden = $this->hiddenFileHasKey( $key, 'lock' );
			if ( !$deleted && !$hidden ) { // not in use now
				wfDebug( __METHOD__ . ": deleting $key" );
				$op = [ 'op' => 'delete', 'src' => $path ];
				if ( !$backend->doOperation( $op )->isOK() ) {
					$status->error( 'undelete-cleanup-error', $path );
					$status->failCount++;
				}
			} else {
				wfDebug( __METHOD__ . ": $key still in use" );
				$status->successCount++;
			}
			$dbw->endAtomic( __METHOD__ );
		}

		return $status;
	}

	/**
	 * Check if a deleted (filearchive) file has this sha1 key
	 *
	 * @param string $key File storage key (base-36 sha1 key with file extension)
	 * @param string|null $lock Use "lock" to lock the row via FOR UPDATE
	 * @return bool File with this key is in use
	 */
	protected function deletedFileHasKey( $key, $lock = null ) {
		$dbw = $this->getPrimaryDB();
		return (bool)$dbw->selectField( 'filearchive', '1',
			[ 'fa_storage_group' => 'deleted', 'fa_storage_key' => $key ],
			__METHOD__,
			$lock === 'lock' ? [ 'FOR UPDATE' ] : []
		);
	}

	/**
	 * Check if a hidden (revision delete) file has this sha1 key
	 *
	 * @param string $key File storage key (base-36 sha1 key with file extension)
	 * @param string|null $lock Use "lock" to lock the row via FOR UPDATE
	 * @return bool File with this key is in use
	 */
	protected function hiddenFileHasKey( $key, $lock = null ) {
		$sha1 = self::getHashFromKey( $key );
		$ext = File::normalizeExtension( substr( $key, strcspn( $key, '.' ) + 1 ) );

		$dbw = $this->getPrimaryDB();
		return (bool)$dbw->selectField( 'oldimage', '1',
			[
				'oi_sha1' => $sha1,
				'oi_archive_name ' . $dbw->buildLike( $dbw->anyString(), ".$ext" ),
				$dbw->bitAnd( 'oi_deleted', File::DELETED_FILE ) => File::DELETED_FILE,
			],
			__METHOD__,
			$lock === 'lock' ? [ 'FOR UPDATE' ] : []
		);
	}

	/**
	 * Gets the SHA1 hash from a storage key
	 *
	 * @param string $key
	 * @return string
	 */
	public static function getHashFromKey( $key ) {
		$sha1 = strtok( $key, '.' );
		if ( is_string( $sha1 ) && strlen( $sha1 ) === 32 && $sha1[0] === '0' ) {
			$sha1 = substr( $sha1, 1 );
		}
		return $sha1;
	}

	/**
	 * Checks if there is a redirect named as $title
	 *
	 * @param PageIdentity|LinkTarget $title Title of file
	 * @return bool|Title
	 */
	public function checkRedirect( $title ) {
		$title = File::normalizeTitle( $title, 'exception' );

		$memcKey = $this->getSharedCacheKey( 'file-redirect', md5( $title->getDBkey() ) );
		if ( $memcKey === false ) {
			$memcKey = $this->getLocalCacheKey( 'file-redirect', md5( $title->getDBkey() ) );
			$expiry = 300; // no invalidation, 5 minutes
		} else {
			$expiry = 86400; // has invalidation, 1 day
		}

		$method = __METHOD__;
		$redirDbKey = $this->wanCache->getWithSetCallback(
			$memcKey,
			$expiry,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $method, $title ) {
				$dbr = $this->getReplicaDB(); // possibly remote DB

				$setOpts += Database::getCacheSetOptions( $dbr );

				$row = $dbr->selectRow(
					[ 'page', 'redirect' ],
					[ 'rd_namespace', 'rd_title' ],
					[
						'page_namespace' => $title->getNamespace(),
						'page_title' => $title->getDBkey(),
						'rd_from = page_id'
					],
					$method
				);

				return ( $row && $row->rd_namespace == NS_FILE )
					? Title::makeTitle( $row->rd_namespace, $row->rd_title )->getDBkey()
					: ''; // negative cache
			},
			[ 'pcTTL' => WANObjectCache::TTL_PROC_LONG ]
		);

		// @note: also checks " " for b/c
		if ( $redirDbKey !== ' ' && strval( $redirDbKey ) !== '' ) {
			// Page is a redirect to another file
			return Title::newFromText( $redirDbKey, NS_FILE );
		}

		return false; // no redirect
	}

	public function findFiles( array $items, $flags = 0 ) {
		$finalFiles = []; // map of (DB key => corresponding File) for matches

		$searchSet = []; // map of (normalized DB key => search params)
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$title = File::normalizeTitle( $item['title'] );
				if ( $title ) {
					$searchSet[$title->getDBkey()] = $item;
				}
			} else {
				$title = File::normalizeTitle( $item );
				if ( $title ) {
					$searchSet[$title->getDBkey()] = [];
				}
			}
		}

		$fileMatchesSearch = static function ( File $file, array $search ) {
			// Note: file name comparison done elsewhere (to handle redirects)

			// Fallback to RequestContext::getMain should be replaced with a better
			// way of setting the user that should be used; currently it needs to be
			// set for each file individually. See T263033#6477586
			$contextPerformer = RequestContext::getMain()->getAuthority();
			$performer = ( !empty( $search['private'] ) && $search['private'] instanceof Authority )
				? $search['private']
				: $contextPerformer;

			return (
				$file->exists() &&
				(
					( empty( $search['time'] ) && !$file->isOld() ) ||
					( !empty( $search['time'] ) && $search['time'] === $file->getTimestamp() )
				) &&
				( !empty( $search['private'] ) || !$file->isDeleted( File::DELETED_FILE ) ) &&
				$file->userCan( File::DELETED_FILE, $performer )
			);
		};

		$applyMatchingFiles = function ( IResultWrapper $res, &$searchSet, &$finalFiles )
			use ( $fileMatchesSearch, $flags )
		{
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$info = $this->getInfo();
			foreach ( $res as $row ) {
				$file = $this->newFileFromRow( $row );
				// There must have been a search for this DB key, but this has to handle the
				// cases were title capitalization is different on the client and repo wikis.
				$dbKeysLook = [ strtr( $file->getName(), ' ', '_' ) ];
				if ( !empty( $info['initialCapital'] ) ) {
					// Search keys for "hi.png" and "Hi.png" should use the "Hi.png file"
					$dbKeysLook[] = $contLang->lcfirst( $file->getName() );
				}
				foreach ( $dbKeysLook as $dbKey ) {
					if ( isset( $searchSet[$dbKey] )
						&& $fileMatchesSearch( $file, $searchSet[$dbKey] )
					) {
						$finalFiles[$dbKey] = ( $flags & FileRepo::NAME_AND_TIME_ONLY )
							? [ 'title' => $dbKey, 'timestamp' => $file->getTimestamp() ]
							: $file;
						unset( $searchSet[$dbKey] );
					}
				}
			}
		};

		$dbr = $this->getReplicaDB();

		// Query image table
		$imgNames = [];
		foreach ( array_keys( $searchSet ) as $dbKey ) {
			$imgNames[] = $this->getNameFromTitle( File::normalizeTitle( $dbKey ) );
		}

		if ( count( $imgNames ) ) {
			$fileQuery = LocalFile::getQueryInfo();
			$res = $dbr->select( $fileQuery['tables'], $fileQuery['fields'], [ 'img_name' => $imgNames ],
				__METHOD__, [], $fileQuery['joins'] );
			$applyMatchingFiles( $res, $searchSet, $finalFiles );
		}

		// Query old image table
		$oiConds = []; // WHERE clause array for each file
		foreach ( $searchSet as $dbKey => $search ) {
			if ( isset( $search['time'] ) ) {
				$oiConds[] = $dbr->makeList(
					[
						'oi_name' => $this->getNameFromTitle( File::normalizeTitle( $dbKey ) ),
						'oi_timestamp' => $dbr->timestamp( $search['time'] )
					],
					LIST_AND
				);
			}
		}

		if ( count( $oiConds ) ) {
			$fileQuery = OldLocalFile::getQueryInfo();
			$res = $dbr->select( $fileQuery['tables'], $fileQuery['fields'],
				$dbr->makeList( $oiConds, LIST_OR ),
				__METHOD__, [], $fileQuery['joins'] );
			$applyMatchingFiles( $res, $searchSet, $finalFiles );
		}

		// Check for redirects...
		foreach ( $searchSet as $dbKey => $search ) {
			if ( !empty( $search['ignoreRedirect'] ) ) {
				continue;
			}

			$title = File::normalizeTitle( $dbKey );
			$redir = $this->checkRedirect( $title ); // hopefully hits memcached

			if ( $redir && $redir->getNamespace() === NS_FILE ) {
				$file = $this->newFile( $redir );
				if ( $file && $fileMatchesSearch( $file, $search ) ) {
					$file->redirectedFrom( $title->getDBkey() );
					if ( $flags & FileRepo::NAME_AND_TIME_ONLY ) {
						$finalFiles[$dbKey] = [
							'title' => $file->getTitle()->getDBkey(),
							'timestamp' => $file->getTimestamp()
						];
					} else {
						$finalFiles[$dbKey] = $file;
					}
				}
			}
		}

		return $finalFiles;
	}

	/**
	 * Get an array or iterator of file objects for files that have a given
	 * SHA-1 content hash.
	 *
	 * @param string $hash A sha1 hash to look for
	 * @return LocalFile[]
	 */
	public function findBySha1( $hash ) {
		$dbr = $this->getReplicaDB();
		$fileQuery = LocalFile::getQueryInfo();
		$res = $dbr->select(
			$fileQuery['tables'],
			$fileQuery['fields'],
			[ 'img_sha1' => $hash ],
			__METHOD__,
			[ 'ORDER BY' => 'img_name' ],
			$fileQuery['joins']
		);

		$result = [];
		foreach ( $res as $row ) {
			$result[] = $this->newFileFromRow( $row );
		}
		$res->free();

		return $result;
	}

	/**
	 * Get an array of arrays or iterators of file objects for files that
	 * have the given SHA-1 content hashes.
	 *
	 * Overrides generic implementation in FileRepo for performance reason
	 *
	 * @param string[] $hashes An array of hashes
	 * @return File[][] An Array of arrays or iterators of file objects and the hash as key
	 */
	public function findBySha1s( array $hashes ) {
		if ( $hashes === [] ) {
			return []; // empty parameter
		}

		$dbr = $this->getReplicaDB();
		$fileQuery = LocalFile::getQueryInfo();
		$res = $dbr->select(
			$fileQuery['tables'],
			$fileQuery['fields'],
			[ 'img_sha1' => $hashes ],
			__METHOD__,
			[ 'ORDER BY' => 'img_name' ],
			$fileQuery['joins']
		);

		$result = [];
		foreach ( $res as $row ) {
			$file = $this->newFileFromRow( $row );
			$result[$file->getSha1()][] = $file;
		}
		$res->free();

		return $result;
	}

	/**
	 * Return an array of files where the name starts with $prefix.
	 *
	 * @param string $prefix The prefix to search for
	 * @param int $limit The maximum amount of files to return
	 * @return LocalFile[]
	 */
	public function findFilesByPrefix( $prefix, $limit ) {
		$selectOptions = [ 'ORDER BY' => 'img_name', 'LIMIT' => intval( $limit ) ];

		// Query database
		$dbr = $this->getReplicaDB();
		$fileQuery = LocalFile::getQueryInfo();
		$res = $dbr->select(
			$fileQuery['tables'],
			$fileQuery['fields'],
			'img_name ' . $dbr->buildLike( $prefix, $dbr->anyString() ),
			__METHOD__,
			$selectOptions,
			$fileQuery['joins']
		);

		// Build file objects
		$files = [];
		foreach ( $res as $row ) {
			$files[] = $this->newFileFromRow( $row );
		}

		return $files;
	}

	/**
	 * Get a connection to the replica DB
	 * @return IDatabase
	 */
	public function getReplicaDB() {
		return wfGetDB( DB_REPLICA );
	}

	/**
	 * Get a connection to the primary DB
	 * @return IDatabase
	 * @since 1.37
	 */
	public function getPrimaryDB() {
		return wfGetDB( DB_PRIMARY );
	}

	/**
	 * Get a connection to the primary DB
	 * @deprecated since 1.37
	 * @return IDatabase
	 */
	public function getMasterDB() {
		wfDeprecated( __METHOD__, '1.37' );
		return $this->getPrimaryDB();
	}

	/**
	 * Get a callback to get a DB handle given an index (DB_REPLICA/DB_PRIMARY)
	 * @return Closure
	 */
	protected function getDBFactory() {
		return static function ( $index ) {
			return wfGetDB( $index );
		};
	}

	/**
	 * Check whether the repo has a shared cache, accessible from the current site context
	 *
	 * @return bool
	 * @since 1.35
	 */
	protected function hasAcessibleSharedCache() {
		return $this->hasAccessibleSharedCache;
	}

	public function getSharedCacheKey( $kClassSuffix, ...$components ) {
		// T267668: do not include the repo name in the key
		return $this->hasAcessibleSharedCache()
			? $this->wanCache->makeGlobalKey(
				'filerepo-' . $kClassSuffix,
				$this->dbDomain,
				...$components
			)
			: false;
	}

	/**
	 * Invalidates image redirect cache related to that image
	 *
	 * @param PageIdentity|LinkTarget $title Title of page
	 * @return void
	 */
	public function invalidateImageRedirect( $title ) {
		$key = $this->getSharedCacheKey( 'file-redirect', md5( $title->getDBkey() ) );
		if ( $key ) {
			$this->getPrimaryDB()->onTransactionPreCommitOrIdle(
				function () use ( $key ) {
					$this->wanCache->delete( $key );
				},
				__METHOD__
			);
		}
	}

	public function store( $srcPath, $dstZone, $dstRel, $flags = 0 ) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	public function storeBatch( array $triplets, $flags = 0 ) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	public function cleanupBatch( array $files, $flags = 0 ) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	public function publish(
		$src,
		$dstRel,
		$archiveRel,
		$flags = 0,
		array $options = []
	) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	public function publishBatch( array $ntuples, $flags = 0 ) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	public function delete( $srcRel, $archiveRel ) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	public function deleteBatch( array $sourceDestPairs ) {
		return $this->skipWriteOperationIfSha1( __FUNCTION__, func_get_args() );
	}

	/**
	 * Skips the write operation if storage is sha1-based, executes it normally otherwise
	 *
	 * @param string $function
	 * @param array $args
	 * @return Status
	 */
	protected function skipWriteOperationIfSha1( $function, array $args ) {
		$this->assertWritableRepo(); // fail out if read-only

		if ( $this->hasSha1Storage() ) {
			wfDebug( __METHOD__ . ": skipped because storage uses sha1 paths" );
			return Status::newGood();
		} else {
			return parent::$function( ...$args );
		}
	}

	/**
	 * Returns true if files should store metadata in JSON format. This
	 * requires metadata from all handlers to be JSON-serializable.
	 *
	 * To avoid breaking existing metadata, reading JSON metadata is always
	 * enabled regardless of this setting.
	 *
	 * @return bool
	 */
	public function isJsonMetadataEnabled() {
		return $this->useJsonMetadata;
	}

	/**
	 * Returns true if files should split up large metadata, storing parts of
	 * it in the BlobStore.
	 *
	 * @return bool
	 */
	public function isSplitMetadataEnabled() {
		return $this->isJsonMetadataEnabled() && $this->useSplitMetadata;
	}

	/**
	 * Get the threshold above which metadata items should be split into
	 * separate storage, or null if no splitting should be done.
	 *
	 * @return int
	 */
	public function getSplitMetadataThreshold() {
		return $this->splitMetadataThreshold;
	}

	public function isMetadataUpdateEnabled() {
		return $this->updateCompatibleMetadata;
	}

	public function isMetadataReserializeEnabled() {
		return $this->reserializeMetadata;
	}

	/**
	 * Get a BlobStore for storing and retrieving large metadata, or null if
	 * that can't be done.
	 *
	 * @return ?BlobStore
	 */
	public function getBlobStore(): ?BlobStore {
		if ( !$this->blobStore ) {
			$this->blobStore = MediaWikiServices::getInstance()->getBlobStoreFactory()
				->newBlobStore( $this->dbDomain );
		}
		return $this->blobStore;
	}
}
