@mod @mod_forum @forumreport @forumreport_summary @javascript
Feature: Forum summary report downloadable
  In order to view all rows of the report together and perform further analysis
  As a teacher
  I can download the forum summary report

  Scenario: Teacher can download the summary report
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C2     | editingteacher |
    And the following "activities" exist:
      | activity | name   | description     | course | idnumber |
      | forum    | forum1 | C1 first forum  | C1     | forum1   |
      | forum    | forum2 | C1 second forum | C1     | forum2   |
      | forum    | forum1 | C2 first forum  | C2     | forum1   |
    And the following forum discussions exist in course "Course 1":
      | user     | forum  | name        | message | created           |
      | teacher1 | forum1 | discussion1 | t1 msg  | ## 1 month ago ## |
      | student1 | forum1 | discussion4 | s1 msg  | ## 1 week ago ##  |
    And the following forum replies exist in course "Course 1":
      | user     | forum  | discussion  | message    | created           |
      | teacher1 | forum1 | discussion1 | t1 reply 1 | ## 2 weeks ago ## |
      | teacher1 | forum1 | discussion4 | t1 reply 2 | ## 2 days ago ##  |
      | student1 | forum1 | discussion1 | s1 reply 1 | ## 6 days ago ##  |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    And I navigate to "Summary report" in current page administration
    Then I should see "Download table data as"
    And "Download" "select" should exist
    And "Download" "button" should exist
    # There is no way to check the downloaded content, so we just ensure downloading is possible without throwing an exception.
    And I click on "Download" "button"
    And I log out
