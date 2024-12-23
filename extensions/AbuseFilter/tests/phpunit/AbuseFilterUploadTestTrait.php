<?php

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\RandomImageGenerator;
use MediaWiki\User\User;

/**
 * This trait can be used to perform uploads in integration tests.
 * NOTE: The implementing classes MUST extend MediaWikiIntegrationTestCase
 * The tearDown method must clear the local file
 * @todo This might be moved to MediaWikiIntegrationTestCase
 *
 * @method string getNewTempDirectory()
 * @method overrideConfigValue( string $key, $value )
 */
trait AbuseFilterUploadTestTrait {
	/**
	 * @var string|null The path represented by this variable will be cleared in tearDown
	 */
	protected $clearPath;

	/**
	 * Clear any temporary uploads, should be called from tearDown
	 */
	protected function clearUploads(): void {
		if ( $this->clearPath ) {
			$backend = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->getBackend();
			$backend->delete( [ 'src' => $this->clearPath ], [ 'force' => 1 ] );
			$this->clearPath = null;
		}
	}

	/**
	 * This is based on ApiUploadTestCase::fakeUploadFile
	 *
	 * @param User $user
	 * @param string $fileName
	 * @param string $pageText
	 * @param string $summary
	 * @return array [ Status, file path ]
	 */
	protected function doUpload( User $user, string $fileName, string $pageText, string $summary ): array {
		global $wgFileExtensions;

		$this->overrideConfigValue( MainConfigNames::FileExtensions, [ ...$wgFileExtensions, 'svg' ] );
		$imgGen = new RandomImageGenerator();
		// Use SVG, since the ImageGenerator doesn't need anything special to create it
		$format = 'svg';
		$mime = 'image/svg+xml';
		$filePath = $imgGen->writeImages( 1, $format, $this->getNewTempDirectory() )[0];
		clearstatcache();
		$request = new FauxRequest( [
			'wpDestFile' => $fileName
		] );
		$request->setUpload( 'wpUploadFile', [
			'name' => $fileName,
			'type' => $mime,
			'tmp_name' => $filePath,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize( $filePath ),
		] );
		/** @var UploadFromFile $ub */
		$ub = UploadBase::createFromRequest( $request );
		$ub->verifyUpload();
		$status = $ub->performUpload( $summary, $pageText, false, $user );
		return [ $status, $ub->getLocalFile()->getPath() ];
	}
}
