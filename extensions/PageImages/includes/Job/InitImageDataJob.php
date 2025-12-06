<?php

namespace PageImages\Job;

use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\JobQueue\Job;
use MediaWiki\Title\Title;
use RefreshLinks;
use Wikimedia\Rdbms\ILBFactory;

class InitImageDataJob extends Job {
	/**
	 * @param Title $title Title object associated with this job
	 * @param array $params Parameters to the job, containing an array of
	 * page ids representing which pages to process
	 * @param ILBFactory $lbFactory
	 */
	public function __construct(
		Title $title,
		array $params,
		private readonly ILBFactory $lbFactory,
	) {
		parent::__construct( 'InitImageDataJob', $title, $params );
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		foreach ( $this->params['page_ids'] as $id ) {
			try {
				RefreshLinks::fixLinksFromArticle( $id );
				$this->lbFactory->waitForReplication();
			} catch ( \Exception $e ) {
				// There are some broken pages out there that just don't parse.
				// Log it and keep on trucking.
				MWExceptionHandler::logException( $e );
			}
		}
		return true;
	}
}
