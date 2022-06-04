<?php

namespace MediaWiki\Extension\AbuseFilter\EditBox;

use BadMethodCallException;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MessageLocalizer;
use OutputPage;
use User;

/**
 * Factory for EditBoxBuilder objects
 */
class EditBoxBuilderFactory {

	public const SERVICE_NAME = 'AbuseFilterEditBoxBuilderFactory';

	/** @var AbuseFilterPermissionManager */
	private $afPermManager;

	/** @var KeywordsManager */
	private $keywordsManager;

	/** @var bool */
	private $isCodeEditorLoaded;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param KeywordsManager $keywordsManager
	 * @param bool $isCodeEditorLoaded
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		KeywordsManager $keywordsManager,
		bool $isCodeEditorLoaded
	) {
		$this->afPermManager = $afPermManager;
		$this->keywordsManager = $keywordsManager;
		$this->isCodeEditorLoaded = $isCodeEditorLoaded;
	}

	/**
	 * Returns a builder, preferring the Ace version if available
	 * @param MessageLocalizer $messageLocalizer
	 * @param User $user
	 * @param OutputPage $output
	 * @return EditBoxBuilder
	 */
	public function newEditBoxBuilder(
		MessageLocalizer $messageLocalizer,
		User $user,
		OutputPage $output
	): EditBoxBuilder {
		return $this->isCodeEditorLoaded
			? $this->newAceBoxBuilder( $messageLocalizer, $user, $output )
			: $this->newPlainBoxBuilder( $messageLocalizer, $user, $output );
	}

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param User $user
	 * @param OutputPage $output
	 * @return PlainEditBoxBuiler
	 */
	public function newPlainBoxBuilder(
		MessageLocalizer $messageLocalizer,
		User $user,
		OutputPage $output
	): PlainEditBoxBuiler {
		return new PlainEditBoxBuiler(
			$this->afPermManager,
			$this->keywordsManager,
			$messageLocalizer,
			$user,
			$output
		);
	}

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param User $user
	 * @param OutputPage $output
	 * @return AceEditBoxBuiler
	 */
	public function newAceBoxBuilder(
		MessageLocalizer $messageLocalizer,
		User $user,
		OutputPage $output
	): AceEditBoxBuiler {
		if ( !$this->isCodeEditorLoaded ) {
			throw new BadMethodCallException( 'Cannot create Ace box without CodeEditor' );
		}
		return new AceEditBoxBuiler(
			$this->afPermManager,
			$this->keywordsManager,
			$messageLocalizer,
			$user,
			$output,
			$this->newPlainBoxBuilder(
				$messageLocalizer,
				$user,
				$output
			)
		);
	}

}
