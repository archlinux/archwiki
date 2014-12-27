<?php
# MediaWiki Poem extension v1.1
#
# Based on example code from
# http://www.mediawiki.org/wiki/Manual:Extending_wiki_markup
#
# Other code is © 2005 Nikola Smolenski <smolensk@eunet.yu>
# and © 2011 Zaran <zaran.krleza@gmail.com>
#
# Anyone is allowed to use this code for any purpose.
#
# To install, copy the extension to your extensions directory and add line
# require_once( "$IP/extensions/Poem/Poem.php" );
# to the bottom of your LocalSettings.php
#
# To use, put some text between <poem></poem> tags
#
# For more information see its page at
# http://www.mediawiki.org/wiki/Extension:Poem

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'Poem',
	'author'         => array( 'Nikola Smolenski', 'Brion Vibber', 'Steve Sanbeg' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Poem',
	'descriptionmsg' => 'poem-desc',
);

$dir = __DIR__ . '/';
$wgParserTestFiles[] = $dir . 'poemParserTests.txt';
$wgAutoloadClasses['Poem'] = $dir . 'Poem.class.php';
$wgMessagesDirs['Poem'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Poem'] =  $dir . 'Poem.i18n.php';
$wgHooks['ParserFirstCallInit'][] = 'Poem::init';
