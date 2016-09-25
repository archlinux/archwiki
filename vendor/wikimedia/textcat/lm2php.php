<?php
/**
 * Convert data from Perl's TextCat LM format to PHP format
 * used by this tool.
 */
require_once __DIR__.'/TextCat.php';

if($argc != 3) {
	die("Use $argv[0] INPUTDIR OUTPUTDIR\n");
}
if(!file_exists($argv[2])) {
	mkdir($argv[2], 0755, true);
}
$cat = new TextCat($argv[2]);

foreach(new DirectoryIterator($argv[1]) as $file) {
	if(!$file->isFile()) {
		continue;
	}
	$ngrams = array();
	foreach(file($file->getPathname(), FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
		list($word, $score) = explode("\t ", $line, 2);
		$ngrams[$word] = intval($score);
	}
	$cat->writeLanguageFile($ngrams, $argv[2] . "/" . $file->getBasename());
}
exit(0);