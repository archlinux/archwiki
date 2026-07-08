<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Formatters;

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Formatters\StatusReasonFormatter;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Formatters\StatusReasonFormatter
 */
class StatusReasonFormatterTest extends MediaWikiUnitTestCase {

	private LinkRenderer $linkRenderer;
	private MessageLocalizer $messageLocalizer;
	private StatusReasonFormatter $formatter;

	protected function setUp(): void {
		parent::setUp();

		$commentFormatter = $this->createMock( CommentFormatter::class );
		$commentFormatter->method( 'format' )
			->willReturnArgument( 0 );

		$this->linkRenderer = $this->createMock( LinkRenderer::class );

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitle' )
			->willReturn( $this->createMock( Title::class ) );

		$this->messageLocalizer = new FakeQqxMessageLocalizer();

		$this->formatter = new StatusReasonFormatter(
			$commentFormatter,
			$this->linkRenderer,
			$titleFactory
		);
	}

	/**
	 * @dataProvider provideFormatCases
	 */
	public function testFormat(
		string $reason,
		CaseStatus $status,
		string|null $performerName,
		bool $expectPerformerLink,
		array $expectContains,
		array $expectNotContains
	): void {
		if ( $expectPerformerLink ) {
			$this->linkRenderer->method( 'makeLink' )
				->willReturn( '<a>PerformerName</a>' );
		} else {
			$this->linkRenderer->expects( $this->never() )
				->method( 'makeLink' );
		}

		$result = $this->formatter->format(
			$reason,
			$status,
			$performerName,
			$this->messageLocalizer
		);

		foreach ( $expectContains as $text ) {
			$this->assertStringContainsString( $text, $result );
		}

		foreach ( $expectNotContains as $text ) {
			$this->assertStringNotContainsString( $text, $result );
		}
	}

	public static function provideFormatCases(): array {
		return [
			'Open with performer has no link' => [
				'reason' => 'some reason',
				'status' => CaseStatus::Open,
				'performerName' => 'PerformerName',
				'expectPerformerLink' => false,
				'expectContains' => [],
				'expectNotContains' => [],
			],
			'Resolved with performer appends link' => [
				'reason' => 'some reason',
				'status' => CaseStatus::Resolved,
				'performerName' => 'PerformerName',
				'expectPerformerLink' => true,
				'expectContains' => [ '(checkuser-suggestedinvestigations-status-reason-with-performer)' ],
				'expectNotContains' => [],
			],
			'Invalid with performer appends link' => [
				'reason' => 'non-empty reason',
				'status' => CaseStatus::Invalid,
				'performerName' => 'PerformerName',
				'expectPerformerLink' => true,
				'expectContains' => [ '(checkuser-suggestedinvestigations-status-reason-with-performer)' ],
				'expectNotContains' => [],
			],
			'Resolved without performer has no link' => [
				'reason' => 'some reason',
				'status' => CaseStatus::Resolved,
				'performerName' => null,
				'expectPerformerLink' => false,
				'expectContains' => [],
				'expectNotContains' => [ '(checkuser-suggestedinvestigations-status-reason-with-performer)' ],
			],
			'Invalid without performer has no link' => [
				'reason' => '',
				'status' => CaseStatus::Invalid,
				'performerName' => null,
				'expectPerformerLink' => false,
				'expectContains' => [],
				'expectNotContains' => [ '(checkuser-suggestedinvestigations-status-reason-with-performer)' ],
			],
			'Invalid empty reason uses default message' => [
				'reason' => '',
				'status' => CaseStatus::Invalid,
				'performerName' => null,
				'expectPerformerLink' => false,
				'expectContains' => [ '(checkuser-suggestedinvestigations-status-reason-default-invalid)' ],
				'expectNotContains' => [ '(checkuser-suggestedinvestigations-status-reason-with-performer)' ],
			],
			'Invalid non-empty reason ignores default message' => [
				'reason' => 'non-empty reason',
				'status' => CaseStatus::Invalid,
				'performerName' => 'PerformerName',
				'expectPerformerLink' => true,
				'expectContains' => [ '(checkuser-suggestedinvestigations-status-reason-with-performer)' ],
				'expectNotContains' => [ '(checkuser-suggestedinvestigations-status-reason-default-invalid)' ],
			],
			'Non-invalid empty reason does not use default' => [
				'reason' => '',
				'status' => CaseStatus::Resolved,
				'performerName' => 'PerformerName',
				'expectPerformerLink' => true,
				'expectContains' => [ 'PerformerName' ],
				'expectNotContains' => [
					'(checkuser-suggestedinvestigations-status-reason-default-invalid)',
					'(checkuser-suggestedinvestigations-status-reason-with-performer)',
				],
			],
		];
	}
}
