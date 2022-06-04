@chrome @en.m.wikipedia.beta.wmflabs.org @firefox @integration @test2.m.wikipedia.org @vagrant
Feature: Search

  Scenario: Clicking search input in tablet mode
    Given I am using the mobile site
      And the page "Selenium search test" exists
      And I am on the "Main Page" page
      And I am viewing the site in tablet mode
    When I click the search input field
    Then I see the search overlay
