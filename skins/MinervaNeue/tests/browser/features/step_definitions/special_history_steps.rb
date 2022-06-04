When(/^I open the latest diff$/) do
  on(SpecialHistoryPage).last_contribution_link_element.click
  expect(on(SpecialMobileDiffPage).user_info_element.when_present(20)).to be_visible
end
