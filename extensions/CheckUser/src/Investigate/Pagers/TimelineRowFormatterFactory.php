<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logging\LogFormatterFactory;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class TimelineRowFormatterFactory {
	private LinkRenderer $linkRenderer;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private TitleFormatter $titleFormatter;
	private SpecialPageFactory $specialPageFactory;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;
	private CommentStore $commentStore;
	private LogFormatterFactory $logFormatterFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		CheckUserLookupUtils $checkUserLookupUtils,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		CommentStore $commentStore,
		LogFormatterFactory $logFormatterFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
		$this->commentStore = $commentStore;
		$this->logFormatterFactory = $logFormatterFactory;
	}

	/**
	 * Creates a row formatter
	 *
	 * @param User $user
	 * @param Language $language
	 * @return TimelineRowFormatter
	 */
	public function createRowFormatter( User $user, Language $language ): TimelineRowFormatter {
		return new TimelineRowFormatter(
			$this->linkRenderer,
			$this->checkUserLookupUtils,
			$this->titleFormatter,
			$this->specialPageFactory,
			$this->commentFormatter,
			$this->userFactory,
			$this->commentStore,
			$this->logFormatterFactory,
			$user,
			$language
		);
	}
}
