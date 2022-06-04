<?php

namespace MediaWiki\Extension\AbuseFilter\Api;

use ApiBase;
use ApiMain;
use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use User;

class UnblockAutopromote extends ApiBase {

	/** @var BlockAutopromoteStore */
	private $afBlockAutopromoteStore;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param BlockAutopromoteStore $afBlockAutopromoteStore
	 */
	public function __construct(
		ApiMain $main,
		$action,
		BlockAutopromoteStore $afBlockAutopromoteStore
	) {
		parent::__construct( $main, $action );
		$this->afBlockAutopromoteStore = $afBlockAutopromoteStore;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->checkUserRightsAny( 'abusefilter-modify' );

		$params = $this->extractRequestParams();
		$target = User::newFromName( $params['user'] );

		if ( $target === false ) {
			$encParamName = $this->encodeParamName( 'user' );
			$this->dieWithError(
				[ 'apierror-baduser', $encParamName, wfEscapeWikiText( $params['user'] ) ],
				"baduser_{$encParamName}"
			);
		}

		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			$this->dieBlocked( $block );
		}

		$msg = $this->msg( 'abusefilter-tools-restoreautopromote' )->inContentLanguage()->text();
		$blockAutopromoteStore = $this->afBlockAutopromoteStore;
		$res = $blockAutopromoteStore->unblockAutopromote( $target, $this->getUser(), $msg );

		if ( !$res ) {
			$this->dieWithError( [ 'abusefilter-reautoconfirm-none', $target->getName() ], 'notsuspended' );
		}

		$finalResult = [ 'user' => $params['user'] ];
		$this->getResult()->addValue( null, $this->getModuleName(), $finalResult );
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'user' => [
				ApiBase::PARAM_TYPE => 'user',
				ApiBase::PARAM_REQUIRED => true
			],
			'token' => null,
		];
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=abusefilterunblockautopromote&user=Example&token=123ABC'
				=> 'apihelp-abusefilterunblockautopromote-example-1',
		];
	}
}
