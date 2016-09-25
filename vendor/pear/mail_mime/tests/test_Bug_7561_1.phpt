--TEST--
Bug #7561   Mail_mimePart::quotedPrintableEncode() misbehavior with mbstring overload
--SKIPIF--
<?php
include "PEAR.php";
if (!extension_loaded('mbstring')){
    if (!PEAR::loadExtension('mbstring')){
        print('SKIP could not load mbstring module');
    }
}
--FILE--
<?php
ini_set('mbstring.language',            'Neutral');
// this isn't working because this option has PHP_INI_SYSTEM mode
ini_set('mbstring.func_overload',       6);
ini_set('mbstring.internal_encoding',   'UTF-8');
ini_set('mbstring.http_output',         'UTF-8');

include("Mail/mimePart.php");
// string is UTF-8 encoded
$input = "Micha\xC3\xABl \xC3\x89ric St\xC3\xA9phane";
$rv = Mail_mimePart::quotedPrintableEncode($input, 76, "\n");
echo $rv, "\n";
--EXPECT--
Micha=C3=ABl =C3=89ric St=C3=A9phane
