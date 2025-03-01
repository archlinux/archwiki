<?php

namespace MediaWiki\Tests\ResourceLoader;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\StaticHookRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FilePath;
use MediaWiki\ResourceLoader\Image;
use MediaWiki\ResourceLoader\ImageModule;
use Wikimedia\TestingAccessWrapper;

/**
 * @group ResourceLoader
 * @covers \MediaWiki\ResourceLoader\ImageModule
 */
class ImageModuleTest extends ResourceLoaderTestCase {

	public const COMMON_IMAGE_DATA = [
		'abc' => 'abc.gif',
		'def' => [
			'file' => 'def.svg',
			'variants' => [ 'destructive' ],
		],
		'ghi' => [
			'file' => [
				'ltr' => 'ghi.svg',
				'rtl' => 'jkl.svg'
			],
		],
		'mno' => [
			'file' => [
				'ltr' => 'mno-ltr.svg',
				'rtl' => 'mno-rtl.svg',
				'lang' => [
					'he' => 'mno-ltr.svg',
				]
			],
		],
		'pqr' => [
			'file' => [
				'default' => 'pqr-a.svg',
				'lang' => [
					'en' => 'pqr-b.svg',
					'ar,de' => 'pqr-f.svg',
				]
			],
		]
	];

	private const COMMON_IMAGE_VARIANTS = [
		'invert' => [
			'color' => '#FFFFFF',
			'global' => true,
		],
		'primary' => [
			'color' => '#598AD1',
		],
		'constructive' => [
			'color' => '#00C697',
		],
		'destructive' => [
			'color' => '#E81915',
		],
	];

	public static function providerGetModules() {
		return [
			[
				[
					'class' => ImageModule::class,
					'prefix' => 'oo-ui-icon',
					'variants' => self::COMMON_IMAGE_VARIANTS,
					'images' => self::COMMON_IMAGE_DATA,
				],
				'.oo-ui-icon-abc {
	...
}
.oo-ui-icon-abc-invert {
	...
}
.oo-ui-icon-def {
	...
}
.oo-ui-icon-def-invert {
	...
}
.oo-ui-icon-def-destructive {
	...
}
.oo-ui-icon-ghi {
	...
}
.oo-ui-icon-ghi-invert {
	...
}
.oo-ui-icon-mno {
	...
}
.oo-ui-icon-mno-invert {
	...
}
.oo-ui-icon-pqr {
	...
}
.oo-ui-icon-pqr-invert {
	...
}',
			],
			[
				[
					'class' => ImageModule::class,
					'selectorWithoutVariant' => '.mw-ui-icon-{name}:after, .mw-ui-icon-{name}:before',
					'selectorWithVariant' =>
						'.mw-ui-icon-{name}-{variant}:after, .mw-ui-icon-{name}-{variant}:before',
					'variants' => self::COMMON_IMAGE_VARIANTS,
					'images' => self::COMMON_IMAGE_DATA,
				],
				'.mw-ui-icon-abc:after, .mw-ui-icon-abc:before {
	...
}
.mw-ui-icon-abc-invert:after, .mw-ui-icon-abc-invert:before {
	...
}
.mw-ui-icon-def:after, .mw-ui-icon-def:before {
	...
}
.mw-ui-icon-def-invert:after, .mw-ui-icon-def-invert:before {
	...
}
.mw-ui-icon-def-destructive:after, .mw-ui-icon-def-destructive:before {
	...
}
.mw-ui-icon-ghi:after, .mw-ui-icon-ghi:before {
	...
}
.mw-ui-icon-ghi-invert:after, .mw-ui-icon-ghi-invert:before {
	...
}
.mw-ui-icon-mno:after, .mw-ui-icon-mno:before {
	...
}
.mw-ui-icon-mno-invert:after, .mw-ui-icon-mno-invert:before {
	...
}
.mw-ui-icon-pqr:after, .mw-ui-icon-pqr:before {
	...
}
.mw-ui-icon-pqr-invert:after, .mw-ui-icon-pqr-invert:before {
	...
}',
			],
		];
	}

	/**
	 * Test reading files from elsewhere than localBasePath using FilePath.
	 *
	 * This mimics modules modified by skins using 'ResourceModuleSkinStyles' and 'OOUIThemePaths'
	 * skin attributes.
	 */
	public function testResourceLoaderFilePath() {
		$hookContainer = new HookContainer(
			new StaticHookRegistry(),
			$this->getServiceContainer()->getObjectFactory()
		);
		$basePath = __DIR__ . '/../../data/blahblah';
		$filePath = __DIR__ . '/../../data/rlfilepath';
		$testModule = new ImageModule( [
			'localBasePath' => $basePath,
			'remoteBasePath' => 'blahblah',
			'prefix' => 'foo',
			'images' => [
				'eye' => new FilePath( 'eye.svg', $filePath, 'rlfilepath' ),
				'flag' => [
					'file' => [
						'ltr' => new FilePath( 'flag-ltr.svg', $filePath, 'rlfilepath' ),
						'rtl' => new FilePath( 'flag-rtl.svg', $filePath, 'rlfilepath' ),
					],
				],
			],
		] );
		$testModule->setName( 'testModule' );
		$testModule->setHookContainer( $hookContainer );
		$expectedModule = new ImageModule( [
			'localBasePath' => $filePath,
			'remoteBasePath' => 'rlfilepath',
			'prefix' => 'foo',
			'images' => [
				'eye' => 'eye.svg',
				'flag' => [
					'file' => [
						'ltr' => 'flag-ltr.svg',
						'rtl' => 'flag-rtl.svg',
					],
				],
			],
		] );
		$expectedModule->setName( 'testModule' );
		$expectedModule->setHookContainer( $hookContainer );

		$context = $this->getResourceLoaderContext();
		$this->assertEquals(
			$expectedModule->getModuleContent( $context ),
			$testModule->getModuleContent( $context ),
			"Using ResourceLoaderFilePath works correctly"
		);
	}

	/**
	 * @dataProvider providerGetModules
	 */
	public function testGetStyles( $module, $expected ) {
		$module = new ImageModuleTestable(
			$module,
			__DIR__ . '/../../data/resourceloader'
		);
		$module->setHookContainer( new HookContainer(
			new StaticHookRegistry(),
			$this->getServiceContainer()->getObjectFactory()
		) );
		$styles = $module->getStyles( $this->getResourceLoaderContext() );
		$this->assertEquals( $expected, $styles['all'] );
	}

	public function testContext() {
		$context = new Context( new EmptyResourceLoader(), new FauxRequest() );
		$this->assertFalse( $context->getImageObj(), 'Missing image parameter' );

		$context = new Context( new EmptyResourceLoader(), new FauxRequest( [
			'image' => 'example',
		] ) );
		$this->assertFalse( $context->getImageObj(), 'Missing module parameter' );

		$context = new Context( new EmptyResourceLoader(), new FauxRequest( [
			'modules' => 'unknown',
			'image' => 'example',
		] ) );
		$this->assertFalse( $context->getImageObj(), 'Not an image module' );

		$rl = new EmptyResourceLoader();
		$rl->register( 'test', [
			'class' => ImageModule::class,
			'prefix' => 'test',
			'images' => [ 'example' => 'example.png' ],
		] );
		$context = new Context( $rl, new FauxRequest( [
			'modules' => 'test',
			'image' => 'unknown',
		] ) );
		$this->assertFalse( $context->getImageObj(), 'Unknown image' );

		$rl = new EmptyResourceLoader();
		$rl->register( 'test', [
			'class' => ImageModule::class,
			'prefix' => 'test',
			'images' => [ 'example' => 'example.png' ],
		] );
		$context = new Context( $rl, new FauxRequest( [
			'modules' => 'test',
			'image' => 'example',
		] ) );
		$this->assertInstanceOf( Image::class, $context->getImageObj() );
	}

	public static function providerGetStyleDeclarations() {
		return [
			[
				false,
				'background-image: url(original.svg);',
			],
			[
				'data:image/svg+xml',
				'background-image: url(data:image/svg+xml);',
			],
			[
				'data:image/svg+xml',
				"-webkit-mask-image: url(data:image/svg+xml);\n	mask-image: url(data:image/svg+xml);",
				true
			]
		];
	}

	/**
	 * @dataProvider providerGetStyleDeclarations
	 */
	public function testGetStyleDeclarations( $dataUriReturnValue, $expected, $useMaskImage = false ) {
		$module = TestingAccessWrapper::newFromObject( new ImageModule( [ 'useMaskImage' => $useMaskImage ] ) );
		$context = $this->getResourceLoaderContext();
		$image = $this->getImageMock( $context, $dataUriReturnValue );

		$styles = $module->getStyleDeclarations(
			$context,
			$image,
			'load.php'
		);

		$this->assertEquals( $expected, $styles );
	}

	private function getImageMock( Context $context, $dataUriReturnValue ) {
		$image = $this->createMock( Image::class );
		$image->method( 'getDataUri' )
			->willReturn( $dataUriReturnValue );
		$image->method( 'getUrl' )
			->willReturnMap( [
				[ $context, 'load.php', null, 'original', 'original.svg' ],
				[ $context, 'load.php', null, 'rasterized', 'rasterized.png' ],
			] );

		return $image;
	}
}

class ImageModuleTestable extends ImageModule {
	/**
	 * Replace with a stub to make test cases easier to write.
	 * @inheritDoc
	 */
	protected function getCssDeclarations( $primary ): array {
		return [ '...' ];
	}
}
