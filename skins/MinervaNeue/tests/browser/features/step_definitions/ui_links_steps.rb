Then(/^I should see a link to the privacy page$/) do
  expect(on(ArticlePage).privacy_link_element).to be_visible
end

Then(/^I should see a link to the terms of use$/) do
  expect(on(ArticlePage).terms_link_element).to be_visible
end

Then(/^I should see the link to the user page of the last editor$/) do
  # T132753
  on(ArticlePage) do |page|
    page.wait_until_rl_module_ready('skins.minerva.scripts')
    expect(page.last_modified_bar_history_userpage_link_element).to be_visible
  end
end

Then(/^I should see the history link$/) do
  expect(on(ArticlePage).standalone_edit_history_link_element).to be_visible
end

Then(/^I should see the beta mode indicator$/) do
  expect(on(ArticlePage).beta_mode_indicator_element).to be_visible
end

Then(/^I should not see the beta mode indicator$/) do
  expect(on(ArticlePage).beta_mode_indicator_element).not_to be_visible
end

Then(/^I should see the last modified bar history link$/) do
  expect(on(ArticlePage).last_modified_bar_history_link_element).to be_visible
end

Then(/^I should see the license link$/) do
  expect(on(ArticlePage).license_link_element).to be_visible
end

Then(/^I should see the switch to desktop link$/) do
  expect(on(ArticlePage).desktop_link_element).to be_visible
end
