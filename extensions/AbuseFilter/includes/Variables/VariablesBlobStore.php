<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use InvalidArgumentException;
use MediaWiki\Json\FormatJson;
use MediaWiki\Storage\BlobAccessException;
use MediaWiki\Storage\BlobStore;
use MediaWiki\Storage\BlobStoreFactory;
use stdClass;

/**
 * This service is used to store and load var dumps to a BlobStore
 */
class VariablesBlobStore {
	public const SERVICE_NAME = 'AbuseFilterVariablesBlobStore';

	/** @var VariablesManager */
	private $varManager;

	/** @var BlobStoreFactory */
	private $blobStoreFactory;

	/** @var BlobStore */
	private $blobStore;

	/** @var string|null */
	private $centralDB;

	/**
	 * @param VariablesManager $varManager
	 * @param BlobStoreFactory $blobStoreFactory
	 * @param BlobStore $blobStore
	 * @param string|null $centralDB
	 */
	public function __construct(
		VariablesManager $varManager,
		BlobStoreFactory $blobStoreFactory,
		BlobStore $blobStore,
		?string $centralDB
	) {
		$this->varManager = $varManager;
		$this->blobStoreFactory = $blobStoreFactory;
		$this->blobStore = $blobStore;
		$this->centralDB = $centralDB;
	}

	/**
	 * Store a var dump to a BlobStore.
	 *
	 * @param VariableHolder $varsHolder
	 * @param bool $global
	 *
	 * @return string Address of the record
	 */
	public function storeVarDump( VariableHolder $varsHolder, $global = false ) {
		// Get all variables yet set and compute old and new wikitext if not yet done
		// as those are needed for the diff view on top of the abuse log pages
		$vars = $this->varManager->dumpAllVars( $varsHolder, [ 'old_wikitext', 'new_wikitext' ] );

		// if user_unnamed_ip exists it can't be saved, as var dump blobs are stored in an append-only
		// database and stored IPs eventually need to be cleared.
		// Set the value to something safe here, as by now it's been used in the filter and if
		// logs later need it, it can be reconstructed from afl_ip.
		if ( isset( $vars[ 'user_unnamed_ip' ] ) && $vars[ 'user_unnamed_ip' ] ) {
			$vars[ 'user_unnamed_ip' ] = true;
		}

		// Vars is an array with native PHP data types (non-objects) now
		$text = FormatJson::encode( $vars );

		$dbDomain = $global ? $this->centralDB : false;
		$blobStore = $this->blobStoreFactory->newBlobStore( $dbDomain );

		$hints = [
			BlobStore::DESIGNATION_HINT => 'AbuseFilter',
			BlobStore::MODEL_HINT => 'AbuseFilter',
		];
		return $blobStore->storeBlob( $text, $hints );
	}

	/**
	 * Retrieve a var dump from a BlobStore.
	 *
	 * The entire $row is passed through but only the following columns are actually required:
	 * - afl_var_dump: the main variable store to load
	 * - afl_ip: the IP value to use if necessary
	 *
	 * @param stdClass $row
	 *
	 * @return VariableHolder
	 */
	public function loadVarDump( stdClass $row ): VariableHolder {
		if ( !isset( $row->afl_var_dump ) || !isset( $row->afl_ip ) ) {
			throw new InvalidArgumentException( 'Both afl_var_dump and afl_ip must be set' );
		}

		try {
			$varDump = $row->afl_var_dump;
			$blob = $this->blobStore->getBlob( $varDump );
		} catch ( BlobAccessException $ex ) {
			return new VariableHolder;
		}

		$vars = FormatJson::decode( $blob, true );
		$obj = VariableHolder::newFromArray( $vars );
		$this->varManager->translateDeprecatedVars( $obj );

		// If user_unnamed_ip was set when afl_var_dump was saved, it was saved as a visibility boolean
		// and needs to be translated back into an IP
		// user_unnamed_ip uses afl_ip instead of saving the value because afl_ip gets purged and the blob
		// that contains user_unnamed_ip can't be modified
		if (
			$this->varManager->getVar( $obj, 'user_unnamed_ip', $this->varManager::GET_LAX )->toNative()
		) {
			$obj->setVar( 'user_unnamed_ip', $row->afl_ip );
		}

		return $obj;
	}
}
