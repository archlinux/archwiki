<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Services;
use MediaWiki\Language\Language;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;

class EchoPlainTextDigestEmailFormatter extends EchoEventDigestFormatter {

	private readonly AttributeManager $attributeManager;

	/**
	 * @param User $user
	 * @param Language $language
	 * @param string $digestMode 'daily' or 'weekly'
	 */
	public function __construct(
		User $user,
		Language $language,
		protected string $digestMode,
	) {
		parent::__construct( $user, $language );
		$this->attributeManager = Services::getInstance()->getAttributeManager();
	}

	/**
	 * @param EchoEventPresentationModel[] $models
	 * @return string[] Array of the following format:
	 *               [ 'body'    => formatted email body,
	 *                 'subject' => formatted email subject ]
	 */
	protected function formatModels( array $models ) {
		$content = [];
		foreach ( $models as $model ) {
			$content[$model->getCategory()][] = Sanitizer::stripAllTags( $model->getHeaderMessage()->parse() );
		}

		ksort( $content );

		// echo-email-batch-body-intro-daily
		// echo-email-batch-body-intro-weekly
		$text = $this->msg( 'echo-email-batch-body-intro-' . $this->digestMode )
			->params( $this->user->getName() )->text();

		// Does this need to be a message?
		$bullet = $this->msg( 'echo-email-batch-bullet' )->text();

		foreach ( $content as $category => $items ) {
			$text .= "\n\n--\n\n";
			$text .= $this->getCategoryTitle( $category, count( $items ) );
			$text .= "\n";
			foreach ( $items as $item ) {
				$text .= "\n$bullet $item";
			}
		}

		$colon = $this->msg( 'colon-separator' )->text();
		$text .= "\n\n--\n\n";
		$viewAll = $this->msg( 'echo-email-batch-link-text-view-all-notifications' )->text();
		$link = SpecialPage::getTitleFor( 'Notifications' )->getFullURL( '', false, PROTO_CANONICAL );
		$text .= "$viewAll$colon <$link>";

		$plainTextFormatter = new EchoPlainTextEmailFormatter( $this->user, $this->language );

		$text .= "\n\n{$plainTextFormatter->getFooter()}";

		// echo-email-batch-subject-daily
		// echo-email-batch-subject-weekly
		$subject = $this->msg( 'echo-email-batch-subject-' . $this->digestMode )
			->numParams( count( $models ), count( $models ) )
			->text();

		return [
			'subject' => $subject,
			'body' => $text,
		];
	}

	/**
	 * @param string $category Notification category
	 * @param int $count Number of notifications in this category's section
	 * @return string Formatted category section title
	 */
	private function getCategoryTitle( $category, $count ) {
		return $this->msg( $this->attributeManager->getCategoryTitle( $category ) )
			->numParams( $count )
			->text();
	}
}
