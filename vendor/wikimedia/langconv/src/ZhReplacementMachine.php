<?php

namespace Wikimedia\LangConv;

class ZhReplacementMachine extends FstReplacementMachine {

	public function __construct() {
		parent::__construct(
			'zh',
			[
				'zh-hans',
				'zh-hant',
				'zh-cn',
				'zh-hk',
				'zh-mo',
				'zh-my',
				'zh-sg',
				'zh-tw',
			]
		);
	}

	/** @inheritDoc */
	public function isValidCodePair( $destCode, $invertCode ) {
		if ( $destCode === $invertCode ) {
			return true;
		}
		switch ( $destCode ) {
			case 'zh-cn':
				if ( $invertCode === 'zh-tw' ) {
					return true;
				}
				// fall through
			case 'zh-sg':
			case 'zh-my':
			case 'zh-hans':
				return $invertCode === 'zh-hant';
			case 'zh-tw':
				if ( $invertCode === 'zh-cn' ) {
					return true;
				}
				// fall through
			case 'zh-hk':
			case 'zh-mo':
			case 'zh-hant':
				return $invertCode === 'zh-hans';
			default:
				return false;
		}
	}

}
