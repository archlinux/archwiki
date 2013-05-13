<?php


class FunnyQuestion {

	private static function normalizeAnswer($answer) {
		return preg_replace('/[^a-z0-9]/', '', strtolower($answer));
	}

	private static function getLang() {
		global $wgLang, $wgFunnyQuestions;

		return (!empty($wgFunnyQuestions[$wgLang->getCode()]) ? $wgLang->getCode() : 'en');
	}

	private static function getFunnyQuestion() {
		global $wgFunnyQuestionHash, $wgFunnyQuestions;

		$question = array_rand($wgFunnyQuestions[self::getLang()]);
		$time = time();
		# make sure the user is not able to tell us the question to answer
		$hash = sha1($time.$question.$wgFunnyQuestionHash);

		return array('question' => $question, 'time' => $time, 'hash' => $hash);
	}

	private static function setFunnyCookie() {
		global $wgFunnyQuestionHash, $wgFunnyQuestionRemember, $wgRequest;

		$time = time();
		$wgRequest->response()->setcookie('FunnyQuestionHash', sha1($time.wfGetIP().$wgFunnyQuestionHash), $time+$wgFunnyQuestionRemember);
		$wgRequest->response()->setcookie('FunnyQuestionTime', $time, $time+$wgFunnyQuestionRemember);
	}

	private static function hasFunnyCookie() {
		global $wgFunnyQuestionHash, $wgFunnyQuestionRemember, $wgCookiePrefix;

		return (!empty($_COOKIE[$wgCookiePrefix.'FunnyQuestionHash']) && !empty($_COOKIE[$wgCookiePrefix.'FunnyQuestionTime'])
			&& time() - $wgFunnyQuestionRemember <= $_COOKIE[$wgCookiePrefix.'FunnyQuestionTime']
			&& sha1($_COOKIE[$wgCookiePrefix.'FunnyQuestionTime']. wfGetIP().$wgFunnyQuestionHash) == $_COOKIE[$wgCookiePrefix.'FunnyQuestionHash']);
	}

	private static function checkFunnyQuestion() {
		global $wgFunnyQuestionHash, $wgFunnyQuestions, $wgFunnyQuestionTimeout, $wgFunnyQuestionWait;

		if (self::hasFunnyCookie()) {
			return true;
		}

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
					self::setFunnyCookie();
					return true;
				}
			}
		}

		return false;
	}

	public static function addFunnyQuestionToEditPage($editpage, $output) {
		global $wgUser;

		if (!$wgUser->isLoggedIn() && !self::hasFunnyCookie()) {
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

	public static function checkFunnyQuestionOnEditPage($editor, $text, $section, &$error, $summary) {
		global $wgUser;

		if (!$wgUser->isLoggedIn() && !self::checkFunnyQuestion()) {
			$error = '<div class="errorbox">'.wfMsg('wrong-answer').'</div><br clear="all" />';
		}
		return true;
	}

	public static function addFunnyQuestionToUserCreateForm($template) {
		if (!self::hasFunnyCookie()) {
			$funnyQuestion = self::getFunnyQuestion();
			$template->addInputItem('FunnyAnswer', '', 'text', 'question-label', 'question-'.sha1($funnyQuestion['question']));
			$template->addInputItem('FunnyQuestionTime', $funnyQuestion['time'], 'hidden', '');
			$template->addInputItem('FunnyQuestionHash', $funnyQuestion['hash'], 'hidden', '');
		}
		return true;
	}

	public static function checkFunnyQuestionOnAbortNewAccount($user, &$message) {
		if (!self::checkFunnyQuestion()) {
			$message = wfMsg('wrong-answer');
			return false;
		} else {
			return true;
		}
	}

}

?>
