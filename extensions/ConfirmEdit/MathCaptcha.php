<?php

/**
 * Captcha class using simple sums and the math renderer
 * Not brilliant, but enough to dissuade casual spam bots
 *
 * @file
 * @ingroup Extensions
 * @author Rob Church <robchur@gmail.com>
 * @copyright © 2006 Rob Church
 * @licence GNU General Public Licence 2.0
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$dir = __DIR__;
require_once $dir . '/ConfirmEdit.php';
$wgCaptchaClass = 'MathCaptcha';

$wgAutoloadClasses['MathCaptcha'] = $dir . '/MathCaptcha.class.php';
