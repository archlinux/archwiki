<?php

global $wgFunnyQuestions;

$messages = array();

$messages['en'] = array(
	'question-label' => 'Your answer:',
	'wrong-answer' => 'Sorry, your answer was wrong. Try again!'
);

$messages['de'] = array(
	'question-label' => 'Deine Antwort:',
	'wrong-answer' => 'Deine Antwort war leider falsch. Versuche es nocheinmal!'
);

foreach ($messages as $lang => $translations) {
	if (!empty($wgFunnyQuestions[$lang])) {
		foreach (array_keys($wgFunnyQuestions[$lang]) as $question) {
			$messages[$lang]['question-'.sha1($question)] = $question;
		}		
	}
}

?>

