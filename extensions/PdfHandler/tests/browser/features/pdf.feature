@chrome @firefox @internet_explorer_8 @internet_explorer_9 @internet_explorer_10 @phantomjs @test.wikipedia.org
Feature: PDF

  Scenario: Check for Download as PDF link
    Given I am at a random page
    Then Download as PDF should be present

  Scenario: Click on Download as PDF link
    Given I am at a random page
    When I click on Download as PDF
    Then Download the file link should be present
