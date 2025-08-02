<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Parser;

use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleException;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Tests\Unit\GetFilterEvaluatorTestTrait;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Helper for parser-related tests
 */
abstract class ParserTestCase extends MediaWikiUnitTestCase {
	use GetFilterEvaluatorTestTrait;

	/**
	 * @param LoggerInterface|null $logger
	 * @return FilterEvaluator
	 */
	protected function getParser( ?LoggerInterface $logger = null ) {
		return $this->getFilterEvaluator( $logger );
	}

	/**
	 * @param string $excep
	 * @param string $expr
	 * @param string $caller
	 * @param bool $skippedBlock Whether we're testing code inside a short-circuited block
	 */
	private function exceptionTestInternal( $excep, $expr, $caller, $skippedBlock ) {
		$parser = $this->getParser();
		$msg = "Exception $excep not thrown";
		if ( $caller ) {
			$msg .= " in $caller";
		}
		if ( $skippedBlock ) {
			$msg .= " inside a short-circuited block";
		}
		try {
			if ( $skippedBlock ) {
				// Skipped blocks are, well, skipped when actually parsing.
				$parser->checkSyntaxThrow( $expr );
			} else {
				$parser->parse( $expr );
			}
		} catch ( UserVisibleException $e ) {
			$this->assertEquals( $excep, $e->mExceptionID, $msg . " Got instead:\n$e" );
			return;
		}
		$this->fail( $msg );
	}

	/**
	 * Base method for testing exceptions
	 *
	 * @param string $excep Identifier of the exception (e.g. 'unexpectedtoken')
	 * @param string $expr The expression to test
	 * @param string $caller The function where the exception is thrown, if available
	 */
	protected function exceptionTest( $excep, $expr, $caller = '' ) {
		$this->exceptionTestInternal( $excep, $expr, $caller, false );
	}

	/**
	 * Same as self::exceptionTest, but wraps the given code in a block that will be short-circuited.
	 * Note that this is executed using Parser::checkSyntax, as errors inside a skipped branch won't
	 * ever be reported at runtime.
	 *
	 * @param string $excep
	 * @param string $expr
	 * @param string $caller
	 */
	protected function exceptionTestInSkippedBlock( $excep, $expr, $caller = '' ) {
		$expr = "false & ( $expr )";
		$this->exceptionTestInternal( $excep, $expr, $caller, true );
	}
}
