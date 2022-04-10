@block @block_completion_progress @javascript
Feature: Using Completion Progress block overview
  In order to see full class progress
  As a teacher
  I can view the overview page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
      | student3 | Student | 3 | student3@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | teacher2 | Teacher | 2 | teacher2@example.com |
    And the following config values are set as admin:
      | enablecompletion | 1 |
      | enableavailability | 1 |
      | enablenotes | 1 |
      | messaging | 1 |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
      | student2 | G2    |
      | teacher2 | G1    |
      | teacher2 | G2    |
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
    And I log out

  Scenario: Editing teacher sees all members by default
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I click on "Overview of students" "button" in the "Completion Progress" "block"
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"

  Scenario: Non-editing teacher sees their group members by default
    Given I log in as "teacher2"
    And I am on "Course 1" course homepage
    When I click on "Overview of students" "button" in the "Completion Progress" "block"
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should not see "Student 3"

  Scenario: Select all selects all
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Overview of students" "button" in the "Completion Progress" "block"
    When I click on "Select all" "checkbox"
    Then the following fields match these values:
      | Select 'Student 1' | Yes |
      | Select 'Student 2' | Yes |
      | Select 'Student 3' | Yes |

  Scenario: Messaging works
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Overview of students" "button" in the "Completion Progress" "block"
    When I click on "Select 'Student 1'" "checkbox"
    And I click on "Select 'Student 2'" "checkbox"
    And I select "Send a message" from the "With selected users..." singleselect
    And I set the field "Message" to "Message"
    And I click on "Send message to 2 people" "button"
    Then I should see "Message sent to 2 people"

  Scenario: Notes work
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Overview of students" "button" in the "Completion Progress" "block"
    When I click on "Select 'Student 1'" "checkbox"
    And I click on "Select 'Student 2'" "checkbox"
    And I select "Add a new note" from the "With selected users..." singleselect
    And I set the field "Note" to "Note"
    And I click on "Add a new note to 2 people" "button"
    Then I should see "Note added to 2 people"
