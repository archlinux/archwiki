<?php

namespace MediaWiki\Tests\Rest;

use Exception;
use InvalidArgumentException;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Validator\BodyValidator;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\NullBodyValidator;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Tests\Unit\DummyServicesTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Message\ListParam;
use Wikimedia\Message\ListType;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @covers \MediaWiki\Rest\Validator\Validator
 * @covers \MediaWiki\Rest\Validator\ParamValidatorCallbacks
 */
class ValidatorTest extends MediaWikiUnitTestCase {
	use DummyServicesTrait;
	use MockAuthorityTrait;

	public static function provideValidateBody() {
		$bodyData = [
			'kittens' => 'cute',
			'number' => 5,
		];

		$emptyBodyValidator = new NullBodyValidator();
		$nonEmptyBodyValidator = new JsonBodyValidator( [
			'kittens' => [
				'rest-param-source' => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'number' => [
				'rest-param-source' => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		] );

		$emptyBodyParams = [ 'bodyContents' => '' ];
		$nonEmptyBodyParams = [
			'bodyContents' => json_encode( (object)$bodyData ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		];
		$nonEmptyBodyUnknownTypeParams = [
			'bodyContents' => json_encode( (object)$bodyData ),
		];

		// Validator::validateBody() normalizes method for case and leading/trailing whitespace.
		// Use various permutations herein to confirm that normalization is happy.
		yield 'GET request with empty body' => [
			$emptyBodyValidator,
			new RequestData( $emptyBodyParams + [ 'method' => 'GET' ] ),
			null
		];

		yield 'GET request with non-empty body' => [
			$emptyBodyValidator,
			new RequestData( $nonEmptyBodyParams + [ 'method' => 'get' ] ),
			null
		];

		yield 'HEAD request with empty body' => [
			$emptyBodyValidator,
			new RequestData( $emptyBodyParams + [ 'method' => 'HEAD' ] ),
			null
		];

		yield 'HEAD request with non-empty body' => [
			$emptyBodyValidator,
			new RequestData( $nonEmptyBodyParams + [ 'method' => 'Head' ] ),
			null

		];
		yield 'DELETE request with empty body' => [
			$emptyBodyValidator,
			new RequestData( $emptyBodyParams + [ 'method' => 'DELETE' ] ),
			null
		];

		yield 'DELETE request with non-empty body' => [
			$nonEmptyBodyValidator,
			new RequestData( $nonEmptyBodyParams + [ 'method' => 'DELETE ' ] ),
			$bodyData
		];

		yield 'POST request with empty body' => [
			$nonEmptyBodyValidator,
			new RequestData( $emptyBodyParams + [ 'method' => 'POST' ] ),
			null
		];

		yield 'POST request with unknown type' => [
			$emptyBodyValidator,
			new RequestData( $nonEmptyBodyUnknownTypeParams + [ 'method' => 'POST' ] ),
			HttpException::class
		];

		yield 'POST request with non-empty body' => [
			$nonEmptyBodyValidator,
			new RequestData( $nonEmptyBodyParams + [ 'method' => ' POST' ] ),
			$bodyData
		];

		yield 'PUT request with empty body' => [
			$nonEmptyBodyValidator,
			new RequestData( $emptyBodyParams + [ 'method' => 'PUT' ] ),
			null
		];

		yield 'PUT request with unknown type' => [
			$emptyBodyValidator,
			new RequestData( $nonEmptyBodyUnknownTypeParams + [ 'method' => 'PUT' ] ),
			HttpException::class
		];

		yield 'PUT request with non-empty body' => [
			$nonEmptyBodyValidator,
			new RequestData( $nonEmptyBodyParams + [ 'method' => ' put ' ] ),
			$bodyData
		];
	}

	/**
	 * If $expected is a string, it must be the name of the expected exception class.
	 * Otherwise, it must match the returned body.
	 *
	 * @dataProvider provideValidateBody
	 */
	public function testValidateBody( BodyValidator $bodyValidator, RequestData $requestData, $expected ) {
		$objectFactory = $this->getDummyObjectFactory();

		/** @var Handler|MockObject $handler */
		$handler = $this->getMockBuilder( Handler::class )
			->onlyMethods( [ 'getBodyValidator', 'execute' ] )
			->getMock();
		$handler->method( 'getBodyValidator' )->willReturn( $bodyValidator );

		$validator = new Validator( $objectFactory, $requestData, $this->mockAnonNullAuthority() );

		if ( is_string( $expected ) ) {
			$this->expectException( $expected );
		}

		$actual = $validator->validateBody( $requestData, $handler );
		$this->assertEquals( $expected, $actual );
	}

	public static function provideParameterSpec() {
		yield 'simple path parameter' => [
			[
				ParamValidator::PARAM_TYPE => 'string',
				Validator::PARAM_SOURCE => 'path',
			],
			[
				'schema' => [
					'type' => 'string',
				],
				'required' => true,
				'description' => 'test parameter',
				'in' => 'path',
				'name' => 'test',
			],
		];

		yield 'optional query parameter' => [
			[
				ParamValidator::PARAM_TYPE => 'float',
				Validator::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
			],
			[
				'schema' => [
					'type' => 'number',
					'format' => 'float',
				],
				'required' => false,
				'description' => 'test parameter',
				'in' => 'query',
				'name' => 'test',
			]
		];

		yield 'array parameter' => [
			[
				ParamValidator::PARAM_TYPE => 'array',
				Validator::PARAM_SOURCE => 'body',
				Validator::PARAM_DESCRIPTION => 'just a test',
				ParamValidator::PARAM_REQUIRED => true,
			],
			[
				'schema' => [
					'type' => 'object',
				],
				'required' => true,
				'description' => 'just a test',
				'in' => 'body',
				'name' => 'test',
			]
		];

		yield 'enum parameter' => [
			[
				ParamValidator::PARAM_TYPE => [ 'a', 'b', 'c' ],
				Validator::PARAM_SOURCE => 'body',
				Validator::PARAM_DESCRIPTION => 'just a test',
				ParamValidator::PARAM_REQUIRED => true,
			],
			[
				'schema' => [
					'type' => 'string',
					'enum' => [ 'a', 'b', 'c' ],
				],
				'required' => true,
				'description' => 'just a test',
				'in' => 'body',
				'name' => 'test',
			]
		];

		yield 'empty enum (should not happen)' => [
			[
				ParamValidator::PARAM_TYPE => [],
				Validator::PARAM_SOURCE => 'body',
				Validator::PARAM_DESCRIPTION => 'just a test',
				ParamValidator::PARAM_REQUIRED => true,
			],
			[
				'schema' => [
					'type' => 'string',
					'enum' => [ '' ], // hacky
				],
				'required' => true,
				'description' => 'just a test',
				'in' => 'body',
				'name' => 'test',
			]
		];

		// Should not happen, but we shouldn't let things explode either.
		yield 'timestamp, missing source (should not happen)' => [
			[
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			[
				'schema' => [
					'type' => 'string',
					'format' => 'mw-timestamp',
				],
				'required' => false,
				'description' => 'test parameter',
				'in' => 'unspecified',
				'name' => 'test',
			]
		];

		// Should not happen, but we shouldn't let things explode either.
		yield 'missing type, pretend not required' => [
			[
				Validator::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => false,
			],
			[
				'schema' => [
					'type' => 'string',
				],
				'required' => true, // path params are always required
				'description' => 'test parameter',
				'in' => 'path',
				'name' => 'test',
			]
		];
	}

	/**
	 * @dataProvider provideParameterSpec
	 * @param array $paramSetting
	 * @param array $expectedSpec
	 */
	public function testParameterSpec( $paramSetting, $expectedSpec ) {
		$spec = Validator::getParameterSpec( 'test', $paramSetting );
		$this->assertArrayEquals( $expectedSpec, $spec, false, true );
	}

	/**
	 * @param string $source
	 * @param string $requestDataKey
	 *
	 * @return \Generator
	 */
	private static function generateParamValidationCases( string $source, $requestDataKey ): \Generator {
		yield "default $source parameter is null" => [
			[
				'defaultparam' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => $source,
				]
			],
			new RequestData( [ 'pathParams' => [] ] ),
			[ 'defaultparam' => null ]
		];
		yield "missing required $source parameter raises Exception" => [
			[
				'requiredparam' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true,
					Validator::PARAM_SOURCE => $source,
				]
			],
			new RequestData( [] ),
			new LocalizedHttpException(
				new MessageValue( 'paramvalidator-missingparam' ),
				400
			)
		];
		yield "$source parameter" => [
			[
				'param' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => $source,
				]
			],
			new RequestData( [ $requestDataKey => [ 'param' => 'test' ] ] ),
			[ 'param' => 'test' ]
		];
		yield "return default $source param set" => [
			[
				'param' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => $source,
					ParamValidator::PARAM_DEFAULT => "default$source"
				]
			],
			new RequestData( [] ),
			[ "param" => "default$source" ]
		];
		yield "throw on malformed $source param" => [
			[
				'param' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => $source,
				]
			],
			new RequestData( [ $requestDataKey => [ 'param' => [] ] ] ),
			new LocalizedHttpException(
				new MessageValue( 'paramvalidator-notmulti' ),
				400
			)
		];
	}

	public static function provideValidateParams() {
		$sources = [ 'path', 'query', 'post' ];
		$paramNames = [
			"path" => "pathParams",
			"query" => "queryParams",
			"post" => "postParams"
		];
		foreach ( $sources as $source ) {
			$cases = self::generateParamValidationCases( $source, $paramNames[ $source ] );
			foreach ( $cases as $name => $case ) {
				yield $name => $case;
			}
		}
		yield 'ignore body param' => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'body',
				]
			],
			new RequestData( [ 'parsedBody' => [ 'foo' => 'test' ] ] ),
			[]
		];
		yield 'unknown source' => [
			[
				'unknown source' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'unknown',
				]
			],
			new RequestData( [ 'parsedBody' => [ 'simplebodyparam1' => 'test' ] ] ),
			new InvalidArgumentException( "Invalid source 'unknown'" )
		];
	}

	/**
	 * If $expected is a string, it must be the name of the expected exception class.
	 * Otherwise, it must match the returned body.
	 *
	 * @dataProvider provideValidateParams
	 */
	public function testValidateParams( $paramSetting, RequestData $requestData, $expected ) {
		$objectFactory = $this->getDummyObjectFactory();
		$validator = new Validator( $objectFactory, $requestData, $this->mockAnonNullAuthority() );

		if ( $expected instanceof Exception ) {
			$this->expectException( get_class( $expected ) );
			$this->expectExceptionCode( $expected->getCode() );
			$this->expectExceptionMessage( $expected->getMessage() );
		}

		$actual = $validator->validateParams( $paramSetting );
		$this->assertEquals( $expected, $actual );
	}

	public static function provideValidateBodyParams() {
		$cases = self::generateParamValidationCases( 'body', 'parsedBody' );
		foreach ( $cases as $name => $case ) {
			yield $name => $case;
		}

		yield 'ignore path param' => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'path',
				]
			],
			new RequestData( [ 'pathParams' => [ 'foo' => 'test' ] ] ),
			[] // The parameter from an unknown source should be ignored.
		];

		yield "valid complex value" => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'array',
					Validator::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => true
				]
			],
			new RequestData( [ 'parsedBody' => [
				'foo' => [ 'x' => 1 ] // this is a complex value
			] ] ),
			[ 'foo' => [ 'x' => 1 ] ]
		];

		yield "invalid complex value" => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'array',
					Validator::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => true
				]
			],
			new RequestData( [ 'parsedBody' => [
				'foo' => 'xyzzy' // not a complex value
			] ] ),
			new LocalizedHttpException( new MessageValue( 'paramvalidator-notarray' ), 400 )
		];

		yield "default complex value" => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'array',
					Validator::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_DEFAULT => []
				]
			],
			new RequestData( [] ),
			[ 'foo' => [] ]
		];
	}

	/**
	 * @dataProvider provideValidateBodyParams
	 */
	public function testValidateBodyParams( $paramSetting, RequestData $requestData, $expected ) {
		$objectFactory = $this->getDummyObjectFactory();
		$validator = new Validator( $objectFactory, $requestData, $this->mockAnonNullAuthority() );

		try {
			$actual = $validator->validateBodyParams( $paramSetting );

			if ( $expected instanceof Exception ) {
				$this->fail( 'Expected exception: ' . $expected );
			}

			$this->assertEquals( $expected, $actual );
		} catch ( LocalizedHttpException $ex ) {
			if ( $expected instanceof LocalizedHttpException ) {
				$this->assertInstanceOf( get_class( $expected ), $ex );
				$this->assertSame( $expected->getCode(), $ex->getCode() );
				$this->assertStringContainsString( 'rest-body-validation-error', $ex->getMessage() );

				// Look at the original validation error that is wrapped inside
				// the rest-body-validation-error as a parameter.
				$param = $ex->getMessageValue()->getParams()[0];
				/** @var MessageValue $validationMessage */
				$validationMessage = $param->getValue();
				$this->assertSame( $expected->getMessageValue()->getKey(), $validationMessage->getKey() );
			} else {
				$this->fail( 'Unexpected exception: ' . $ex );
			}
		}
	}

	public static function provideDetectExtraneousBodyFields() {
		yield 'known body params' => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'body',
				],
				'pathfoo' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'path',
				],
			],
			new RequestData( [ 'parsedBody' => [ 'foo' => 'test' ] ] )
		];
		yield 'no known body params' => [
			[
				'pathfoo' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'path',
				],
			],
			new RequestData( [ 'parsedBody' => [ 'foo' => 'test' ] ] )
		];
		yield 'extraneous body params' => [
			[
				'foo' => [
					ParamValidator::PARAM_TYPE => 'string',
					Validator::PARAM_SOURCE => 'body',
				],
			],
			new RequestData( [ 'parsedBody' => [ 'foo' => 'test', 'xyzzy' => 123 ] ] ),
			new LocalizedHttpException(
				new MessageValue(
					'rest-extraneous-body-fields',
					[ new ListParam( ListType::COMMA, array_keys( [ 'xyzzy' ] ) ) ]
				),
				400
			)
		];
	}

	/**
	 * @dataProvider provideDetectExtraneousBodyFields
	 */
	public function testDetectExtraneousBodyFields( $paramSetting, RequestData $requestData, $expected = null ) {
		$objectFactory = $this->getDummyObjectFactory();
		$validator = new Validator( $objectFactory, $requestData, $this->mockAnonNullAuthority() );

		try {
			$validator->detectExtraneousBodyFields(
				$paramSetting,
				$requestData->getParsedBody()
			);

			if ( $expected instanceof Exception ) {
				$this->fail( 'Expected exception: ' . $expected );
			}

			// all is fine
			$this->addToAssertionCount( 1 );
		} catch ( LocalizedHttpException $ex ) {
			if ( $expected instanceof LocalizedHttpException ) {
				$this->assertInstanceOf( get_class( $expected ), $ex );
				$this->assertSame( $expected->getCode(), $ex->getCode() );
				$this->assertStringContainsString( $expected->getMessage(), $ex->getMessage() );
				$this->assertStringContainsString(
					$expected->getMessageValue()->getKey(),
					$ex->getMessageValue()->getKey()
				);
			} else {
				$this->fail( 'Unexpected exception: ' . $ex );
			}
		}
	}
}
