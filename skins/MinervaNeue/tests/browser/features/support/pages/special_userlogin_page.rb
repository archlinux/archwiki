class SpecialUserLoginPage < ArticlePage
  include PageObject

  page_url 'Special:UserLogin'

  h1(:first_heading, id: 'section_0')

  button(:login, id: 'wpLoginAttempt')
  text_field(:username, name: 'wpName')
  text_field(:password, name: 'wpPassword')
  text_field(:confirm_password, id: 'wpRetype')
  a(:login_wl, class: 'button')
  button(:signup_submit, id: 'wpCreateaccount')
  a(:create_account_link, id: 'mw-createaccount-join')
  div(:error_box, css: '#userlogin2 > .error')
  span(:confirm_password_error_box, css: '#wpRetype + .error')
  a(:password_reset, title: 'Special:PasswordReset')

  # signup specific
  text_field(:confirmation_field, id: 'wpCaptchaWord')
  div(:refresh_captcha, id: 'mf-captcha-reload-container')
end
