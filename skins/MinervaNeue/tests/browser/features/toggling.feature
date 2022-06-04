@en.m.wikipedia.beta.wmflabs.org @test2.m.wikipedia.org @vagrant
Feature: Toggling sections

  Background:
    Given I am using the mobile site
      And I am viewing the site in mobile mode

  Scenario: Respect the hash on sections
    When I visit the page "Selenium section test page" with hash "#Section_2A"
    Then the heading element with id "Section_2A" should be visible

  @smoke @integration
  Scenario: Opening a section on mobile
    Given I go to a page that has sections
    When I click on the first collapsible section heading
    Then I should see the content of the first section

  Scenario: Closing a section on mobile
    Given I go to a page that has sections
      And I click on the first collapsible section heading
    When I click on the first collapsible section heading
    Then I should not see the content of the first section
