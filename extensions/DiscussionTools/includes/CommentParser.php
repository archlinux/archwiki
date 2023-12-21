<?php

namespace MediaWiki\Extension\DiscussionTools;

use Config;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Language;
use LogicException;
use MalformedTitleException;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentCommentItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentHeadingItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentThreadItem;
use MediaWiki\Languages\LanguageConverterFactory;
use RuntimeException;
use TitleParser;
use TitleValue;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\DOM\Text;
use Wikimedia\Parsoid\Utils\DOMCompat;

// TODO consider making timestamp parsing not a returned function

class CommentParser {

	/**
	 * How far backwards we look for a signature associated with a timestamp before giving up.
	 * Note that this is not a hard limit on the length of signatures we detect.
	 */
	private const SIGNATURE_SCAN_LIMIT = 100;

	private Config $config;
	private Language $language;
	private LanguageConverterFactory $languageConverterFactory;
	private TitleParser $titleParser;

	private $dateFormat;
	private $digits;
	/** @var string[][] */
	private $contLangMessages;
	private $localTimezone;
	private $timezones;
	private $specialContributionsName;

	private Element $rootNode;
	private TitleValue $title;

	/**
	 * @param Config $config
	 * @param Language $language Content language
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param LanguageData $languageData
	 * @param TitleParser $titleParser
	 */
	public function __construct(
		Config $config,
		Language $language,
		LanguageConverterFactory $languageConverterFactory,
		LanguageData $languageData,
		TitleParser $titleParser
	) {
		$this->config = $config;
		$this->language = $language;
		$this->languageConverterFactory = $languageConverterFactory;
		$this->titleParser = $titleParser;

		$data = $languageData->getLocalData();
		$this->dateFormat = $data['dateFormat'];
		$this->digits = $data['digits'];
		$this->contLangMessages = $data['contLangMessages'];
		$this->localTimezone = $data['localTimezone'];
		$this->timezones = $data['timezones'];
		$this->specialContributionsName = $data['specialContributionsName'];
	}

	/**
	 * Parse a discussion page.
	 *
	 * @param Element $rootNode Root node of content to parse
	 * @param TitleValue $title Title of the page being parsed
	 * @return ContentThreadItemSet
	 */
	public function parse( Element $rootNode, TitleValue $title ): ContentThreadItemSet {
		$this->rootNode = $rootNode;
		$this->title = $title;

		$result = $this->buildThreadItems();
		$this->buildThreads( $result );
		$this->computeIdsAndNames( $result );

		return $result;
	}

	/**
	 * Return the next leaf node in the tree order that is likely a part of a discussion comment,
	 * rather than some boring "separator" element.
	 *
	 * Currently, this can return a Text node with content other than whitespace, or an Element node
	 * that is a "void element" or "text element", except some special cases that we treat as comment
	 * separators (isCommentSeparator()).
	 *
	 * @param Node $node Node to start searching at. This node's children are ignored.
	 * @return Node
	 */
	private function nextInterestingLeafNode( Node $node ): Node {
		$rootNode = $this->rootNode;
		$treeWalker = new TreeWalker(
			$rootNode,
			NodeFilter::SHOW_ELEMENT | NodeFilter::SHOW_TEXT,
			static function ( $n ) use ( $node, $rootNode ) {
				// Ignore this node and its descendants
				// (unless it's the root node, this is a special case for "fakeHeading" handling)
				if ( $node !== $rootNode && ( $n === $node || $n->parentNode === $node ) ) {
					return NodeFilter::FILTER_REJECT;
				}
				// Ignore some elements usually used as separators or headers (and their descendants)
				if ( CommentUtils::isCommentSeparator( $n ) ) {
					return NodeFilter::FILTER_REJECT;
				}
				// Ignore nodes with no rendering that mess up our indentation detection
				if ( CommentUtils::isRenderingTransparentNode( $n ) ) {
					return NodeFilter::FILTER_REJECT;
				}
				if ( CommentUtils::isCommentContent( $n ) ) {
					return NodeFilter::FILTER_ACCEPT;
				}
				return NodeFilter::FILTER_SKIP;
			}
		);
		$treeWalker->currentNode = $node;
		$treeWalker->nextNode();
		if ( !$treeWalker->currentNode ) {
			throw new RuntimeException( 'nextInterestingLeafNode not found' );
		}
		return $treeWalker->currentNode;
	}

	/**
	 * @param string[] $values Values to match
	 * @return string Regular expression
	 */
	private static function regexpAlternateGroup( array $values ): string {
		return '(' . implode( '|', array_map( static function ( string $x ) {
			return preg_quote( $x, '/' );
		}, $values ) ) . ')';
	}

	/**
	 * Get text of localisation messages in content language.
	 *
	 * @param string $contLangVariant Content language variant
	 * @param string[] $messages Message keys
	 * @return string[] Message values
	 */
	private function getMessages( string $contLangVariant, array $messages ): array {
		return array_map( function ( string $key ) use ( $contLangVariant ) {
			return $this->contLangMessages[$contLangVariant][$key];
		}, $messages );
	}

	/**
	 * Get a regexp that matches timestamps generated using the given date format.
	 *
	 * This only supports format characters that are used by the default date format in any of
	 * MediaWiki's languages, namely: D, d, F, G, H, i, j, l, M, n, Y, xg, xkY (and escape characters),
	 * and only dates when MediaWiki existed, let's say 2000 onwards (Thai dates before 1941 are
	 * complicated).
	 *
	 * @param string $contLangVariant Content language variant
	 * @param string $format Date format
	 * @param string $digitsRegexp Regular expression matching a single localised digit, e.g. '[0-9]'
	 * @param array $tzAbbrs Associative array mapping localised timezone abbreviations to
	 *   IANA abbreviations, for the local timezone, e.g. [ 'EDT' => 'EDT', 'EST' => 'EST' ]
	 * @return string Regular expression
	 */
	private function getTimestampRegexp(
		string $contLangVariant, string $format, string $digitsRegexp, array $tzAbbrs
	): string {
		$formatLength = strlen( $format );
		$s = '';
		$raw = false;
		// Adapted from Language::sprintfDate()
		for ( $p = 0; $p < $formatLength; $p++ ) {
			$num = false;
			$code = $format[ $p ];
			if ( $code === 'x' && $p < $formatLength - 1 ) {
				$code .= $format[++$p];
			}
			if ( $code === 'xk' && $p < $formatLength - 1 ) {
				$code .= $format[++$p];
			}

			switch ( $code ) {
				case 'xx':
					$s .= 'x';
					break;
				case 'xg':
					$s .= static::regexpAlternateGroup(
						$this->getMessages( $contLangVariant, Language::MONTH_GENITIVE_MESSAGES )
					);
					break;
				case 'xn':
					$raw = true;
					break;
				case 'd':
					$num = '2';
					break;
				case 'D':
					$s .= static::regexpAlternateGroup(
						$this->getMessages( $contLangVariant, Language::WEEKDAY_ABBREVIATED_MESSAGES )
					);
					break;
				case 'j':
					$num = '1,2';
					break;
				case 'l':
					$s .= static::regexpAlternateGroup(
						$this->getMessages( $contLangVariant, Language::WEEKDAY_MESSAGES )
					);
					break;
				case 'F':
					$s .= static::regexpAlternateGroup(
						$this->getMessages( $contLangVariant, Language::MONTH_MESSAGES )
					);
					break;
				case 'M':
					$s .= static::regexpAlternateGroup(
						$this->getMessages( $contLangVariant, Language::MONTH_ABBREVIATED_MESSAGES )
					);
					break;
				case 'm':
					$num = '2';
					break;
				case 'n':
					$num = '1,2';
					break;
				case 'Y':
					$num = '4';
					break;
				case 'xkY':
					$num = '4';
					break;
				case 'G':
					$num = '1,2';
					break;
				case 'H':
					$num = '2';
					break;
				case 'i':
					$num = '2';
					break;
				case 's':
					$num = '2';
					break;
				case '\\':
					// Backslash escaping
					if ( $p < $formatLength - 1 ) {
						$s .= preg_quote( $format[++$p], '/' );
					} else {
						$s .= preg_quote( '\\', '/' );
					}
					break;
				case '"':
					// Quoted literal
					if ( $p < $formatLength - 1 ) {
						$endQuote = strpos( $format, '"', $p + 1 );
						if ( $endQuote === false ) {
							// No terminating quote, assume literal "
							$s .= '"';
						} else {
							$s .= preg_quote( substr( $format, $p + 1, $endQuote - $p - 1 ), '/' );
							$p = $endQuote;
						}
					} else {
						// Quote at end of string, assume literal "
						$s .= '"';
					}
					break;
				default:
					// Copy whole characters together, instead of single bytes
					$char = mb_substr( mb_strcut( $format, $p, 4 ), 0, 1 );
					$s .= preg_quote( $char, '/' );
					$p += strlen( $char ) - 1;
			}
			if ( $num !== false ) {
				if ( $raw ) {
					$s .= '([0-9]{' . $num . '})';
					$raw = false;
				} else {
					$s .= '(' . $digitsRegexp . '{' . $num . '})';
				}
			}
			// Ignore some invisible Unicode characters that often sneak into copy-pasted timestamps (T308448)
			$s .= '[\\x{200E}\\x{200F}]?';
		}

		$tzRegexp = static::regexpAlternateGroup( array_keys( $tzAbbrs ) );

		// Hard-coded parentheses and space like in Parser::pstPass2
		// Ignore some invisible Unicode characters that often sneak into copy-pasted timestamps (T245784)
		// \uNNNN syntax can only be used from PHP 7.3
		return '/' . $s . ' [\\x{200E}\\x{200F}]?\\(' . $tzRegexp . '\\)/u';
	}

	/**
	 * Get a function that parses timestamps generated using the given date format, based on the result
	 * of matching the regexp returned by getTimestampRegexp()
	 *
	 * @param string $contLangVariant Content language variant
	 * @param string $format Date format, as used by MediaWiki
	 * @param string[]|null $digits Localised digits from 0 to 9, e.g. `[ '0', '1', ..., '9' ]`
	 * @param string $localTimezone Local timezone IANA name, e.g. `America/New_York`
	 * @param array $tzAbbrs Map of localised timezone abbreviations to IANA abbreviations
	 *   for the local timezone, e.g. [ 'EDT' => 'EDT', 'EST' => 'EST' ]
	 * @return callable Parser function
	 */
	private function getTimestampParser(
		string $contLangVariant, string $format, ?array $digits, string $localTimezone, array $tzAbbrs
	): callable {
		$untransformDigits = static function ( string $text ) use ( $digits ) {
			if ( !$digits ) {
				return $text;
			}
			return preg_replace_callback(
				'/[' . implode( '', $digits ) . ']/u',
				static function ( array $m ) use ( $digits ) {
					return (string)array_search( $m[0], $digits, true );
				},
				$text
			);
		};

		$formatLength = strlen( $format );
		$matchingGroups = [];
		for ( $p = 0; $p < $formatLength; $p++ ) {
			$code = $format[$p];
			if ( $code === 'x' && $p < $formatLength - 1 ) {
				$code .= $format[++$p];
			}
			if ( $code === 'xk' && $p < $formatLength - 1 ) {
				$code .= $format[++$p];
			}

			switch ( $code ) {
				case 'xx':
				case 'xn':
					break;
				case 'xg':
				case 'd':
				case 'j':
				case 'D':
				case 'l':
				case 'F':
				case 'M':
				case 'm':
				case 'n':
				case 'Y':
				case 'xkY':
				case 'G':
				case 'H':
				case 'i':
				case 's':
					$matchingGroups[] = $code;
					break;
				case '\\':
					// Backslash escaping
					if ( $p < $formatLength - 1 ) {
						$p++;
					}
					break;
				case '"':
					// Quoted literal
					if ( $p < $formatLength - 1 ) {
						$endQuote = strpos( $format, '"', $p + 1 );
						if ( $endQuote !== false ) {
							$p = $endQuote;
						}
					}
					break;
				default:
					break;
			}
		}

		return function ( array $match ) use (
			$matchingGroups, $untransformDigits, $localTimezone, $tzAbbrs, $contLangVariant
		) {
			if ( is_array( $match[0] ) ) {
				// Strip PREG_OFFSET_CAPTURE data
				unset( $match['offset'] );
				$match = array_map( static function ( array $tuple ) {
					return $tuple[0];
				}, $match );
			}
			$year = 0;
			$monthIdx = 0;
			$day = 0;
			$hour = 0;
			$minute = 0;
			foreach ( $matchingGroups as $i => $code ) {
				$text = $match[$i + 1];
				switch ( $code ) {
					case 'xg':
						$monthIdx = array_search(
							$text,
							$this->getMessages( $contLangVariant, Language::MONTH_GENITIVE_MESSAGES ),
							true
						);
						break;
					case 'd':
					case 'j':
						$day = intval( $untransformDigits( $text ) );
						break;
					case 'D':
					case 'l':
						// Day of the week - unused
						break;
					case 'F':
						$monthIdx = array_search(
							$text,
							$this->getMessages( $contLangVariant, Language::MONTH_MESSAGES ),
							true
						);
						break;
					case 'M':
						$monthIdx = array_search(
							$text,
							$this->getMessages( $contLangVariant, Language::MONTH_ABBREVIATED_MESSAGES ),
							true
						);
						break;
					case 'm':
					case 'n':
						$monthIdx = intval( $untransformDigits( $text ) ) - 1;
						break;
					case 'Y':
						$year = intval( $untransformDigits( $text ) );
						break;
					case 'xkY':
						// Thai year
						$year = intval( $untransformDigits( $text ) ) - 543;
						break;
					case 'G':
					case 'H':
						$hour = intval( $untransformDigits( $text ) );
						break;
					case 'i':
						$minute = intval( $untransformDigits( $text ) );
						break;
					case 's':
						// Seconds - unused, because most timestamp formats omit them
						break;
					default:
						throw new LogicException( 'Not implemented' );
				}
			}

			// The last matching group is the timezone abbreviation
			$tzAbbr = $tzAbbrs[ end( $match ) ];

			// Most of the time, the timezone abbreviation is not necessary to parse the date, since we
			// can assume all times are in the wiki's local timezone.
			$date = new DateTime();
			// setTimezone must be called before setDate/setTime
			$date->setTimezone( new DateTimeZone( $localTimezone ) );
			$date->setDate( $year, $monthIdx + 1, $day );
			$date->setTime( $hour, $minute, 0 );

			// But during the "fall back" at the end of DST, some times will happen twice.
			// Since the timezone abbreviation disambiguates the DST/non-DST times, we can detect
			// when PHP chose the wrong one, and then try the other one. It appears that PHP always
			// uses the later (non-DST) hour, but that behavior isn't documented, so we account for both.
			$dateWarning = null;
			if ( $date->format( 'T' ) !== $tzAbbr ) {
				$altDate = clone $date;
				if ( $date->format( 'I' ) ) {
					// Parsed time is DST, try non-DST by advancing one hour
					$altDate->add( new DateInterval( 'PT1H' ) );
				} else {
					// Parsed time is non-DST, try DST by going back one hour
					$altDate->sub( new DateInterval( 'PT1H' ) );
				}
				if ( $altDate->format( 'T' ) === $tzAbbr ) {
					$date = $altDate;
					$dateWarning = 'Timestamp has timezone abbreviation for the wrong time';
				} else {
					$dateWarning = 'Ambiguous time at DST switchover was parsed';
				}
			}

			// Now set the timezone back to UTC for formatting
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			$date = DateTimeImmutable::createFromMutable( $date );

			return [
				'date' => $date,
				'warning' => $dateWarning,
			];
		};
	}

	/**
	 * Get a regexp that matches timestamps in the local date format, for each language variant.
	 *
	 * This calls getTimestampRegexp() with predefined data for the current wiki.
	 *
	 * @return string[] Regular expressions
	 */
	public function getLocalTimestampRegexps(): array {
		$langConv = $this->languageConverterFactory->getLanguageConverter( $this->language );
		return array_map( function ( $contLangVariant ) {
			return $this->getTimestampRegexp(
				$contLangVariant,
				$this->dateFormat[$contLangVariant],
				'[' . implode( '', $this->digits[$contLangVariant] ) . ']',
				$this->timezones[$contLangVariant]
			);
		}, $langConv->getVariants() );
	}

	/**
	 * Get a function that parses timestamps in the local date format, for each language variant,
	 * based on the result of matching the regexp returned by getLocalTimestampRegexp().
	 *
	 * This calls getTimestampParser() with predefined data for the current wiki.
	 *
	 * @return callable[] Parser functions
	 */
	private function getLocalTimestampParsers(): array {
		$langConv = $this->languageConverterFactory->getLanguageConverter( $this->language );
		return array_map( function ( $contLangVariant ) {
			return $this->getTimestampParser(
				$contLangVariant,
				$this->dateFormat[$contLangVariant],
				$this->digits[$contLangVariant],
				$this->localTimezone,
				$this->timezones[$contLangVariant]
			);
		}, $langConv->getVariants() );
	}

	/**
	 * Given a link node (`<a>`), if it's a link to a user-related page, return their username.
	 *
	 * @param Element $link
	 * @return array|null Array, or null:
	 * - string 'username' Username
	 * - string|null 'displayName' Display name (link text if link target was in the user namespace)
	 */
	private function getUsernameFromLink( Element $link ): ?array {
		// Selflink: use title of current page
		if ( DOMCompat::getClassList( $link )->contains( 'mw-selflink' ) ) {
			$title = $this->title;
		} else {
			$titleString = CommentUtils::getTitleFromUrl( $link->getAttribute( 'href' ) ?? '', $this->config ) ?? '';
			try {
				$title = $this->titleParser->parseTitle( $titleString );
			} catch ( MalformedTitleException $err ) {
				return null;
			}
		}

		$username = null;
		$displayName = null;
		$namespaceId = $title->getNamespace();
		$mainText = $title->getText();

		if ( $namespaceId === NS_USER || $namespaceId === NS_USER_TALK ) {
			$username = $mainText;
			if ( str_contains( $username, '/' ) ) {
				return null;
			}
			if ( $namespaceId === NS_USER ) {
				// Use regex trim for consistency with JS implementation
				$text = preg_replace( [ '/^[\s]+/u', '/[\s]+$/u' ], '', $link->textContent ?? '' );
				// Record the display name if it has been customised beyond changing case
				if ( $text && mb_strtolower( $text ) !== mb_strtolower( $username ) ) {
					$displayName = $text;
				}
			}
		} elseif ( $namespaceId === NS_SPECIAL ) {
			$parts = explode( '/', $mainText );
			if ( count( $parts ) === 2 && $parts[0] === $this->specialContributionsName ) {
				// Normalize the username: users may link to their contributions with an unnormalized name
				$userpage = $this->titleParser->makeTitleValueSafe( NS_USER, $parts[1] );
				if ( !$userpage ) {
					return null;
				}
				$username = $userpage->getText();
			}
		}
		if ( !$username ) {
			return null;
		}
		if ( IPUtils::isIPv6( $username ) ) {
			// Bot-generated links "Preceding unsigned comment added by" have non-standard case
			$username = strtoupper( $username );
		}
		return [
			'username' => $username,
			'displayName' => $displayName,
		];
	}

	/**
	 * Find a user signature preceding a timestamp.
	 *
	 * The signature includes the timestamp node.
	 *
	 * A signature must contain at least one link to the user's userpage, discussion page or
	 * contributions (and may contain other links). The link may be nested in other elements.
	 *
	 * @param Text $timestampNode
	 * @param Node|null $until Node to stop searching at
	 * @return array Result, an associative array with the following keys:
	 *   - Node[] `nodes` Sibling nodes comprising the signature, in reverse order (with
	 *     $timestampNode or its parent node as the first element)
	 *   - string|null `username` Username, null for unsigned comments
	 */
	private function findSignature( Text $timestampNode, ?Node $until = null ): array {
		$sigUsername = null;
		$sigDisplayName = null;
		$length = 0;
		$lastLinkNode = $timestampNode;

		CommentUtils::linearWalkBackwards(
			$timestampNode,
			function ( string $event, Node $node ) use (
				&$sigUsername, &$sigDisplayName, &$lastLinkNode, &$length,
				$until, $timestampNode
			) {
				if ( $event === 'enter' && $node === $until ) {
					return true;
				}
				if ( $length >= static::SIGNATURE_SCAN_LIMIT ) {
					return true;
				}
				if ( CommentUtils::isBlockElement( $node ) ) {
					// Don't allow reaching into preceding paragraphs
					return true;
				}

				if ( $event === 'leave' && $node !== $timestampNode ) {
					$length += $node instanceof Text ?
						mb_strlen( CommentUtils::htmlTrim( $node->textContent ?? '' ) ) : 0;
				}

				// Find the closest link before timestamp that links to the user's user page.
				//
				// Support timestamps being linked to the diff introducing the comment:
				// if the timestamp node is the only child of a link node, use the link node instead
				//
				// Handle links nested in formatting elements.
				if ( $event === 'leave' && $node instanceof Element && strtolower( $node->tagName ) === 'a' ) {
					$user = $this->getUsernameFromLink( $node );
					if ( $user ) {
						// Accept the first link to the user namespace, then only accept links to that user
						if ( $sigUsername === null ) {
							$sigUsername = $user['username'];
						}
						if ( $user['username'] === $sigUsername ) {
							$lastLinkNode = $node;
							if ( $user['displayName'] ) {
								$sigDisplayName = $user['displayName'];
							}
						}
					}
					// Keep looking if a node with links wasn't a link to a user page
					// "Doc James (talk · contribs · email)"
				}
			}
		);

		$range = new ImmutableRange(
			$lastLinkNode->parentNode,
			CommentUtils::childIndexOf( $lastLinkNode ),
			$timestampNode->parentNode,
			CommentUtils::childIndexOf( $timestampNode ) + 1
		);

		// Expand the range so that it covers sibling nodes.
		// This will include any wrapping formatting elements as part of the signature.
		//
		// Helpful accidental feature: users whose signature is not detected in full (due to
		// text formatting) can just wrap it in a <span> to fix that.
		// "Ten Pound Hammer • (What did I screw up now?)"
		// "« Saper // dyskusja »"
		//
		// TODO Not sure if this is actually good, might be better to just use the range...
		$sigNodes = array_reverse( CommentUtils::getCoveredSiblings( $range ) );

		return [
			'nodes' => $sigNodes,
			'username' => $sigUsername,
			'displayName' => $sigDisplayName,
		];
	}

	/**
	 * Callback for TreeWalker that will skip over nodes where we don't want to detect
	 * comments (or section headings).
	 *
	 * @param Node $node
	 * @return int Appropriate NodeFilter constant
	 */
	public static function acceptOnlyNodesAllowingComments( Node $node ): int {
		if ( $node instanceof Element ) {
			$tagName = strtolower( $node->tagName );
			// The table of contents has a heading that gets erroneously detected as a section
			if ( $node->getAttribute( 'id' ) === 'toc' ) {
				return NodeFilter::FILTER_REJECT;
			}
			// Don't detect comments within quotes (T275881)
			if (
				$tagName === 'blockquote' ||
				$tagName === 'cite' ||
				$tagName === 'q'
			) {
				return NodeFilter::FILTER_REJECT;
			}
			$classList = DOMCompat::getClassList( $node );
			// Don't attempt to parse blocks marked 'mw-notalk'
			if ( $classList->contains( 'mw-notalk' ) ) {
				return NodeFilter::FILTER_REJECT;
			}
			// Don't detect comments within references. We can't add replies to them without bungling up
			// the structure in some cases (T301213), and you're not supposed to do that anyway…
			if (
				// <ol class="references"> is the only reliably consistent thing between the two parsers
				$tagName === 'ol' &&
				DOMCompat::getClassList( $node )->contains( 'references' )
			) {
				return NodeFilter::FILTER_REJECT;
			}
		}
		$parentNode = $node->parentNode;
		// Don't detect comments within headings (but don't reject the headings themselves)
		if ( $parentNode instanceof Element && preg_match( '/^h([1-6])$/i', $parentNode->tagName ) ) {
			return NodeFilter::FILTER_REJECT;
		}
		return NodeFilter::FILTER_ACCEPT;
	}

	/**
	 * Convert a byte offset within a text node to a unicode codepoint offset
	 *
	 * @param Text $node Text node
	 * @param int $byteOffset Byte offset
	 * @return int Codepoint offset
	 */
	private static function getCodepointOffset( Text $node, int $byteOffset ): int {
		return mb_strlen( substr( $node->nodeValue ?? '', 0, $byteOffset ) );
	}

	/**
	 * Find a timestamps in a given text node
	 *
	 * @param Text $node
	 * @param string[] $timestampRegexps
	 * @return array|null Array with the following keys:
	 *   - int 'offset' Length of extra text preceding the node that was used for matching (in bytes)
	 *   - int 'parserIndex' Which of the regexps matched
	 *   - array 'matchData' Regexp match data, which specifies the location of the match,
	 *     and which can be parsed using getLocalTimestampParsers() (offsets are in bytes)
	 *   - ImmutableRange 'range' Range covering the timestamp
	 */
	public function findTimestamp( Text $node, array $timestampRegexps ): ?array {
		$nodeText = '';
		$offset = 0;
		// Searched nodes (reverse order)
		$nodes = [];

		while ( $node ) {
			$nodeText = $node->nodeValue . $nodeText;
			$nodes[] = $node;

			// In Parsoid HTML, entities are represented as a 'mw:Entity' node, rather than normal HTML
			// entities. On Arabic Wikipedia, the "UTC" timezone name contains some non-breaking spaces,
			// which apparently are often turned into &nbsp; entities by buggy editing tools. To handle
			// this, we must piece together the text, so that our regexp can match those timestamps.
			if (
				( $previousSibling = $node->previousSibling ) &&
				$previousSibling instanceof Element &&
				$previousSibling->getAttribute( 'typeof' ) === 'mw:Entity'
			) {
				$nodeText = $previousSibling->firstChild->nodeValue . $nodeText;
				$offset += strlen( $previousSibling->firstChild->nodeValue ?? '' );
				$nodes[] = $previousSibling->firstChild;

				// If the entity is preceded by more text, do this again
				if (
					$previousSibling->previousSibling &&
					$previousSibling->previousSibling instanceof Text
				) {
					$offset += strlen( $previousSibling->previousSibling->nodeValue ?? '' );
					$node = $previousSibling->previousSibling;
				} else {
					$node = null;
				}
			} else {
				$node = null;
			}
		}

		foreach ( $timestampRegexps as $i => $timestampRegexp ) {
			$matchData = null;
			// Allows us to mimic match.index in #getComments
			if ( preg_match( $timestampRegexp, $nodeText, $matchData, PREG_OFFSET_CAPTURE ) ) {
				$timestampLength = strlen( $matchData[0][0] );
				// Bytes at the end of the last node which aren't part of the match
				$tailLength = strlen( $nodeText ) - $timestampLength - $matchData[0][1];
				// We are moving right to left, but we start to the right of the end of
				// the timestamp if there is trailing garbage, so that is a negative offset.
				$count = -$tailLength;
				$endNode = $nodes[0];
				$endOffset = strlen( $endNode->nodeValue ?? '' ) - $tailLength;

				foreach ( $nodes as $n ) {
					$count += strlen( $n->nodeValue ?? '' );
					// If we have counted to beyond the start of the timestamp, we are in the
					// start node of the timestamp
					if ( $count >= $timestampLength ) {
						$startNode = $n;
						// Offset is how much we overshot the start by
						$startOffset = $count - $timestampLength;
						break;
					}
				}
				Assert::precondition( $endNode instanceof Node, 'endNode of timestamp is a Node' );
				Assert::precondition( $startNode instanceof Node, 'startNode of timestamp range found' );
				Assert::precondition( is_int( $startOffset ), 'startOffset of timestamp range found' );

				$startOffset = static::getCodepointOffset( $startNode, $startOffset );
				$endOffset = static::getCodepointOffset( $endNode, $endOffset );

				$range = new ImmutableRange( $startNode, $startOffset, $endNode, $endOffset );

				return [
					'matchData' => $matchData,
					// Bytes at the start of the first node which aren't part of the match
					// TODO: Remove this and use 'range' instead
					'offset' => $offset,
					'range' => $range,
					'parserIndex' => $i,
				];
			}
		}
		return null;
	}

	/**
	 * @param Node[] $sigNodes
	 * @param array $match
	 * @param Text $node
	 * @return ImmutableRange
	 */
	private function adjustSigRange( array $sigNodes, array $match, Text $node ): ImmutableRange {
		$firstSigNode = end( $sigNodes );
		$lastSigNode = $sigNodes[0];

		// TODO Document why this needs to be so complicated
		$lastSigNodeOffsetByteOffset =
			$match['matchData'][0][1] + strlen( $match['matchData'][0][0] ) - $match['offset'];
		$lastSigNodeOffset = $lastSigNode === $node ?
			static::getCodepointOffset( $node, $lastSigNodeOffsetByteOffset ) :
			CommentUtils::childIndexOf( $lastSigNode ) + 1;
		$sigRange = new ImmutableRange(
			$firstSigNode->parentNode,
			CommentUtils::childIndexOf( $firstSigNode ),
			$lastSigNode === $node ? $node : $lastSigNode->parentNode,
			$lastSigNodeOffset
		);

		return $sigRange;
	}

	private function buildThreadItems(): ContentThreadItemSet {
		$result = new ContentThreadItemSet();

		$timestampRegexps = $this->getLocalTimestampRegexps();
		$dfParsers = $this->getLocalTimestampParsers();

		$curCommentEnd = $this->rootNode;

		$treeWalker = new TreeWalker(
			$this->rootNode,
			NodeFilter::SHOW_ELEMENT | NodeFilter::SHOW_TEXT,
			[ static::class, 'acceptOnlyNodesAllowingComments' ]
		);
		$lastSigNode = null;
		while ( $node = $treeWalker->nextNode() ) {
			if ( $node instanceof Element && preg_match( '/^h([1-6])$/i', $node->tagName, $match ) ) {
				$headingNodeAndOffset = CommentUtils::getHeadlineNodeAndOffset( $node );
				$headingNode = $headingNodeAndOffset['node'];
				$startOffset = $headingNodeAndOffset['offset'];
				$range = new ImmutableRange(
					$headingNode, $startOffset, $headingNode, $headingNode->childNodes->length
				);
				$curComment = new ContentHeadingItem( $range, (int)( $match[ 1 ] ) );
				$curComment->setRootNode( $this->rootNode );
				$result->addThreadItem( $curComment );
				$curCommentEnd = $node;
			} elseif ( $node instanceof Text && ( $match = $this->findTimestamp( $node, $timestampRegexps ) ) ) {
				$warnings = [];
				$foundSignature = $this->findSignature( $node, $lastSigNode );
				$author = $foundSignature['username'];
				$lastSigNode = $foundSignature['nodes'][0];

				if ( !$author ) {
					// Ignore timestamps for which we couldn't find a signature. It's probably not a real
					// comment, but just a false match due to a copypasted timestamp.
					continue;
				}

				$sigRanges = [];
				$timestampRanges = [];

				$sigRanges[] = $this->adjustSigRange( $foundSignature['nodes'], $match, $node );
				$timestampRanges[] = $match['range'];

				// Everything from the last comment up to here is the next comment
				$startNode = $this->nextInterestingLeafNode( $curCommentEnd );
				$endNode = $lastSigNode;

				// Skip to the end of the "paragraph". This only looks at tag names and can be fooled by CSS, but
				// avoiding that would be more difficult and slower.
				//
				// If this skips over another potential signature, also skip it in the main TreeWalker loop, to
				// avoid generating multiple comments when there is more than one signature on a single "line".
				// Often this is done when someone edits their comment later and wants to add a note about that.
				// (Or when another person corrects a typo, or strikes out a comment, etc.) Multiple comments
				// within one paragraph/list-item result in a confusing double "Reply" button, and we also have
				// no way to indicate which one you're replying to (this might matter in the future for
				// notifications or something).
				CommentUtils::linearWalk(
					$lastSigNode,
					function ( string $event, Node $n ) use (
						&$endNode, &$sigRanges, &$timestampRanges,
						$treeWalker, $timestampRegexps, $node
					) {
						if ( CommentUtils::isBlockElement( $n ) || CommentUtils::isCommentSeparator( $n ) ) {
							// Stop when entering or leaving a block node
							return true;
						}
						if (
							$event === 'leave' &&
							$n instanceof Text && $n !== $node &&
							( $match2 = $this->findTimestamp( $n, $timestampRegexps ) )
						) {
							// If this skips over another potential signature, also skip it in the main TreeWalker loop
							$treeWalker->currentNode = $n;
							// …and add it as another signature to this comment (regardless of the author and timestamp)
							$foundSignature2 = $this->findSignature( $n, $node );
							if ( $foundSignature2['username'] ) {
								$sigRanges[] = $this->adjustSigRange( $foundSignature2['nodes'], $match2, $n );
								$timestampRanges[] = $match2['range'];
							}
						}
						if ( $event === 'leave' ) {
							// Take the last complete node which we skipped past
							$endNode = $n;
						}
					}
				);

				$length = ( $endNode instanceof Text ) ?
					mb_strlen( rtrim( $endNode->nodeValue ?? '', "\t\n\f\r " ) ) :
					// PHP bug: childNodes can be null for comment nodes
					// (it should always be a NodeList, even if the node can't have children)
					( $endNode->childNodes ? $endNode->childNodes->length : 0 );
				$range = new ImmutableRange(
					$startNode->parentNode,
					CommentUtils::childIndexOf( $startNode ),
					$endNode,
					$length
				);

				$startLevel = CommentUtils::getIndentLevel( $startNode, $this->rootNode ) + 1;
				$endLevel = CommentUtils::getIndentLevel( $node, $this->rootNode ) + 1;
				if ( $startLevel !== $endLevel ) {
					$warnings[] = 'Comment starts and ends with different indentation';
				}
				// Should this use the indent level of $startNode or $node?
				$level = min( $startLevel, $endLevel );

				[ 'date' => $dateTime, 'warning' => $dateWarning ] =
					$dfParsers[ $match['parserIndex'] ]( $match['matchData'] );

				if ( $dateWarning ) {
					$warnings[] = $dateWarning;
				}

				$curComment = new ContentCommentItem(
					$level,
					$range,
					$sigRanges,
					$timestampRanges,
					$dateTime,
					$author,
					$foundSignature['displayName']
				);
				$curComment->setRootNode( $this->rootNode );
				if ( $warnings ) {
					$curComment->addWarnings( $warnings );
				}
				if ( $result->isEmpty() ) {
					// Add a fake placeholder heading if there are any comments in the 0th section
					// (before the first real heading)
					$range = new ImmutableRange( $this->rootNode, 0, $this->rootNode, 0 );
					$fakeHeading = new ContentHeadingItem( $range, null );
					$fakeHeading->setRootNode( $this->rootNode );
					$result->addThreadItem( $fakeHeading );
				}
				$result->addThreadItem( $curComment );
				$curCommentEnd = $curComment->getRange()->endContainer;
			}
		}

		return $result;
	}

	/**
	 * Truncate user generated parts of IDs so full ID always fits within a database field of length 255
	 *
	 * @param string $text Text
	 * @return string Truncated text
	 */
	private function truncateForId( string $text ): string {
		return $this->language->truncateForDatabase( $text, 80, '' );
	}

	/**
	 * Given a thread item, return an identifier for it that is unique within the page.
	 *
	 * @param ContentThreadItem $threadItem
	 * @param ContentThreadItemSet $previousItems
	 * @return string
	 */
	private function computeId( ContentThreadItem $threadItem, ContentThreadItemSet $previousItems ): string {
		$id = null;

		if ( $threadItem instanceof ContentHeadingItem && $threadItem->isPlaceholderHeading() ) {
			// The range points to the root note, using it like below results in silly values
			$id = 'h-';
		} elseif ( $threadItem instanceof ContentHeadingItem ) {
			// <span class="mw-headline" …>, or <hN …> in Parsoid HTML
			$headline = $threadItem->getRange()->startContainer;
			Assert::precondition( $headline instanceof Element, 'HeadingItem refers to an element node' );
			$id = 'h-' . $this->truncateForId( $headline->getAttribute( 'id' ) ?? '' );
		} elseif ( $threadItem instanceof ContentCommentItem ) {
			$id = 'c-' . $this->truncateForId( str_replace( ' ', '_', $threadItem->getAuthor() ) ) .
				'-' . $threadItem->getTimestampString();
		} else {
			throw new InvalidArgumentException( 'Unknown ThreadItem type' );
		}

		// If there would be multiple comments with the same ID (i.e. the user left multiple comments
		// in one edit, or within a minute), add the parent ID to disambiguate them.
		$threadItemParent = $threadItem->getParent();
		if ( $threadItemParent instanceof ContentHeadingItem && !$threadItemParent->isPlaceholderHeading() ) {
			// <span class="mw-headline" …>, or <hN …> in Parsoid HTML
			$headline = $threadItemParent->getRange()->startContainer;
			Assert::precondition( $headline instanceof Element, 'HeadingItem refers to an element node' );
			$id .= '-' . $this->truncateForId( $headline->getAttribute( 'id' ) ?? '' );
		} elseif ( $threadItemParent instanceof ContentCommentItem ) {
			$id .= '-' . $this->truncateForId( str_replace( ' ', '_', $threadItemParent->getAuthor() ) ) .
				'-' . $threadItemParent->getTimestampString();
		}

		if ( $threadItem instanceof ContentHeadingItem ) {
			// To avoid old threads re-appearing on popular pages when someone uses a vague title
			// (e.g. dozens of threads titled "question" on [[Wikipedia:Help desk]]: https://w.wiki/fbN),
			// include the oldest timestamp in the thread (i.e. date the thread was started) in the
			// heading ID.
			$oldestComment = $threadItem->getOldestReply();
			if ( $oldestComment ) {
				$id .= '-' . $oldestComment->getTimestampString();
			}
		}

		if ( $previousItems->findCommentById( $id ) ) {
			// Well, that's tough
			$threadItem->addWarning( 'Duplicate comment ID' );
			// Finally, disambiguate by adding sequential numbers, to allow replying to both comments
			$number = 1;
			while ( $previousItems->findCommentById( "$id-$number" ) ) {
				$number++;
			}
			$id = "$id-$number";
		}

		return $id;
	}

	/**
	 * Given a thread item, return an identifier for it that is consistent across all pages and
	 * revisions where this comment might appear.
	 *
	 * Multiple comments on a page can have the same name; use ID to distinguish them.
	 *
	 * @param ContentThreadItem $threadItem
	 * @return string
	 */
	private function computeName( ContentThreadItem $threadItem ): string {
		$name = null;

		if ( $threadItem instanceof ContentHeadingItem ) {
			$name = 'h-';
			$mainComment = $threadItem->getOldestReply();
		} elseif ( $threadItem instanceof ContentCommentItem ) {
			$name = 'c-';
			$mainComment = $threadItem;
		} else {
			throw new InvalidArgumentException( 'Unknown ThreadItem type' );
		}

		if ( $mainComment ) {
			$name .= $this->truncateForId( str_replace( ' ', '_', $mainComment->getAuthor() ) ) .
				'-' . $mainComment->getTimestampString();
		}

		return $name;
	}

	/**
	 * @param ContentThreadItemSet $result
	 */
	private function buildThreads( ContentThreadItemSet $result ): void {
		$lastHeading = null;
		$replies = [];

		foreach ( $result->getThreadItems() as $threadItem ) {
			if ( count( $replies ) < $threadItem->getLevel() ) {
				// Someone skipped an indentation level (or several). Pretend that the previous reply
				// covers multiple indentation levels, so that following comments get connected to it.
				$threadItem->addWarning( 'Comment skips indentation level' );
				while ( count( $replies ) < $threadItem->getLevel() ) {
					$replies[] = end( $replies );
				}
			}

			if ( $threadItem instanceof ContentHeadingItem ) {
				// New root (thread)
				// Attach as a sub-thread to preceding higher-level heading.
				// Any replies will appear in the tree twice, under the main-thread and the sub-thread.
				$maybeParent = $lastHeading;
				while ( $maybeParent && $maybeParent->getHeadingLevel() >= $threadItem->getHeadingLevel() ) {
					$maybeParent = $maybeParent->getParent();
				}
				if ( $maybeParent ) {
					$threadItem->setParent( $maybeParent );
					$maybeParent->addReply( $threadItem );
				}
				$lastHeading = $threadItem;
			} elseif ( isset( $replies[ $threadItem->getLevel() - 1 ] ) ) {
				// Add as a reply to the closest less-nested comment
				$threadItem->setParent( $replies[ $threadItem->getLevel() - 1 ] );
				$threadItem->getParent()->addReply( $threadItem );
			} else {
				$threadItem->addWarning( 'Comment could not be connected to a thread' );
			}

			$replies[ $threadItem->getLevel() ] = $threadItem;
			// Cut off more deeply nested replies
			array_splice( $replies, $threadItem->getLevel() + 1 );
		}
	}

	/**
	 * Set the IDs and names used to refer to comments and headings.
	 * This has to be a separate pass because we don't have the list of replies before
	 * this point.
	 *
	 * @param ContentThreadItemSet $result
	 */
	private function computeIdsAndNames( ContentThreadItemSet $result ): void {
		foreach ( $result->getThreadItems() as $threadItem ) {
			$name = $this->computeName( $threadItem );
			$threadItem->setName( $name );

			$id = $this->computeId( $threadItem, $result );
			$threadItem->setId( $id );

			$result->updateIdAndNameMaps( $threadItem );
		}
	}
}
