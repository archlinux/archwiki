When(/^I click a search result$/) do
  on(ArticlePage).search_result_element.when_present.click
end

When(/^I click the search icon$/) do
  on(ArticlePage).wait_until_rl_module_ready('skins.minerva.scripts')
  on(ArticlePage).search_icon_element.when_present.click
  # this check is needed to accommodate for the hack for opening the virtual
  # keyboard (see comments in search.js)
  on(ArticlePage).wait_until do
    on(ArticlePage).current_url.end_with? '#/search'
  end
end

When(/^I click the search input field$/) do
  on(ArticlePage).search_box_placeholder_element.when_present.click
end

When(/^I click the search button$/) do
  on(ArticlePage).search_icon_element.when_present.click
end

When(/^I see the search in pages button$/) do
  expect(on(ArticlePage).search_within_pages_element.when_visible).to be_visible
end

When(/^I click the search in pages button$/) do
  on(ArticlePage).search_content_header_element.when_present.click
end

When(/^I click a search watch star$/) do
  on(ArticlePage).search_watchstars_element.when_present(15).click
end

When(/^I press the enter key$/) do
  on(ArticlePage).search_box2_element.when_present.send_keys :enter
end

When(/^I click the search overlay close button$/) do
  on(ArticlePage).search_overlay_close_button_element.when_visible.click
end

When(/^I see the search overlay$/) do
  on(ArticlePage).search_overlay_element.when_present
end

When(/^I type into search box "(.+)"$/) do |search_term|
  on(ArticlePage) do |page|
    if page.search_box2_element.exists?
      # Type in JavaScript mode
      page.search_box2 = search_term
    else
      page.search_box_placeholder = search_term
    end
  end
end

Then(/^I should not see the search overlay$/) do
  expect(on(ArticlePage).search_overlay_element).not_to be_visible
end

Then(/^I should see a list of search results$/) do
  expect(on(SearchPage).list_of_results_element.when_present(10)).to be_visible
end

Then(/^I should see the search button$/) do
  expect(on(ArticlePage).search_icon_element.when_present).to be_visible
end

When(/^I should see the search overlay$/) do
  expect(on(ArticlePage).search_overlay_element.when_present).to be_visible
end

Then(/^search results should contain "(.+)"$/) do |text|
  expect(on(ArticlePage).search_result_heading_element.when_present.text).to eq text
end

Then(/^I should not see '#\/search' in URL$/) do
  expect(on(ArticlePage).current_url.end_with? '#/search').to be false
end
