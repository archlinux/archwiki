<?php
namespace MediaWiki\Extension\CheckUser\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Language\FormatterFactory;

/**
 * An API module to allow the CheckUser UI to retrieve and display HTML-formatted block error messages.
 */
class ApiQueryFormattedBlockInfo extends ApiQueryBase {
	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		private readonly FormatterFactory $formatterFactory,
	) {
		parent::__construct( $queryModule, $moduleName );
	}

	public function execute() {
		$performer = $this->getAuthority();
		if ( !$performer->isRegistered() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic' );
		}

		$block = $performer->getBlock();

		$result = $this->getResult();

		if ( $block !== null && $block->isSitewide() ) {
			$msg = $this->formatterFactory->getBlockErrorFormatter( $this )
				->getMessage( $block, $performer->getUser(), null, $this->getRequest()->getIP() );

			$result->addValue( [ 'query', $this->getModuleName() ], 'details', $msg->parse() );
		} else {
			$result->addValue( [ 'query', $this->getModuleName() ], 'details', null );
		}
	}

	/**
	 * This API is only intended to supply data to CheckUser's own frontend.
	 * @codeCoverageIgnore Merely declarative
	 * @return bool
	 */
	public function isInternal(): bool {
		return true;
	}
}
