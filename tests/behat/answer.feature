@mod @mod_peerfeedback
Feature: Students give peer feedback one person at a time
  In order to evaluate my team mates
  As a student
  I need to answer the questions for each group member

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | s1       | Student   | One      |
      | s2       | Student   | Two      |
      | s3       | Student   | Three    |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user | course | role    |
      | s1   | C1     | student |
      | s2   | C1     | student |
      | s3   | C1     | student |
    And the following "activities" exist:
      | activity     | name          | course | idnumber |
      | peerfeedback | Team feedback | C1     | pf1      |
    And the following "mod_peerfeedback > questions" exist:
      | peerfeedback | qtype    | questiontext  | config    | required |
      | pf1          | rating   | Communication | {"max":5} | 1        |
      | pf1          | longtext | Improve       | {}        | 1        |
    And the following "mod_peerfeedback > groups" exist:
      | peerfeedback | name   | members    |
      | pf1          | Team A | s1, s2, s3 |

  @javascript
  Scenario: Student answers each peer one at a time
    When I am on the "Team feedback" "peerfeedback activity" page logged in as "s1"
    Then I should see "Student Three" in the ".pf-tabs" "css_element"
    And I should see "Student Two" in the ".pf-tabs" "css_element"
    And I should see "1 of 2" in the ".pf-person" "css_element"
    When I press "Save and next"
    Then I should see "This question is required."
    When I click on "4" "text" in the ".pf-rating" "css_element"
    And I set the field "Improve" to "More notes"
    And I press "Save and next"
    Then ".pf-tab.pf-saved" "css_element" should exist
    And I should see "2 of 2" in the ".pf-person" "css_element"
    When I click on "5" "text" in the ".pf-rating" "css_element"
    And I set the field "Improve" to "Great job"
    And I press "Submit"
    Then I should see "Feedback submitted"
    And ".pf-tab.pf-done" "css_element" should exist

  Scenario: Student without a group is told so
    Given the following "users" exist:
      | username | firstname | lastname |
      | s4       | Student   | Four     |
    And the following "course enrolments" exist:
      | user | course | role    |
      | s4   | C1     | student |
    When I am on the "Team feedback" "peerfeedback activity" page logged in as "s4"
    Then I should see "You have not been assigned to a group yet."
