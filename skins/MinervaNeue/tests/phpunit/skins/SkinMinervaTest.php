<?php

namespace MediaWiki\Minerva;

use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use OutputPage;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \MediaWiki\Minerva\Skins\SkinMinerva
 * @group MinervaNeue
 */
class SkinMinervaTest extends MediaWikiIntegrationTestCase {
	private const ATTRIBUTE_NOTIFICATION_HREF = [
		'key' => 'href',
		'value' => '/wiki/Special:Notifications',
	];
	private const ATTRIBUTE_NOTIFICATION_DATA_EVENT_NAME = [
		'key' => 'data-event-name',
		'value' => 'ui.notifications',
	];

	private const ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT = [
		'key' => 'data-counter-text',
		'value' => "13",
	];
	private const ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM = [
		'key' => 'data-counter-num',
		'value' => 13,
	];
	private const ATTRIBUTE_NOTIFICATION_TITLE = [
		'key' => 'title',
		'value' => "Your alerts",
	];

	/**
	 * @param array $options
	 */
	private function overrideSkinOptions( $options ) {
		$mockOptions = new SkinOptions();
		$mockOptions->setMultiple( $options );
		$this->setService( 'Minerva.SkinOptions', $mockOptions );
	}

	/**
	 * @covers ::setContext
	 * @covers ::hasCategoryLinks
	 */
	public function testHasCategoryLinksWhenOptionIsOff() {
		$outputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPage->expects( $this->never() )
			->method( 'getCategoryLinks' );

		$this->overrideSkinOptions( [ SkinOptions::CATEGORIES => false ] );
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Test' ) );
		$context->setOutput( $outputPage );

		$skin = new SkinMinerva();
		$skin->setContext( $context );
		$skin = TestingAccessWrapper::newFromObject( $skin );

		$this->assertFalse( $skin->hasCategoryLinks() );
	}

	/**
	 * @dataProvider provideHasCategoryLinks
	 * @param array $categoryLinks
	 * @param bool $expected
	 * @covers ::setContext
	 * @covers ::hasCategoryLinks
	 */
	public function testHasCategoryLinks( array $categoryLinks, $expected ) {
		$outputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPage->expects( $this->once() )
			->method( 'getCategoryLinks' )
			->willReturn( $categoryLinks );

		$this->overrideSkinOptions( [ SkinOptions::CATEGORIES => true ] );

		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Test' ) );
		$context->setOutput( $outputPage );

		$skin = new SkinMinerva();
		$skin->setContext( $context );

		$skin = TestingAccessWrapper::newFromObject( $skin );

		$this->assertEquals( $expected, $skin->hasCategoryLinks() );
	}

	public static function provideHasCategoryLinks() {
		return [
			[ [], false ],
			[
				[
					'normal' => '<ul><li><a href="/wiki/Category:1">1</a></li></ul>'
				],
				true
			],
			[
				[
					'hidden' => '<ul><li><a href="/wiki/Category:Hidden">Hidden</a></li></ul>'
				],
				true
			],
			[
				[
					'normal' => '<ul><li><a href="/wiki/Category:1">1</a></li></ul>',
					'hidden' => '<ul><li><a href="/wiki/Category:Hidden">Hidden</a></li></ul>'
				],
				true
			],
			[
				[
					'unexpected' => '<ul><li><a href="/wiki/Category:1">1</a></li></ul>'
				],
				false
			],
		];
	}

	public static function provideGetNotificationButtons() {
		return [
			[
				[],
				[]
			],
			//
			// CIRCLE
			//
			[
	[
					'tag-name' => 'a',
					'classes' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs '
						. ' mw-echo-unseen-notifications',
					'array-attributes' => [
						self::ATTRIBUTE_NOTIFICATION_HREF,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
						self::ATTRIBUTE_NOTIFICATION_TITLE,
						[
							'key' => 'id',
							'value' => 'pt-notifications-alert',
						],
					],
					'data-icon' => [
						'icon' => 'circle'
					],
					'label' => 'Alerts (13)',
				],
				[
					[
						'name' => 'notifications-alert',
						'id' => 'pt-notifications-alert',
						'class' => 'notification-count notification-unseen mw-echo-unseen-notifications mw-list-item',
						'array-links' => [
							[
								'icon' => 'circle',
								'array-attributes' => [
									self::ATTRIBUTE_NOTIFICATION_HREF,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
									self::ATTRIBUTE_NOTIFICATION_TITLE,
									[
										'key' => 'class',
										'value' => 'mw-echo-notifications-badge '
											. 'mw-echo-notification-badge-nojs oo-ui-icon-bellOutline '
											. 'mw-echo-unseen-notifications',
									],
								],
								'text' => 'Alerts (13)'
							]
						]
					]
				]

			],
			//
			// BELL
			//
			[
				[
					'tag-name' => 'a',
					'classes' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs',
					'array-attributes' => [
						self::ATTRIBUTE_NOTIFICATION_HREF,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
						self::ATTRIBUTE_NOTIFICATION_TITLE,
						[
							'key' => 'id',
							'value' => 'pt-notifications-alert',
						],
					],
					'data-icon' => [
						'icon' => 'bellOutline-base20'
					],
					'label' => 'Alerts (13)',
				],
				[
					[
						'html-item' => 'n/a',
						'name' => 'notifications-alert',
						'html' => 'HTML',
						'id' => 'pt-notifications-alert',
						'class' => 'mw-list-item',
						'array-links' => [
							[
								'icon' => 'bellOutline-base20',
								'array-attributes' => [
									self::ATTRIBUTE_NOTIFICATION_HREF,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
									self::ATTRIBUTE_NOTIFICATION_TITLE,
									[
										'key' => 'class',
										'value' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs',
									],
								],
								'text' => 'Alerts (13)'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider provideGetNotificationButtons
	 * @param array $expected
	 * @param array $from
	 * @covers ::getNotificationButtons
	 */
	public function testGetNotificationButtons( $expected, $from ) {
		$btns = SkinMinerva::getNotificationButtons( $from );
		$this->assertEquals( $expected['classes'] ?? '', $btns[0]['classes'] ?? '' );
		$this->assertEquals( $expected['data-attributes'] ?? [], $btns[0]['data-attributes'] ?? [] );
		$this->assertEquals( $expected['data-icon'] ?? [], $btns[0]['data-icon'] ?? [] );
		$this->assertEquals( $expected['data-label'] ?? '', $btns[0]['data-label'] ?? '' );
	}
}
