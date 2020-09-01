<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for core_course\coursecontentexport\manager.
 *
 * @package core_course\coursecontentexport
 * @category  test
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

declare(strict_types=1);

namespace core_course\coursecontentexport;

use advanced_testcase;


/**
 * Unit tests for core_course\coursecontentexport\manager.
 *
 * @coversDefaultClass \core_course\coursecontentexport\manager
 * @package core_course\coursecontentexport
 * @category  test
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager_test extends advanced_testcase {

    /**
     * Test test manager::export_all_content_for_course() method.
     *
     */
    public function test_export_all_content_for_course(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $zipwriter = \core_course\coursecontentexport\zipwriter::get_file_writer('test.zip');

        manager::export_all_content_for_course($context, $zipwriter);
        $this->assertTrue(file_exists($zipwriter->get_file_path()));
    }
}
