<?php

global $wgFunnyQuestions;

$messages = array();

$messages['en'] = array(
	'wrong-answer' => 'Sorry, your answer was wrong. Try again!'
);

$messages['de'] = array(
	'wrong-answer' => 'Deine Antwort war leider falsch. Versuche es nocheinmal!'
);

foreach ($messages as $lang => $translations) {
	if (!empty($wgFunnyQuestions[$lang])) {
		foreach (array_keys($wgFunnyQuestions[$lang]) as $question) {
			$messages[$lang]['question-'.sha1($question)] = $question;
		}
	}
}
