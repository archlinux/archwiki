# on a shared environment there is no need to waste unnecessary API requests
# to edit a page which only needs to exist. This function limits write operations
# and speeds up browser tests.
def create_page_with_content(title, wikitext)
  resp = api.get_wikitext title
  if resp.status == 404
    api.create_page title, wikitext
  elsif wikitext != resp.body
    api.create_page title, wikitext
  end
end

# export MEDIAWIKI_API_URL = http://en.wikipedia.beta.wmflabs.org/w/api.php
Given(/^I go to a page that has references$/) do
  wikitext = "MinervaNeue is a MediaWiki skin.
{{#tag:ref|This is a note.<ref>This is a nested ref.</ref>|group=note}}
==Notes==
<references group=note />
==References==
<references/>
"

  create_page_with_content 'Selenium References test page', wikitext
  step 'I am on the "Selenium References test page" page'
end

Given(/^I go to a page that has sections$/) do
  wikitext = "==Section 1==
Hello world
== Section 2 ==
Section 2.
=== Section 2A ===
Section 2A.
== Section 3 ==
Section 3.
"

  create_page_with_content 'Selenium section test page2', wikitext
  step 'I am on the "Selenium section test page2" page'
end

Given(/^I am on a page which has cleanup templates$/) do
  wikitext = <<-END.gsub(/^ */, '')
      This page is used by Selenium to test MediaWiki functionality.

      <table class="metadata plainlinks ambox ambox-content ambox-Refimprove" role="presentation">
        <tr>
          <td class="mbox-image">[[File:Question_book-new.svg|thumb]]</td>
          <td class="mbox-text">
            <span class="mbox-text-span">This article \'\'\'needs additional citations for [[Wikipedia:Verifiability|verification]]\'\'\'. <span class="hide-when-compact">Please help [[Selenium page issues test page#editor/0|improve this article]] by [[Help:Introduction_to_referencing/1|adding citations to reliable sources]]. Unsourced material may be challenged and removed.</span> <small><i>(October 2012)</i></small></span>
          </td>
        </tr>
      </table>
    END

  create_page_with_content 'Selenium page issues test page', wikitext
  step 'I am on the "Selenium page issues test page" page'
end

Given(/^the page "(.*?)" exists$/) do |title|
  create_page_with_content title, 'Test is used by Selenium web driver'
  step 'I am on the "' + title + '" page'
end

Given(/^at least one article with geodata exists$/) do
  create_page_with_content 'Selenium geo test page', <<-end
This page is used by Selenium to test geo related features.

{{#coordinates:43|-75|primary}}
  end
end

Given(/^I am in a wiki that has categories$/) do
  msg = 'This page is used by Selenium to test category related features.'
  wikitext = msg + '

[[Category:Test category]]
[[Category:Selenium artifacts]]
[[Category:Selenium hidden category]]'

  create_page_with_content 'Category:Selenium artifacts', msg
  create_page_with_content 'Category:Test category', msg
  create_page_with_content 'Category:Selenium hidden category', '__HIDDENCAT__' + msg
  create_page_with_content 'Selenium categories test page', wikitext
end

Given(/^I go to a page that has languages$/) do
  wikitext = 'This page is used by Selenium to test language related features.

[[es:Selenium language test page]]'

  create_page_with_content 'Selenium language test page', wikitext
  step 'I am on the "Selenium language test page" page'
end

Given(/^I go to a page that does not have languages$/) do
  wikitext = 'This page is used by Selenium to test language related features.'

  create_page_with_content 'Selenium language test page without languages', wikitext
  step 'I am on the "Selenium language test page without languages" page'
end

Given(/^the wiki has a terms of use$/) do
  create_page_with_content 'MediaWiki:mobile-frontend-terms-url', 'https://mobile.test/wiki/Terms_of_Use'
  create_page_with_content 'MediaWiki:mobile-frontend-terms-text', 'Terms of use'
  # force a visit to check its existence
  visit(ArticlePage, using_params: { article_name: 'MediaWiki:Mobile-frontend-terms-url?action=info' })
end

Given(/^I visit a protected page$/) do
  create_page_with_content 'Selenium protected test 2', 'Test is used by Selenium web driver'
  step 'the "Selenium protected test 2" page is protected.'
  step 'I am on the "Selenium protected test 2" page'
end
