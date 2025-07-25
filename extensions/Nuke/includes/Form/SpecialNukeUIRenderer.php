<?php

namespace MediaWiki\Extension\Nuke\Form;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Nuke\NukeContext;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Wikimedia\Message\MessageSpecifier;

abstract class SpecialNukeUIRenderer {

	/**
	 * The context of the form.
	 *
	 * @var NukeContext
	 */
	protected NukeContext $context;

	/**
	 * @param NukeContext $context
	 */
	public function __construct( NukeContext $context ) {
		$this->context = $context;
	}

	/**
	 * Alias for {@link \MessageLocalizer::msg} for the request context returned
	 * by {@link NukeContext::getRequestContext}.
	 *
	 * @param string|string[]|MessageSpecifier $key Message key, or array of keys,
	 *   or a MessageSpecifier.
	 * @param mixed ...$params Normal message parameters
	 * @return Message
	 */
	protected function msg( $key, ...$params ): Message {
		return $this->context->getRequestContext()->msg( $key, ...$params );
	}

	/**
	 * @return IContextSource
	 */
	protected function getRequestContext(): IContextSource {
		return $this->context->getRequestContext();
	}

	/**
	 * @return OutputPage
	 */
	protected function getOutput(): OutputPage {
		return $this->context->getRequestContext()->getOutput();
	}

	/**
	 * Prompt for a username or IP address. Directly modifies the
	 * {@link IContextSource::getOutput output} in
	 * {@link NukeContext::getRequestContext $this->context->getRequestContext}.
	 */
	abstract public function showPromptForm(): void;

	/**
	 * Display the prompt form and a list of pages to delete. Directly modifies the
	 * {@link IContextSource::getOutput output} in
	 * {@link NukeContext::getRequestContext $this->context->getRequestContext}.
	 *
	 * @param array{0:Title,1:string|false,2?:string,3?:Title}[][] $pageGroups An array of page title-actor
	 * *   name pairs.
	 * @param bool $hasExcludedResults Whether some results had to be excluded due to the
	 *   user-defined limit
	 * @param string[] $searchNotices An array of i18n strings to provide as notices to show the user with the results
	 */
	abstract public function showListForm( array $pageGroups, bool $hasExcludedResults, array $searchNotices ): void;

	/**
	 * Display a page confirming all pages to be deleted. Directly modifies the
	 * {@link IContextSource::getOutput output} in
	 * {@link NukeContext::getRequestContext $this->context->getRequestContext}.
	 * @return void
	 */
	abstract public function showConfirmForm(): void;

	/**
	 * Show the result page, showing what pages were deleted and what pages were skipped by the
	 * user. Directly modifies the {@link IContextSource::getOutput output} in
	 * {@link NukeContext::getRequestContext $this->context->getRequestContext}.
	 *
	 * @param (Status|string|boolean)[] $deletedPageStatuses The status for each page queued for
	 *   deletion. Can be either `"job"` to indicate that the page was queued for deletion, a
	 *   {@link Status} to indicate if the page was successfully deleted, or `false` if the user
	 *   did not select the page for deletion.
	 * @return void
	 */
	abstract public function showResultPage( array $deletedPageStatuses ): void;

}
