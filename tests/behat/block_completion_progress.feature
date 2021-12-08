@block @block_completion_progress @javascript
Feature: Using block completion progress for a quiz
  In order to know what quizzes are due
  As a student
  I can visit my dashboard

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following config values are set as admin:
      | enablecompletion | 1 |
      | enableavailability | 1 |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    # 2 = Show activity as complete when conditions are met.
    And the following "activities" exist:
      | activity | course | idnumber | name                    | timeclose  | enablecompletion | completionview | completion |
      | quiz     | C1     | Q1A      | Quiz 1A No deadline     | 0          | 1                | 1              | 2 |
      | quiz     | C1     | Q1B      | Quiz 1B Past deadline   | 1337       | 1                | 0              | 0 |
      | quiz     | C1     | Q1C      | Quiz 1C Future deadline | 9000000000 | 1                | 0              | 0 |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | qtype     | name           | questiontext              | questioncategory |
      | truefalse | First question | Answer the first question | Test questions   |
    And quiz "Quiz 1A No deadline" contains the following questions:
      | question       | page |
      | First question | 1    |
    And quiz "Quiz 1B Past deadline" contains the following questions:
      | question       | page |
      | First question | 1    |
    And quiz "Quiz 1C Future deadline" contains the following questions:
      | question       | page |
      | First question | 1    |
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Completion Progress" block
    And I configure the "Completion Progress" block
    And I set the following fields to these values:
      | Show percentage to students | Yes |
    And I press "Save changes"
    And I log out

  Scenario: Basic functioning of the block
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I hover ".block_completion_progress .progressBarCell:first-child" "css_element"
    Then I should see "Progress: 0%" in the "Completion Progress" "block"
    And I should see "Quiz 1A No deadline" in the "Completion Progress" "block"
    And I should see "Not completed" in the "Completion Progress" "block"

  Scenario: Submit the quizzes
    Given I am on the "Quiz 1A No deadline" "mod_quiz > View" page logged in as "student1"
    And I click on "Attempt quiz" "link_or_button"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I am on "Course 1" course homepage
    When I hover ".block_completion_progress .progressBarCell:first-child" "css_element"
    Then I should see "Progress: 100%" in the "Completion Progress" "block"
    And I should see "Quiz 1A No deadline" in the "Completion Progress" "block"
    And I should see "Completed" in the "Completion Progress" "block"
