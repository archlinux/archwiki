<?php

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Extension\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logging\LogFormatterFactory;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class TimelineRowFormatterFactory {
	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly CheckUserLookupUtils $checkUserLookupUtils,
		private readonly TitleFormatter $titleFormatter,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly CommentFormatter $commentFormatter,
		private readonly UserFactory $userFactory,
		private readonly CommentStore $commentStore,
		private readonly LogFormatterFactory $logFormatterFactory,
	) {
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
