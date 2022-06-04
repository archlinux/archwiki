<?php
/**
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\TemplateData;

use Message;
use Status;

/**
 * Represents the information about a template,
 * coming from the JSON blob in the <templatedata> tags
 * on wiki pages.
 * This implementation stores the information as a compressed gzip blob
 * in the database.
 */
class TemplateDataCompressedBlob extends TemplateDataBlob {
	// Size of MySQL 'blob' field; page_props table where the data is stored uses one.
	private const MAX_LENGTH = 65535;

	/**
	 * @var string|null In-object cache for getJSONForDatabase()
	 */
	protected $jsonDB = null;

	/**
	 * @inheritDoc
	 */
	protected function parse(): Status {
		$status = parent::parse();
		if ( $status->isOK() ) {
			$length = strlen( $this->getJSONForDatabase() );
			if ( $length > self::MAX_LENGTH ) {
				return Status::newFatal(
					'templatedata-invalid-length',
					Message::numParam( $length ),
					Message::numParam( self::MAX_LENGTH )
				);
			}
		}
		return $status;
	}

	/**
	 * @return string JSON (gzip compressed)
	 */
	public function getJSONForDatabase(): string {
		if ( $this->jsonDB === null ) {
			// Cache for repeat calls
			$this->jsonDB = gzencode( $this->getJSON() );
		}
		return $this->jsonDB;
	}

	/**
	 * Just initialize the data, compression to be done later.
	 *
	 * @param mixed $data Template data
	 */
	protected function __construct( $data ) {
		parent::__construct( $data );
		$this->jsonDB = null;
	}
}
