@mod @mod_peerfeedback
Feature: Teachers give students feedback on the feedback they wrote
  In order to teach students how to give good feedback
  As a teacher
  I need to comment on their feedback and have them see my comments in one list

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | t1       | Teacher   | One      |
      | s1       | Ayse      | Kaya     |
      | s2       | Can       | Yilmaz   |
      | s3       | Deniz     | Aydin    |
      | s4       | Ece       | Nogroup  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user | course | role           |
      | t1   | C1     | editingteacher |
      | s1   | C1     | student        |
      | s2   | C1     | student        |
      | s3   | C1     | student        |
      | s4   | C1     | student        |
    And the following "activities" exist:
      | activity     | name          | course | idnumber | grading | visibility |
      | peerfeedback | Team feedback | C1     | pf1      | 0       | 0          |
    And the following "mod_peerfeedback > questions" exist:
      | peerfeedback | qtype    | questiontext | config | required |
      | pf1          | longtext | Improve      | {}     | 1        |
    And the following "mod_peerfeedback > groups" exist:
      | peerfeedback | name   | members |
      | pf1          | Team A | s1, s2  |
      | pf1          | Team B | s3      |
    And the following "mod_peerfeedback > responses" exist:
      | peerfeedback | giver | receiver | answers                             |
      | pf1          | s1    | s2       | {"Improve": "Share notes earlier."} |

  Scenario: The student sees the teacher's comments and overall comment in one list
    Given I am on the "Team feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Review"
    And I follow "Ayse Kaya"
    When I set the field "Teacher comment" to "Say which notes and when."
    And I press "Mark as reviewed"
    And I set the field "Overall comment for Ayse Kaya" to "Kind tone, now be more specific."
    And I press "Save and show to student"
    Then I should see "Shown to the student"
    And I log out
    When I am on the "Team feedback" "peerfeedback activity" page logged in as "s1"
    Then I should see "Teacher feedback"
    And I should see "Kind tone, now be more specific." in the "#pf-teacherfeedback" "css_element"
    And I should see "Your feedback about Can Yilmaz" in the "#pf-teacherfeedback" "css_element"
    And I should see "Say which notes and when." in the "#pf-teacherfeedback" "css_element"
    And I log out
    When I am on the "Team feedback" "peerfeedback activity" page logged in as "s2"
    Then I should not see "Teacher feedback"
    And I should not see "Say which notes and when."

  Scenario: An overall comment that is only saved stays hidden from the student
    Given I am on the "Team feedback" "peerfeedback activity" page logged in as "t1"
    And I follow "Review"
    And I follow "Ayse Kaya"
    When I set the field "Overall comment for Ayse Kaya" to "Draft thoughts."
    And I press "Save and show to student"
    And I press "Hide from student"
    Then I should see "Not shown to the student"
    And I log out
    When I am on the "Team feedback" "peerfeedback activity" page logged in as "s1"
    Then I should not see "Draft thoughts."

  Scenario: The review list shows only grouped students, group by group, with a group filter
    Given I am on the "Team feedback" "peerfeedback activity" page logged in as "t1"
    When I follow "Review"
    Then I should see "Team A" in the ".pf-review-list" "css_element"
    And I should see "Team B" in the ".pf-review-list" "css_element"
    And I should see "Deniz Aydin" in the ".pf-review-list" "css_element"
    And I should not see "Ece Nogroup" in the ".pf-review-list" "css_element"
    When I set the field "Group" to "Team B"
    And I press "Go"
    Then I should see "Deniz Aydin" in the ".pf-review-list" "css_element"
    And I should not see "Ayse Kaya" in the ".pf-review-list" "css_element"
    And I should not see "Team A" in the ".pf-review-list" "css_element"
