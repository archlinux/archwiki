<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Formatters;

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;

/**
 * Builds the formatted HTML for a case status reason cell, used by both the
 * REST API handler (for dynamic updates) and the pager (for initial page render).
 */
class StatusReasonFormatter {

	public function __construct(
		private readonly CommentFormatter $commentFormatter,
		private readonly LinkRenderer $linkRenderer,
		private readonly TitleFactory $titleFactory,
	) {
	}

	/**
	 * @param string $reason The raw reason text
	 * @param CaseStatus $status The case status
	 * @param string|null $performerName The username of who changed the status, or null if unknown
	 * @param MessageLocalizer $localizer The localizer to use for message rendering
	 * @return string Formatted HTML
	 */
	public function format(
		string $reason,
		CaseStatus $status,
		string|null $performerName,
		MessageLocalizer $localizer
	): string {
		$displayReason = $reason;
		if ( $reason === '' && $status === CaseStatus::Invalid ) {
			$displayReason = $localizer->msg(
				'checkuser-suggestedinvestigations-status-reason-default-invalid'
			)->text();
		}

		$html = $this->commentFormatter->format( $displayReason );

		if ( $performerName !== null && $status !== CaseStatus::Open ) {
			$userTalkTitle = $this->titleFactory->makeTitle( NS_USER_TALK, $performerName );
			$performerLink = $this->linkRenderer->makeLink( $userTalkTitle, $performerName );
			if ( $html !== '' ) {
				$template = $localizer->msg(
					'checkuser-suggestedinvestigations-status-reason-with-performer'
				)->plain();
				$html = strtr( $template, [ '$1' => $html, '$2' => $performerLink ] );
			} else {
				$html = $performerLink;
			}
		}

		return $html;
	}
}
