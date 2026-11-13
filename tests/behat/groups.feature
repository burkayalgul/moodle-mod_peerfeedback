@mod @mod_peerfeedback
Feature: Teachers put every student in a group of their own
  In order to run a self-evaluation only activity
  As a teacher
  I need one click that gives each student a group of their own

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | t1       | Teacher   | One      | t1@example.com   |
      | s1       | Ayse      | Kaya     | ayse@example.com |
      | s2       | Can       | Yilmaz   | cy@example.com   |
      | s3       | Deniz     | Aydin    | da@example.com   |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user | course | role           |
      | t1   | C1     | editingteacher |
      | s1   | C1     | student        |
      | s2   | C1     | student        |
      | s3   | C1     | student        |
    And the following "activities" exist:
      | activity     | name          | course | idnumber | selfeval |
      | peerfeedback | Self feedback | C1     | pf1      | 1        |
    And the following "mod_peerfeedback > groups" exist:
      | peerfeedback | name   | members    |
      | pf1          | Team A | s1, s2, s3 |

  Scenario: Everyone alone replaces the groups with one group per student
    Given I am on the "Self feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Groups"
    When I press "Everyone alone"
    Then I should see "one group per student"
    And I press "Continue"
    And I should not see "Team A"
    And "//section[contains(@class, 'pf-groupbox')]//input[@value='Ayse Kaya']" "xpath_element" should exist
    And "//section[contains(@class, 'pf-groupbox')]//input[@value='Can Yilmaz']" "xpath_element" should exist
    And "//section[contains(@class, 'pf-groupbox')]//input[@value='Deniz Aydin']" "xpath_element" should exist
    And "section.pf-groupbox:nth-of-type(4)" "css_element" should not exist

  @javascript
  Scenario: Pair and Alone act only on the ticked students
    Given I am on the "Self feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Groups"
    And "Pair everyone" "button" should exist
    When I set the field "Ayse Kaya" to "1"
    Then "Pair selected" "button" should exist
    And "Selected alone" "button" should exist
    And I press "Selected alone"
    And "//section[contains(@class, 'pf-groupbox')]//input[@value='Ayse Kaya']" "xpath_element" should exist
    And "//section[contains(@class, 'pf-groupbox')]//input[@value='Team A']" "xpath_element" should exist
    And "//section[contains(@class, 'pf-groupbox')]//input[@value='Can Yilmaz']" "xpath_element" should not exist

  @javascript
  Scenario: Ticks survive several searches and the list is alphabetical
    Given I am on the "Self feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Groups"
    Then "Ayse Kaya" "table_row" should appear before "Can Yilmaz" "table_row"
    And "Can Yilmaz" "table_row" should appear before "Deniz Aydin" "table_row"
    And I should see "cy@example.com"
    When I set the field "Search by name or email" to "ayse"
    Then "Ayse Kaya" "table_row" should be visible
    And "Can Yilmaz" "table_row" should not be visible
    And I set the field "Ayse Kaya" to "1"
    And I set the field "Search by name or email" to "da@"
    And "Ayse Kaya" "table_row" should not be visible
    And I set the field "Deniz Aydin" to "1"
    And I should see "2 selected"
    And I set the field "Search by name or email" to "nobody"
    And I should see "No students match your search."
    And I set the field "Search by name or email" to ""
    And I set the field "Assign selected to" to "A new group"
    And I press "Assign"
    And "//section[contains(@class, 'pf-groupbox')][.//*[contains(., 'Ayse Kaya')] and .//*[contains(., 'Deniz Aydin')]]" "xpath_element" should exist
    And "Can Yilmaz" "table_row" should exist
