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
    And the following "activities" exist:
      | activity | course | idnumber | name                    | timeclose  | enablecompletion |
      | quiz     | C1     | Q1A      | Quiz 1A No deadline     | 0          | 1                |
      | quiz     | C1     | Q1B      | Quiz 1B Past deadline   | 1337       | 1                |
      | quiz     | C1     | Q1C      | Quiz 1C Future deadline | 9000000000 | 1                |
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
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I follow "Quiz 1A No deadline"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Completion tracking | Show activity as complete when conditions are met |
      | Require view | 1 |
    And I press "Save and return to course"
    And I add the "Completion Progress" block
    And I log out

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
