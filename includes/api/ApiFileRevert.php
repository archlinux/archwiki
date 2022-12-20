<?php
/**
 * Copyright © 2011 Bryan Tong Minh <Bryan.TongMinh@Gmail.com>
 *
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

use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiFileRevert extends ApiBase {
	/** @var LocalFile */
	protected $file;

	/** @var string */
	protected $archiveName;

	/** @var array */
	protected $params;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		ApiMain $main,
		$action,
		RepoGroup $repoGroup
	) {
		parent::__construct( $main, $action );
		$this->repoGroup = $repoGroup;
	}

	public function execute() {
		$this->useTransactionalTimeLimit();

		$this->params = $this->extractRequestParams();
		// Extract the file and archiveName from the request parameters
		$this->validateParameters();

		// Check whether we're allowed to revert this file
		$this->checkTitleUserPermissions( $this->file->getTitle(), [ 'edit', 'upload' ] );

		$sourceUrl = $this->file->getArchiveVirtualUrl( $this->archiveName );
		$status = $this->file->upload(
			$sourceUrl,
			$this->params['comment'],
			$this->params['comment'],
			0,
			false,
			false,
			$this->getAuthority()
		);

		if ( $status->isGood() ) {
			$result = [ 'result' => 'Success' ];
		} else {
			$result = [
				'result' => 'Failure',
				'errors' => $this->getErrorFormatter()->arrayFromStatus( $status ),
			];
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Validate the user parameters and set $this->archiveName and $this->file.
	 * Throws an error if validation fails
	 */
	protected function validateParameters() {
		// Validate the input title
		$title = Title::makeTitleSafe( NS_FILE, $this->params['filename'] );
		if ( $title === null ) {
			$this->dieWithError(
				[ 'apierror-invalidtitle', wfEscapeWikiText( $this->params['filename'] ) ]
			);
		}
		$localRepo = $this->repoGroup->getLocalRepo();

		// Check if the file really exists
		$this->file = $localRepo->newFile( $title );
		if ( !$this->file->exists() ) {
			$this->dieWithError( 'apierror-missingtitle' );
		}

		// Check if the archivename is valid for this file
		$this->archiveName = $this->params['archivename'];
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable T240141
		$oldFile = $localRepo->newFromArchiveName( $title, $this->archiveName );
		if ( !$oldFile->exists() ) {
			$this->dieWithError( 'filerevert-badversion' );
		}
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'filename' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'comment' => [
				ParamValidator::PARAM_DEFAULT => '',
			],
			'archivename' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	protected function getExamplesMessages() {
		return [
			'action=filerevert&filename=Wiki.png&comment=Revert&' .
				'archivename=20110305152740!Wiki.png&token=123ABC'
				=> 'apihelp-filerevert-example-revert',
		];
	}
}
