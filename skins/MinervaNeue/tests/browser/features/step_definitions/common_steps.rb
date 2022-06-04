Given /^I am in beta mode$/ do
  visit(MainPage) do |page|
    page_uri = URI.parse(page.page_url_value)

    # A domain is explicitly given to avoid a bug in earlier versions of Chrome
    domain = page_uri.host == 'localhost' ? nil : page_uri.host
    # FIXME: remove 'mf_useformat' cookie from here
    browser.cookies.add 'mf_useformat', 'true', domain: domain
    browser.cookies.add 'optin', 'beta', domain: domain

    page.refresh
  end
end

Given(/^I am logged in as a user with a > (\d+) edit count$/) do |count|
  api.meta(:userinfo, uiprop: 'editcount').data['editcount'].upto(count.to_i) do |n|
    api.create_page("Ensure #{user} edit count - #{n + 1}", 'foo')
  end
  log_in
end

Given(/^I am logged into the mobile website$/) do
  step 'I am using the mobile site'
  log_in
  # avoids login failing (see https://phabricator.wikimedia.org/T109593)
  expect(on(ArticlePage).is_authenticated_element.when_present(20)).to exist
end

Given(/^I am on a page that does not exist$/) do
  name = 'NewPage' + Time.now.to_i.to_s
  visit(ArticlePage, using_params: { article_name: name })
end

Given(/^I am on the "(.+)" page$/) do |article|
  # Ensure we do not cause a redirect
  article = article.gsub(/ /, '_')
  visit(ArticlePage, using_params: { article_name: article })
end

Given(/^I am using the mobile site$/) do
  visit(MainPage) do |page|
    page_uri = URI.parse(page.page_url_value)

    domain = page_uri.host == 'localhost' ? nil : page_uri.host
    browser.cookies.add 'mf_useformat', 'true', domain: domain

    page.refresh
  end
end

Given(/^I am viewing the site in mobile mode$/) do
  browser.window.resize_to(320, 480)
end

Given(/^I am viewing the site in tablet mode$/) do
  # Use numbers significantly larger than tablet threshold to account for browser chrome
  browser.window.resize_to(1280, 1024)
end

Given(/^my browser doesn't support JavaScript$/) do
  browser_factory.override(browser_user_agent: 'Opera/9.80 (J2ME/MIDP; Opera Mini/9.80 (S60; SymbOS; Opera Mobi/23.348; U; en) Presto/2.5.25 Version/10.54')
end

Given(/^the "(.*?)" page is protected\.$/) do |page|
  api.protect_page(page, 'MinervaNeue Selenium test protected this page')
end

When(/^I click the browser back button$/) do
  on(ArticlePage).back
end

When(/^I say OK in the confirm dialog$/) do
  on(ArticlePage).confirm(true) do
  end
end

When(/^I visit the page "(.*?)" with hash "(.*?)"$/) do |article, hash|
  # Ensure we do not cause a redirect
  article = article.gsub(/ /, '_')
  visit(ArticlePage, using_params: { article_name: article, hash: hash })
end

Then(/^there should be a red link with text "(.+)"$/) do |text|
  # FIXME: Switch to link_element when red links move to stable
  expect(on(ArticlePage).content_element.link_element(text: text).when_present(10)).to be_visible
end
