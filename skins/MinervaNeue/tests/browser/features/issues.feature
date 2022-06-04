@integration @chrome @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant
Feature: Issues

  Background:
    Given I am using the mobile site
      And I am on a page which has cleanup templates
      And this page has issues

  Scenario: Clicking page issues opens overlay
    When I click the page issues stamp
    Then I should see the issues overlay

  Scenario: Closing page issues
    When I click the page issues stamp
      And I see the issues overlay
      And I click the overlay issue close button
    Then I should not see the issues overlay

  Scenario: Closing page issues (browser back)
    When I click the page issues stamp
      And I see the issues overlay
      And I click the browser back button
    Then I should not see the issues overlay
