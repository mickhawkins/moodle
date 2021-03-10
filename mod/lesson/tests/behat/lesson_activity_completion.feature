@mod @mod_lesson
Feature: View activity completion
  In order to have visibility of lesson completion requirements
  As a student
  I need to be able to view my lesson completion progress

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Vinnie    | Student1 | student1@example.com |
      | teacher1 | Darrell   | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user | course | role           |
      | student1 | C1 | student        |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Enable completion tracking | Yes |
      | Show completion conditions | Yes |
    And I press "Save and display"
    And I turn editing mode on
    And I add a "Lesson" to section "1" and I fill the form with:
      | Name                                       | Music history                                     |
      | Completion tracking                        | Show activity as complete when conditions are met |
      | Require view                               | 1                                                 |
      | Require grade                              | 1                                                 |
      | Require end reached                        | 1                                                 |
    And I follow "Music history"
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    # This is required because the inputs on grouped form fields are not visibly labelled.
    And I set the field "Student must do this activity at least for" to "1"
    And I set the field with xpath "//input[@name='completiontimespent[number]']" to "1"
    And I set the field with xpath "//select[@name='completiontimespent[timeunit]']" to "seconds"
    And I press "Save and display"
    And I follow "Add a content page"
    And I set the following fields to these values:
    | Page title  | Music history part 1        |
    | Description | The history of music part 1 |
    And I click on "Save page" "button"
    And I log out

  Scenario: View automatic completion items as a teacher
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Music history"
    Then I should see "Receive a grade" in the "[data-region=completionrequirements]" "css_element"
    And I should see "View" in the "[data-region=completionrequirements]" "css_element"
    And I should see "Reach the end of the lesson page" in the "[data-region=completionrequirements]" "css_element"
    And I should see "Spend at least 1 sec in the lesson" in the "[data-region=completionrequirements]" "css_element"

  Scenario: View automatic completion items as a student
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    And I should see "To do: Receive a grade" in the "[data-region=completionrequirements]" "css_element"
    And I should see "To do: Spend at least 1 sec in the lesson" in the "[data-region=completionrequirements]" "css_element"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "View > Grader report" in the course gradebook
    And I turn editing mode on
    And I give the grade "90.00" to the user "Vinnie Student1" for the grade item "Music history"
    And I press "Save changes"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    And I wait "2" seconds
    And I reload the page
    Then I should see "Done: View" in the "[data-region=completionrequirements]" "css_element"
    # TODO: Update this to have a to do version for end of lesson page when it's possible for end of page to be marked done.
    And I should see "Done: Reach the end of the lesson page" in the "[data-region=completionrequirements]" "css_element"
    And I should see "Done: Receive a grade" in the "[data-region=completionrequirements]" "css_element"
    And I should see "Done: Spend at least 1 sec in the lesson" in the "[data-region=completionrequirements]" "css_element"

  @javascript
  Scenario: Use manual completion
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And I set the field "Completion tracking" to "Students can manually mark the activity as completed"
    And I press "Save and display"
    # Teacher view
    And "Mark as done" "button" should exist
    And the "Mark as done" "button" should be disabled
    And I log out
    # Student view.
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    Then "Mark as done" "button" should exist
    And I click on "Mark as done" "button"
    And "Done" "button" should exist
