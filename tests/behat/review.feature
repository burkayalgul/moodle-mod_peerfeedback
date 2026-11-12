@mod @mod_peerfeedback
Feature: Teachers review and grade the feedback students give
  In order to assess how well students give feedback
  As a teacher
  I need to grade each feedback and lock it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | t1       | Teacher   | One      |
      | s1       | Student   | One      |
      | s2       | Student   | Two      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user | course | role           |
      | t1   | C1     | editingteacher |
      | s1   | C1     | student        |
      | s2   | C1     | student        |
    And the following "activities" exist:
      | activity     | name          | course | idnumber | grading | visibility |
      | peerfeedback | Team feedback | C1     | pf1      | 1       | 1          |
    And the following "mod_peerfeedback > questions" exist:
      | peerfeedback | qtype    | questiontext | config | required |
      | pf1          | longtext | Improve      | {}     | 1        |
    And the following "mod_peerfeedback > groups" exist:
      | peerfeedback | name   | members |
      | pf1          | Team A | s1, s2  |
    And the following "mod_peerfeedback > responses" exist:
      | peerfeedback | giver | receiver | answers                             |
      | pf1          | s1    | s2       | {"Improve": "Share notes earlier."} |

  Scenario: Teacher grades a feedback and the student sees it locked with the grade
    Given I am on the "Team feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Review"
    When I follow "Student One"
    Then I should see "Student One → Student Two"
    And I should see "Share notes earlier."
    And I should see "Not reviewed" in the ".pf-card" "css_element"
    When I set the field "Grade" to "85"
    And I set the field "Teacher comment" to "Specific and kind."
    And I press "Mark as reviewed"
    Then I should see "Reviewed" in the ".pf-card" "css_element"
    And I should see "85" in the ".pf-average" "css_element"
    And I log out
    When I am on the "Team feedback" "peerfeedback activity" page logged in as "s1"
    Then I should see "Your teacher has reviewed this feedback"
    And I should see "85 / 100"
    And I should see "Specific and kind."

  Scenario: The review filter stays on after saving a review
    Given I am on the "Team feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Review"
    And I follow "Student One"
    And I set the field "Show" to "Not reviewed"
    And I press "Go"
    When I set the field "Teacher comment" to "Thanks."
    And I press "Save"
    Then the field "Show" matches value "Not reviewed"
