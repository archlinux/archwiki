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
		$wgRequest->response()->setcookie('FunnyQuestionHash', sha1($time.$wgRequest->getIP().$wgFunnyQuestionHash), $time+$wgFunnyQuestionRemember);
		$wgRequest->response()->setcookie('FunnyQuestionTime', $time, $time+$wgFunnyQuestionRemember);
	}

	private static function hasFunnyCookie() {
		global $wgFunnyQuestionHash, $wgFunnyQuestionRemember, $wgCookiePrefix, $wgRequest;

		return (!empty($_COOKIE[$wgCookiePrefix.'FunnyQuestionHash']) && !empty($_COOKIE[$wgCookiePrefix.'FunnyQuestionTime'])
			&& time() - $wgFunnyQuestionRemember <= $_COOKIE[$wgCookiePrefix.'FunnyQuestionTime']
			&& sha1($_COOKIE[$wgCookiePrefix.'FunnyQuestionTime']. $wgRequest->getIP().$wgFunnyQuestionHash) == $_COOKIE[$wgCookiePrefix.'FunnyQuestionHash']);
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

	public static function addFunnyQuestionToEditPage(EditPage $editpage, OutputPage $output): bool {
		global $wgUser;

		if (!$wgUser->isLoggedIn() && !self::hasFunnyCookie()) {
			$funnyQuestion = self::getFunnyQuestion();
			$editpage->editFormTextAfterWarn .=
				'<div class="editOptions">
					<label for="FunnyAnswerField"><strong>'
					.wfMessage('question-'.sha1($funnyQuestion['question']))->text().'</strong></label>
					<input id="FunnyAnswerField" type="text" name="FunnyAnswer" value="" />
					<input type="hidden" name="FunnyQuestionTime" value="'.$funnyQuestion['time'].'" />
					<input type="hidden" name="FunnyQuestionHash" value="'.$funnyQuestion['hash'].'" />
				</div>';
		}
		return true;
	}

	public static function checkFunnyQuestionOnEditPage(EditPage $editor, $text, $section, &$error, $summary) {
		global $wgUser;

		if (!$wgUser->isLoggedIn() && !self::checkFunnyQuestion()) {
			$error = '<div class="errorbox">'.wfMessage('wrong-answer')->text().'</div><br clear="all" />';
		}
		return true;
	}

	public static function addFunnyQuestionToUserCreateForm(FakeAuthTemplate $template) {
		if (!self::hasFunnyCookie()) {
			$funnyQuestion = self::getFunnyQuestion();
			$template->addInputItem('FunnyAnswer', '', 'text', 'question-'.sha1($funnyQuestion['question']));
			$template->extend('formheader', '<style>label[for=FunnyAnswer]{display:block;margin-top:15px;}</style>');
			$template->extend('formheader', '<input type="hidden" name="FunnyQuestionTime" value="'.$funnyQuestion['time'].'" />');
            $template->extend('formheader', '<input type="hidden" name="FunnyQuestionHash" value="'.$funnyQuestion['hash'].'" />');
		}
		return true;
	}

	public static function checkFunnyQuestionOnAbortNewAccount(User $user, &$message) {
		if (!self::checkFunnyQuestion()) {
			$message = wfMessage('wrong-answer')->text();
			return false;
		} else {
			return true;
		}
	}
}
