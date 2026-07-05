<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\EditPage;

use MediaWiki\Content\Content;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\UnknownContentModelException;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Stats\StatsFactory;

/**
 * Helper for displaying edit conflicts in text content models to users
 *
 * @since 1.31
 * @author Kunal Mehta <legoktm@debian.org>
 */
class TextConflictHelper {

	public ?string $contentModel = null;
	public ?string $contentFormat = null;

	protected string $yourtext = '';
	protected string $storedversion = '';

	private readonly TextboxBuilder $textboxBuilder;

	/**
	 * @param Title $title
	 * @param OutputPage $out
	 * @param StatsFactory $stats
	 * @param string $submitLabel Message key for the label of the submit button
	 * @param IContentHandlerFactory $contentHandlerFactory Required param with legacy support
	 * @param TextboxBuilder|null $textboxBuilder
	 *
	 * @throws UnknownContentModelException
	 */
	public function __construct(
		protected readonly Title $title,
		protected readonly OutputPage $out,
		protected readonly StatsFactory $stats,
		protected readonly string $submitLabel,
		private readonly IContentHandlerFactory $contentHandlerFactory,
		?TextboxBuilder $textboxBuilder = null,
	) {
		$this->contentModel = $title->getContentModel();

		$this->contentFormat = $this->contentHandlerFactory
			->getContentHandler( $this->contentModel )
			->getDefaultFormat();

		$this->textboxBuilder = $textboxBuilder ?? MediaWikiServices::getInstance()->getTextboxBuilder();
	}

	public function setTextboxes( string $yourtext, string $storedversion ): void {
		$this->yourtext = $yourtext;
		$this->storedversion = $storedversion;
	}

	public function setContentModel( string $contentModel ): void {
		$this->contentModel = $contentModel;
	}

	public function setContentFormat( string $contentFormat ): void {
		$this->contentFormat = $contentFormat;
	}

	/**
	 * Record a user encountering an edit conflict
	 */
	public function incrementConflictStats( ?User $user = null ): void {
		$namespace = 'n/a';
		$userBucket = 'n/a';

		// Only include 'standard' namespaces to avoid creating unknown numbers of statsd metrics
		if (
			$this->title->getNamespace() >= NS_MAIN &&
			$this->title->getNamespace() <= NS_CATEGORY_TALK
		) {
			// getNsText() returns empty string if getNamespace() === NS_MAIN
			$namespace = $this->title->getNsText() ?: 'Main';
		}
		if ( $user ) {
			$userBucket = $this->getUserBucket( $user->getEditCount() );
		}
		$this->stats->getCounter( 'edit_failure_total' )
			->setLabel( 'cause', 'conflict' )
			->setLabel( 'namespace', $namespace )
			->setLabel( 'user_bucket', $userBucket )
			->increment();
	}

	/**
	 * Record when a user has resolved an edit conflict
	 */
	public function incrementResolvedStats( ?User $user = null ): void {
		$namespace = 'n/a';
		$userBucket = 'n/a';

		// Only include 'standard' namespaces to avoid creating unknown numbers of statsd metrics
		if (
			$this->title->getNamespace() >= NS_MAIN &&
			$this->title->getNamespace() <= NS_CATEGORY_TALK
		) {
			// getNsText() returns empty string if getNamespace() === NS_MAIN
			$namespace = $this->title->getNsText() ?: 'Main';
		}

		if ( $user ) {
			$userBucket = $this->getUserBucket( $user->getEditCount() );
		}

		$this->stats->getCounter( 'edit_failure_resolved_total' )
			->setLabel( 'cause', 'conflict' )
			->setLabel( 'namespace', $namespace )
			->setLabel( 'user_bucket', $userBucket )
			->increment();
	}

	protected function getUserBucket( ?int $userEdits ): string {
		if ( $userEdits === null ) {
			return 'anon';
		} elseif ( $userEdits > 200 ) {
			return 'over200';
		} elseif ( $userEdits > 100 ) {
			return 'over100';
		} elseif ( $userEdits > 10 ) {
			return 'over10';
		} else {
			return 'under11';
		}
	}

	/**
	 * @return string HTML
	 */
	public function getExplainHeader(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'mw-explainconflict' ],
			$this->out->msg( 'explainconflict', $this->out->msg( $this->submitLabel ) )->parse()
		);
	}

	/**
	 * HTML to build the textbox1 on edit conflicts
	 *
	 * @return string HTML
	 */
	public function getEditConflictMainTextBox( array $customAttribs = [] ): string {
		$classes = $this->textboxBuilder->getTextboxProtectionCSSClasses( $this->title );

		$attribs = [
			'aria-label' => $this->out->msg( 'edit-textarea-aria-label' )->text(),
			'tabindex' => 1,
		];
		$attribs += $customAttribs;
		foreach ( $classes as $class ) {
			Html::addClass( $attribs['class'], $class );
		}

		$attribs = $this->textboxBuilder->buildTextboxAttribs(
			'wpTextbox1',
			$attribs,
			$this->out->getUser(),
			$this->title
		);

		return Html::textarea(
			'wpTextbox1',
			$this->textboxBuilder->addNewLineAtEnd( $this->storedversion ),
			$attribs
		);
	}

	/**
	 * Content to go in the edit form before textbox1
	 *
	 * @see EditPage::$editFormTextBeforeContent
	 * @return string HTML
	 */
	public function getEditFormHtmlBeforeContent(): string {
		return '';
	}

	/**
	 * Content to go in the edit form after textbox1
	 *
	 * @see EditPage::$editFormTextAfterContent
	 * @return string HTML
	 */
	public function getEditFormHtmlAfterContent(): string {
		return '';
	}

	/**
	 * Content to go in the edit form after the footers
	 * (templates on this page, hidden categories, limit report)
	 */
	public function showEditFormTextAfterFooters(): void {
		$this->out->wrapWikiMsg( '<h2>$1</h2>', "yourdiff" );

		$yourContent = $this->toEditContent( $this->yourtext );
		$storedContent = $this->toEditContent( $this->storedversion );
		$handler = $this->contentHandlerFactory->getContentHandler( $this->contentModel );
		$diffEngine = $handler->createDifferenceEngine( $this->out );

		$diffEngine->setContent( $yourContent, $storedContent );
		$diffEngine->showDiff(
			$this->out->msg( 'yourtext' )->parse(),
			$this->out->msg( 'storedversion' )->text()
		);

		$this->out->wrapWikiMsg( '<h2>$1</h2>', "yourtext" );

		$attribs = $this->textboxBuilder->buildTextboxAttribs(
			'wpTextbox2',
			[ 'tabindex' => 6, 'readonly' ],
			$this->out->getUser(),
			$this->title
		);

		$this->out->addHTML(
			Html::textarea( 'wpTextbox2', $this->textboxBuilder->addNewLineAtEnd( $this->yourtext ), $attribs )
		);
	}

	private function toEditContent( string $text ): Content {
		return ContentHandler::makeContent(
			$text,
			$this->title,
			$this->contentModel,
			$this->contentFormat
		);
	}
}
