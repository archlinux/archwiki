When /^I click the switch-language page action$/ do
  on(ArticlePage).wait_until_rl_module_ready('skins.minerva.scripts')
  on(ArticlePage).switch_language_page_action_element.when_present.click
end

Then(/^I should see the disabled switch-language page action$/) do
  expect(on(ArticlePage).disabled_switch_langage_page_action_element).to be_visible
end

Then(/^I should see the switch-language page action$/) do
  expect(on(ArticlePage).switch_language_page_action_element).to be_visible
end
