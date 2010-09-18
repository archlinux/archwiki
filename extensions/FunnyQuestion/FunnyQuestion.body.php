<?php


class FunnyQuestion {

	private static function normalizeAnswer($answer) {
		return preg_replace('/[^a-z0-9]/', '', strtolower($answer));
	}

	private static function getLang() {
		global $wgLang;

		return (!empty($wgFunnyQuestions[$wgLang->getCode()]) ? $wgFunnyQuestions[$wgLang->getCode()] : 'en');
	}

	private static function getFunnyQuestion() {
		global $wgFunnyQuestionHash, $wgFunnyQuestions;

		$question = array_rand($wgFunnyQuestions[self::getLang()]);
		$time = time();
		# make sure the user is not able to tell us the question to answer
		$hash = sha1($time.$question.$wgFunnyQuestionHash);
	
		return array('question' => $question, 'time' => $time, 'hash' => $hash);
	}

	private static function checkFunnyQuestion() {
		global $wgFunnyQuestionHash, $wgFunnyQuestions, $wgFunnyQuestionTimeout, $wgFunnyQuestionWait;

		if (!empty($_POST['FunnyQuestionTime'])
		&& !empty($_POST['FunnyQuestionHash'])
		&& !empty($_POST['FunnyAnswer'])) {
			$now = time();
			$time = $_POST['FunnyQuestionTime'];
			$hash = $_POST['FunnyQuestionHash'];
			$userAnswer = self::normalizeAnswer($_POST['FunnyAnswer']);
		} else {
			return false;
		}

		if ($now - $time > $wgFunnyQuestionTimeout) {
			return false;
		} elseif ($now - $time < $wgFunnyQuestionWait) {
			return false;
		}

		foreach ($wgFunnyQuestions[self::getLang()] as $question => $answers) {
			if (!is_array($answers)) {
				$answers = array($answers);
			}
			foreach ($answers as $answer) {
				if (self::normalizeAnswer($answer) == $userAnswer
					&& $hash == sha1($time.$question.$wgFunnyQuestionHash)) {
					return true;
				}
			}
		}

		return false;
	}

	public static function addFunnyQuestionToEditPage($editpage, $output) {
		global $wgUser;

		if (!$wgUser->isLoggedIn()) {
			$funnyQuestion = self::getFunnyQuestion();
			$editpage->editFormTextAfterWarn .= 
				'<div class="editOptions">
					<label for="FunnyAnswerField"><strong>'
					.wfMsg('question-'.sha1($funnyQuestion['question'])).'</strong></label>
					<input id="FunnyAnswerField" type="text" name="FunnyAnswer" value="" />
					<input type="hidden" name="FunnyQuestionTime" value="'.$funnyQuestion['time'].'" />
					<input type="hidden" name="FunnyQuestionHash" value="'.$funnyQuestion['hash'].'" />
				</div>';
		}
		return true;
	}

	public static function checkFunnyQuestionOnEditPage($editpage, $text, $section, $error) {
		global $wgUser;

		if (!$wgUser->isLoggedIn() && !self::checkFunnyQuestion()) {
			$error = '<div class="errorbox">'.wfMsg('wrong-answer').'</div><br clear="all" />';
		}
		return true;
	}

	public static function addFunnyQuestionToUserCreateForm($template) {
		$funnyQuestion = self::getFunnyQuestion();
		$template->addInputItem('FunnyAnswer', '', 'text', 'question-label', 'question-'.sha1($funnyQuestion['question']));
		$template->addInputItem('FunnyQuestionTime', $funnyQuestion['time'], 'hidden', '');
		$template->addInputItem('FunnyQuestionHash', $funnyQuestion['hash'], 'hidden', '');
		return true;
	}

	public static function checkFunnyQuestionOnAbortNewAccount($user, $message) {
		if (!self::checkFunnyQuestion()) {
			$message = wfMsg('wrong-answer');
			return false;
		} else {
			return true;
		}
	}

}

?>

