@custom-browser @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org
Feature: Basic site for legacy devices

  Background:
    Given my browser doesn't support JavaScript
      And I am using the mobile site
      And I am on the "Main Page" page

  # FIXME: Add scenario to check search actually works
  Scenario: Able to search in basic non-JavaScript site
    When I click on "Random" in the main navigation menu
    Then I should see the search button
      # FIXME: Check that the edit button is invisible

  @smoke
  Scenario: Able to access left navigation in basic non-JavaScript site
    When I click on "Random" in the main navigation menu
      And I click on the main navigation button
    Then I should see a link to "Home" in the main navigation menu
      And I should see a link to "Random" in the main navigation menu
      And I should see a link to "Settings" in the main navigation menu
      And I should not see a link to "Watchlist" in the main navigation menu
      And I should see a link to "Log in" in the main navigation menu

  @extension-geodata
  Scenario: Nearby link not present in main navigation menu
    When I click on "Random" in the main navigation menu
      And I click on the main navigation button
    Then I should not see a link to "Nearby" in the main navigation menu

  @smoke @integration @skip
  Scenario: Search with JavaScript disabled
    Given the page "Selenium search test" exists
    When I type into search box "Test is used by Selenium web driver"
      And I click the search button
    Then I should see a list of search results
