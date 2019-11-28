@mod @mod_forum @forumreport @forumreport_summary
Feature: Post date column data and filters available
  In order to view forum summary data, including earliest and most recent post dates, for a specific time period
  As a teacher
  I need the ability to filter the forum summary report by date and view earliest/most recent post data

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | G1       |
      | Group B | C1     | G2       |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | G1    |
      | teacher1 | G2    |
      | student1 | G1    |
      | student2 | G2    |
    And the following "activities" exist:
      | activity | name   | description     | course | idnumber | groupmode |
      | forum    | forum1 | C1 first forum  | C1     | forum1   | 1         |
      | forum    | forum2 | C1 second forum | C1     | forum2   | 1         |
    And the following forum discussions exist in course "Course 1":
      | user     | forum  | name        | message         | group | created                 |
      | teacher1 | forum1 | discussion1 | t1 earliest     | G1    | ##2018-01-02 09:00:00## |
      | teacher1 | forum1 | discussion2 | t1 between      | G2    | ##2018-03-27 10:00:00## |
      | teacher1 | forum2 | discussion3 | t1 other forum  | G2    | ##2018-01-01 11:00:00## |
      | student1 | forum1 | discussion4 | s1 between      | G1    | ##2019-03-27 13:00:00## |
      | student2 | forum2 | discussion5 | s2 other forum  | G2    | ##2018-03-27 09:00:00## |
    And the following forum replies exist in course "Course 1":
      | user     | forum  | discussion  | message         | created                 |
      | teacher1 | forum1 | discussion1 | t1 between      | ##2018-01-02 10:30:00## |
      | teacher1 | forum1 | discussion2 | t1 another      | ##2019-01-01 09:00:00## |
      | teacher1 | forum1 | discussion2 | t1 latest       | ##2019-09-01 07:00:00## |
      | teacher1 | forum2 | discussion3 | t1 other forum  | ##2019-09-12 08:00:00## |
      | student1 | forum1 | discussion1 | s1 earliest     | ##2019-02-14 01:30:00## |
      | student1 | forum1 | discussion1 | s1 another      | ##2019-03-27 04:00:00## |
      | student2 | forum2 | discussion3 | s2 other forum  | ##2018-03-27 10:00:00## |

  Scenario: Add posts and view accurate dates in summary report
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    When I navigate to "Summary report" in current page administration
    Then the following should exist in the "forumreport_summary_table" table:
    # |Name       | Earliest post                       | Most recent post                  |
      | -2-       | -9-                                 | -10-                              |
      | Student 1 | Thursday, 14 February 2019, 1:30 AM | Wednesday, 27 March 2019, 1:00 PM |
      | Student 2 | -                                   | -                                 |
      | Teacher 1 | Tuesday, 2 January 2018, 9:00 AM    | Sunday, 1 September 2019, 7:00 AM |

  @javascript
  Scenario: Report can be filtered by combinations of From and To dates
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    When I navigate to "Summary report" in current page administration
    Then "Dates" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                       | Most recent post                    |
      | -2-       | -3-      | -4-         | -9-                                 | -10-                                |
      | Student 1 | 1        | 2           | Thursday, 14 February 2019, 1:30 AM | Wednesday, 27 March 2019, 1:00 PM   |
      | Student 2 | 0        | 0           | -                                   | -                                   |
      | Teacher 1 | 2        | 3           | Tuesday, 2 January 2018, 9:00 AM    | Sunday, 1 September 2019, 7:00 AM   |
    And I click on "Dates" "button"
    And the "filterdatefrompopover[day]" "select" should be disabled
    And the "filterdatefrompopover[month]" "select" should be disabled
    And the "filterdatefrompopover[year]" "select" should be disabled
    And the field "filterdatefrompopover[enabled]" does not match value "1"
    And the "filterdatetopopover[day]" "select" should be disabled
    And the "filterdatetopopover[month]" "select" should be disabled
    And the "filterdatetopopover[year]" "select" should be disabled
    And the field "filterdatetopopover[enabled]" does not match value "1"
    # Set "From" filter only
    And I click on "filterdatefrompopover[enabled]" "checkbox"
    And the field "filterdatefrompopover[enabled]" matches value "1"
    And I set the following fields to these values:
      | filterdatefrompopover[day]     | 1     |
      | filterdatefrompopover[month]   | March |
      | filterdatefrompopover[year]    | 2019  |
    And I click on "Save" "button" in the "filter-dates-popover" "region"
    And "From 1 Mar 2019" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                      | Most recent post                  |
      | -2-       | -3-      | -4-         | -9-                                | -10-                              |
      | Student 1 | 1        | 1           | Wednesday, 27 March 2019, 4:00 AM  | Wednesday, 27 March 2019, 1:00 PM |
      | Student 2 | 0        | 0           | -                                  | -                                 |
      | Teacher 1 | 0        | 1           | Sunday, 1 September 2019, 7:00 AM  | Sunday, 1 September 2019, 7:00 AM |
    And I click on "From 1 Mar 2019" "button"
    And the following fields match these values:
    | filterdatefrompopover[day]     | 1     |
    | filterdatefrompopover[month]   | March |
    | filterdatefrompopover[year]    | 2019  |
    | filterdatefrompopover[enabled] | 1     |
    And the "filterdatetopopover[day]" "select" should be disabled
    And the "filterdatetopopover[month]" "select" should be disabled
    And the "filterdatetopopover[year]" "select" should be disabled
    And the field "filterdatetopopover[enabled]" does not match value "1"
    # Set both "From" and "to" filters
    And I click on "filterdatetopopover[enabled]" "checkbox"
    And the field "filterdatetopopover[enabled]" matches value "1"
    And I set the following fields to these values:
      | filterdatefrompopover[day]     | 20       |
      | filterdatefrompopover[month]   | January  |
      | filterdatefrompopover[year]    | 2018     |
      | filterdatetopopover[day]       | 20       |
      | filterdatetopopover[month]     | February |
      | filterdatetopopover[year]      | 2019     |
    And I click on "Save" "button" in the "filter-dates-popover" "region"
    And "20 Jan 2018 - 20 Feb 2019" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                       | Most recent post                    |
      | -2-       | -3-      | -4-         | -9-                                 | -10-                                |
      | Student 1 | 0        | 1           | Thursday, 14 February 2019, 1:30 AM | Thursday, 14 February 2019, 1:30 AM |
      | Student 2 | 0        | 0           | -                                   | -                                   |
      | Teacher 1 | 1        | 1           | Tuesday, 27 March 2018, 10:00 AM    | Tuesday, 1 January 2019, 9:00 AM    |
    And I click on "20 Jan 2018 - 20 Feb 2019" "button"
    And the following fields match these values:
      | filterdatefrompopover[day]     | 20       |
      | filterdatefrompopover[month]   | January  |
      | filterdatefrompopover[year]    | 2018     |
      | filterdatefrompopover[enabled] | 1        |
      | filterdatetopopover[day]       | 20       |
      | filterdatetopopover[month]     | February |
      | filterdatetopopover[year]      | 2019     |
      | filterdatetopopover[enabled]   | 1        |
    # Set "To" filter only
    And I click on "filterdatefrompopover[enabled]" "checkbox"
    And the field "filterdatefrompopover[enabled]" does not match value "1"
    And I click on "Save" "button" in the "filter-dates-popover" "region"
    And "To 20 Feb 2019" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                       | Most recent post                    |
      | -2-       | -3-      | -4-         | -9-                                 | -10-                                |
      | Student 1 | 0        | 1           | Thursday, 14 February 2019, 1:30 AM | Thursday, 14 February 2019, 1:30 AM |
      | Student 2 | 0        | 0           | -                                   | -                                   |
      | Teacher 1 | 2        | 2           | Tuesday, 2 January 2018, 9:00 AM    | Tuesday, 1 January 2019, 9:00 AM    |
    And I click on "To 20 Feb 2019" "button"
    And the "filterdatefrompopover[day]" "select" should be disabled
    And the "filterdatefrompopover[month]" "select" should be disabled
    And the "filterdatefrompopover[year]" "select" should be disabled
    And the field "filterdatefrompopover[enabled]" does not match value "1"
    And the following fields match these values:
      | filterdatetopopover[day]       | 20       |
      | filterdatetopopover[month]     | February |
      | filterdatetopopover[year]      | 2019     |
      | filterdatetopopover[enabled]   | 1        |

  @javascript
  Scenario: Report can be filtered by groups and dates concurrently
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    When I navigate to "Summary report" in current page administration
    Then "Dates" "button" should exist
    And "Groups" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                       | Most recent post                    |
      | -2-       | -3-      | -4-         | -9-                                 | -10-                                |
      | Student 1 | 1        | 2           | Thursday, 14 February 2019, 1:30 AM | Wednesday, 27 March 2019, 1:00 PM   |
      | Student 2 | 0        | 0           | -                                   | -                                   |
      | Teacher 1 | 2        | 3           | Tuesday, 2 January 2018, 9:00 AM    | Sunday, 1 September 2019, 7:00 AM   |
    And I click on "Dates" "button"
    And I click on "filterdatefrompopover[enabled]" "checkbox"
    And the field "filterdatefrompopover[enabled]" matches value "1"
    And I click on "filterdatetopopover[enabled]" "checkbox"
    And the field "filterdatetopopover[enabled]" matches value "1"
    And I set the following fields to these values:
      | filterdatefrompopover[day]     | 20       |
      | filterdatefrompopover[month]   | January  |
      | filterdatefrompopover[year]    | 2018     |
      | filterdatetopopover[day]       | 20       |
      | filterdatetopopover[month]     | February |
      | filterdatetopopover[year]      | 2019     |
    And I click on "Save" "button" in the "filter-dates-popover" "region"
    And "20 Jan 2018 - 20 Feb 2019" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                       | Most recent post                    |
      | -2-       | -3-      | -4-         | -9-                                 | -10-                                |
      | Student 1 | 0        | 1           | Thursday, 14 February 2019, 1:30 AM | Thursday, 14 February 2019, 1:30 AM |
      | Student 2 | 0        | 0           | -                                   | -                                   |
      | Teacher 1 | 1        | 1           | Tuesday, 27 March 2018, 10:00 AM    | Tuesday, 1 January 2019, 9:00 AM    |
    And I click on "Groups" "button"
    And I click on "Group A" "checkbox" in the "filter-groups-popover" "region"
    And I click on "Save" "button" in the "filter-groups-popover" "region"
    And "20 Jan 2018 - 20 Feb 2019" "button" should exist
    And "Groups (1)" "button" should exist
    And the following should exist in the "forumreport_summary_table" table:
    # |Name       | Num disc | Num replies | Earliest post                       | Most recent post                    |
      | -2-       | -3-      | -4-         | -9-                                 | -10-                                |
      | Student 1 | 0        | 1           | Thursday, 14 February 2019, 1:30 AM | Thursday, 14 February 2019, 1:30 AM |
      | Teacher 1 | 0        | 0           | -                                   | -                                   |
    And I should not see "Student 2"
