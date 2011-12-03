<?php
/**
 * Aliases for special pages
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'Captcha' => array( 'Captcha' ),
);

/** Arabic (العربية) */
$specialPageAliases['ar'] = array(
	'Captcha' => array( 'كابتشا' ),
);

/** Egyptian Spoken Arabic (مصرى) */
$specialPageAliases['arz'] = array(
	'Captcha' => array( 'كابتشا' ),
);

/** Esperanto (Esperanto) */
$specialPageAliases['eo'] = array(
	'Captcha' => array( 'Kontraŭspamilo' ),
);

/** Estonian (Eesti) */
$specialPageAliases['et'] = array(
	'Captcha' => array( 'Robotilõks' ),
);

/** Persian (فارسی) */
$specialPageAliases['fa'] = array(
	'Captcha' => array( 'کپچا' ),
);

/** Finnish (Suomi) */
$specialPageAliases['fi'] = array(
	'Captcha' => array( 'Ihmiskäyttäjävarmistus' ),
);

/** Japanese (日本語) */
$specialPageAliases['ja'] = array(
	'Captcha' => array( 'キャプチャ' ),
);

/** Colognian (Ripoarisch) */
$specialPageAliases['ksh'] = array(
	'Captcha' => array( 'Kaptscha' ),
);

/** Macedonian (Македонски) */
$specialPageAliases['mk'] = array(
	'Captcha' => array( 'Капча' ),
);

/** Malayalam (മലയാളം) */
$specialPageAliases['ml'] = array(
	'Captcha' => array( 'ക്യാപ്ച' ),
);

/** Serbian Cyrillic ekavian (‪Српски (ћирилица)‬) */
$specialPageAliases['sr-ec'] = array(
	'Captcha' => array( 'Потврдни_код' ),
);

/** Simplified Chinese (‪中文(简体)‬) */
$specialPageAliases['zh-hans'] = array(
	'Captcha' => array( '验证码' ),
);

/** Traditional Chinese (‪中文(繁體)‬) */
$specialPageAliases['zh-hant'] = array(
	'Captcha' => array( '驗證碼' ),
);

/**
 * For backwards compatibility with MediaWiki 1.15 and earlier.
 */
$aliases =& $specialPageAliases;