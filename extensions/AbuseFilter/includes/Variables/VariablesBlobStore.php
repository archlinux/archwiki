<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use InvalidArgumentException;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Json\FormatJson;
use MediaWiki\Storage\BlobAccessException;
use MediaWiki\Storage\BlobStore;
use MediaWiki\Storage\BlobStoreFactory;
use stdClass;
use Wikimedia\IPUtils;

/**
 * This service is used to generate the value of afl_var_dump for an abuse_filter_log row and
 * parse afl_var_dump from an abuse_filter_log row into a {@link VariableHolder}
 */
class VariablesBlobStore {
	public const SERVICE_NAME = 'AbuseFilterVariablesBlobStore';

	private VariablesManager $varManager;
	private BlobStoreFactory $blobStoreFactory;
	private BlobStore $blobStore;
	private AbuseFilterPermissionManager $permissionManager;

	private ?string $centralDB;

	public function __construct(
		VariablesManager $varManager,
		AbuseFilterPermissionManager $permissionManager,
		BlobStoreFactory $blobStoreFactory,
		BlobStore $blobStore,
		?string $centralDB
	) {
		$this->varManager = $varManager;
		$this->blobStoreFactory = $blobStoreFactory;
		$this->blobStore = $blobStore;
		$this->permissionManager = $permissionManager;
		$this->centralDB = $centralDB;
	}

	/**
	 * Store a var dump to a BlobStore.
	 *
	 * @param VariableHolder $varsHolder
	 * @param bool $global
	 *
	 * @return string Blob store address or JSON if the var dump included protected variables.
	 */
	public function storeVarDump( VariableHolder $varsHolder, $global = false ) {
		// Get all variables yet set and compute old and new wikitext if not yet done
		// as those are needed for the diff view on top of the abuse log pages
		$varsForBlobStore = $this->varManager->dumpAllVars( $varsHolder, [ 'old_wikitext', 'new_wikitext' ] );
		$varsForDB = [];

		// Get a list of the protected variables present in the VariableHolder. This excludes user_unnamed_ip always
		// as it is handled separately later in this method.
		$usedProtectedVariables = $this->permissionManager->getUsedProtectedVariables(
			array_keys( $varsForBlobStore )
		);
		unset( $usedProtectedVariables['user_unnamed_ip'] );

		// Store the values of protected variables in the DB instead of the append-only external storage.
		// Leave a reference to these variables so that they display nothing when the data is purged.
		foreach ( $usedProtectedVariables as $protectedVariable ) {
			$varsForDB[$protectedVariable] = $varsForBlobStore[$protectedVariable];
			$varsForBlobStore[$protectedVariable] = true;
		}

		// Set the value to something safe here, as by now it's been used in the filter and if
		// logs later need it, it can be reconstructed from afl_ip_hex.
		if ( isset( $varsForBlobStore[ 'user_unnamed_ip' ] ) && $varsForBlobStore[ 'user_unnamed_ip' ] ) {
			$varsForBlobStore[ 'user_unnamed_ip' ] = true;
		}

		// Vars is an array with native PHP data types (non-objects) now
		$text = FormatJson::encode( $varsForBlobStore );

		$dbDomain = $global ? $this->centralDB : false;
		$blobStore = $this->blobStoreFactory->newBlobStore( $dbDomain );

		$hints = [
			BlobStore::DESIGNATION_HINT => 'AbuseFilter',
			BlobStore::MODEL_HINT => 'AbuseFilter',
		];
		$blobStoreAddress = $blobStore->storeBlob( $text, $hints );

		if ( !count( $varsForDB ) ) {
			return $blobStoreAddress;
		}

		return FormatJson::encode( array_merge( $varsForDB, [ '_blob' => $blobStoreAddress ] ) );
	}

	/**
	 * Retrieve a var dump from a BlobStore.
	 *
	 * The entire $row is passed through but only the following columns are actually required:
	 * - afl_var_dump: the main variable store to load
	 * - afl_ip_hex: the IP value to use if necessary
	 *
	 * @param stdClass $row
	 *
	 * @return VariableHolder
	 */
	public function loadVarDump( stdClass $row ): VariableHolder {
		if ( !isset( $row->afl_var_dump ) || !isset( $row->afl_ip_hex ) ) {
			throw new InvalidArgumentException( 'Both afl_var_dump and afl_ip_hex must be set' );
		}
		$variablesFromDb = [];

		$varDumpJsonParseStatus = FormatJson::parse( $row->afl_var_dump, FormatJson::FORCE_ASSOC );
		if ( $varDumpJsonParseStatus->isGood() ) {
			$varDumpAsJson = $varDumpJsonParseStatus->getValue();
			$blobStoreAddress = $varDumpAsJson['_blob'];
			unset( $varDumpAsJson['_blob'] );
			$variablesFromDb = $varDumpAsJson;
		} else {
			$blobStoreAddress = $row->afl_var_dump;
		}

		try {
			$blob = $this->blobStore->getBlob( $blobStoreAddress );
		} catch ( BlobAccessException ) {
			return new VariableHolder;
		}

		$vars = FormatJson::decode( $blob, true );
		$obj = VariableHolder::newFromArray( $vars );

		// If user_unnamed_ip was set when afl_var_dump was saved, it was saved as a visibility boolean
		// and needs to be translated back into an IP
		// user_unnamed_ip uses afl_ip_hex instead of saving the value because afl_ip_hex gets purged and the blob
		// that contains user_unnamed_ip can't be modified
		if (
			$this->varManager->getVar( $obj, 'user_unnamed_ip', VariablesManager::GET_LAX )->toNative()
		) {
			$formattedIP = $row->afl_ip_hex ? IPUtils::formatHex( $row->afl_ip_hex ) : '';
			$obj->setVar( 'user_unnamed_ip', $formattedIP );
		}

		// Add variables from the DB into the returned VariableHolder.
		foreach ( $variablesFromDb as $variable => $value ) {
			$obj->setVar( $variable, $value );
		}

		$this->varManager->translateDeprecatedVars( $obj );
		return $obj;
	}
}
