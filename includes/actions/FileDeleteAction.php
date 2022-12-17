<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @file
 * @ingroup Actions
 */

namespace MediaWiki\Actions;

use DeleteAction;
use ErrorPageError;
use File;
use FileDeleteForm;
use IContextSource;
use LocalFile;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionStatus;
use OldLocalFile;
use Page;
use PermissionsError;
use Title;

/**
 * Handle file deletion
 *
 * @ingroup Actions
 */
class FileDeleteAction extends DeleteAction {
	/** @var File */
	private $file;
	/** @var string Descriptor for the old version of the image, if applicable */
	private $oldImage;
	/** @var OldLocalFile|null Corresponding to oldImage, if applicable */
	private $oldFile;

	/**
	 * @inheritDoc
	 */
	public function __construct( Page $page, IContextSource $context = null ) {
		parent::__construct( $page, $context );
		$services = MediaWikiServices::getInstance();
		$this->file = $this->getArticle()->getFile();
		$this->oldImage = $this->getRequest()->getText( 'oldimage', '' );
		if ( $this->oldImage !== '' ) {
			$this->oldFile = $services->getRepoGroup()->getLocalRepo()->newFromArchiveName(
				$this->getTitle(),
				$this->oldImage
			);
		}
	}

	protected function tempDelete() {
		$file = $this->file;
		/** @var LocalFile $file */'@phan-var LocalFile $file';
		$this->tempExecute( $file );
	}

	private function tempExecute( LocalFile $file ): void {
		$context = $this->getContext();
		$title = $this->getTitle();

		$this->runExecuteChecks();

		$outputPage = $context->getOutput();
		$this->prepareOutput( $context->msg( 'filedelete', $title->getText() ) );

		$request = $context->getRequest();

		$checkFile = $this->oldFile ?: $file;
		if ( !$checkFile->exists() || !$checkFile->isLocal() ) {
			$outputPage->addHTML( $this->prepareMessage( 'filedelete-nofile' ) );
			$outputPage->addReturnTo( $title );
			return;
		}

		// Perform the deletion if appropriate
		$token = $request->getVal( 'wpEditToken' );
		if (
			!$request->wasPosted() ||
			!$context->getUser()->matchEditToken( $token, [ 'delete', $title->getPrefixedText() ] )
		) {
			$this->showConfirm();
			return;
		}

		$permissionStatus = PermissionStatus::newEmpty();
		if ( !$context->getAuthority()->authorizeWrite(
			'delete', $title, $permissionStatus
		) ) {
			throw new PermissionsError( 'delete', $permissionStatus );
		}

		$reason = $this->getDeleteReason();

		# Flag to hide all contents of the archived revisions
		$suppress = $request->getCheck( 'wpSuppress' ) &&
			$context->getAuthority()->isAllowed( 'suppressrevision' );

		$status = FileDeleteForm::doDelete(
			$title,
			$file,
			$this->oldImage,
			$reason,
			$suppress,
			$context->getUser(),
			[],
			$request->getCheck( 'wpDeleteTalk' )
		);

		if ( !$status->isGood() ) {
			$outputPage->wrapWikiTextAsInterface(
				'error',
				$status->getWikiText( 'filedeleteerror-short', 'filedeleteerror-long' )
			);
		}
		if ( $status->isOK() ) {
			$outputPage->setPageTitle( $context->msg( 'actioncomplete' ) );
			$outputPage->addHTML( $this->prepareMessage( 'filedelete-success' ) );
			// Return to the main page if we just deleted all versions of the
			// file, otherwise go back to the description page
			$outputPage->addReturnTo( $this->oldImage ? $title : Title::newMainPage() );

			$this->watchlistManager->setWatch(
				$request->getCheck( 'wpWatch' ),
				$context->getAuthority(),
				$title
			);
		}
	}

	protected function showFormWarnings(): void {
		$this->getOutput()->addHTML( $this->prepareMessage( 'filedelete-intro' ) );
		$this->showSubpagesWarnings();
	}

	/**
	 * Show the confirmation form
	 */
	private function showConfirm() {
		$this->prepareOutputForForm();
		$this->showFormWarnings();
		$this->showForm( $this->getDefaultReason() );
		$this->showEditReasonsLinks();
		$this->showLogEntries();
	}

	/**
	 * Prepare a message referring to the file being deleted,
	 * showing an appropriate message depending upon whether
	 * it's a current file or an old version
	 *
	 * @param string $message Message base
	 * @return string
	 */
	private function prepareMessage( string $message ) {
		if ( $this->oldFile ) {
			$lang = $this->getContext()->getLanguage();
			# Message keys used:
			# 'filedelete-intro-old', 'filedelete-nofile-old', 'filedelete-success-old'
			return $this->getContext()->msg(
				"{$message}-old",
				wfEscapeWikiText( $this->getTitle()->getText() ),
				$lang->date( $this->oldFile->getTimestamp(), true ),
				$lang->time( $this->oldFile->getTimestamp(), true ),
				wfExpandUrl( $this->file->getArchiveUrl( $this->oldImage ), PROTO_CURRENT )
			)->parseAsBlock();
		} else {
			return $this->getContext()->msg(
				$message,
				wfEscapeWikiText( $this->getTitle()->getText() )
			)->parseAsBlock();
		}
	}

	/**
	 * @return string
	 */
	protected function getFormAction(): string {
		$q = [];
		$q['action'] = 'delete';

		if ( $this->oldImage ) {
			$q['oldimage'] = $this->oldImage;
		}

		return $this->getTitle()->getLocalURL( $q );
	}

	/**
	 * @inheritDoc
	 */
	protected function runExecuteChecks(): void {
		parent::runExecuteChecks();

		if ( $this->getContext()->getConfig()->get( MainConfigNames::UploadMaintenance ) ) {
			throw new ErrorPageError( 'filedelete-maintenance-title', 'filedelete-maintenance' );
		}
	}

	/**
	 * TODO Do we need all these messages to be different?
	 * @return string[]
	 */
	protected function getFormMessages(): array {
		return [
			self::MSG_REASON_DROPDOWN => 'filedelete-reason-dropdown',
			self::MSG_REASON_DROPDOWN_SUPPRESS => 'filedelete-reason-dropdown-suppress',
			self::MSG_REASON_DROPDOWN_OTHER => 'filedelete-reason-otherlist',
			self::MSG_COMMENT => 'filedelete-comment',
			self::MSG_REASON_OTHER => 'filedelete-otherreason',
			self::MSG_SUBMIT => 'filedelete-submit',
			self::MSG_LEGEND => 'filedelete-legend',
			self::MSG_EDIT_REASONS => 'filedelete-edit-reasonlist',
			self::MSG_EDIT_REASONS_SUPPRESS => 'filedelete-edit-reasonlist-suppress',
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultReason(): string {
		// TODO Should we autogenerate something for files?
		return $this->getRequest()->getText( 'wpReason' );
	}
}
