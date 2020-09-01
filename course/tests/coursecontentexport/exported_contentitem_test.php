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
 * Unit tests for core_course\coursecontentexport\exported_contentitem.
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
 * Unit tests for core_course\coursecontentexport\exported_contentitem.
 *
 * @coversDefaultClass \core_course\coursecontentexport\exported_contentitem
 * @package core_course\coursecontentexport
 * @category  test
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exported_contentitem_test extends advanced_testcase {

    /**
     * Test exported_contentitem constructor.
     *
     */
    public function test_exported_contentitem(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $context = \context_course::instance($course->id);
        $exportedci = new exported_contentitem($context, 'mod_folder', 'my files', 'file', 1, 'folder module files');

        $this->assertInstanceOf(exported_contentitem::class, $exportedci);
        $this->assertEquals('mod_folder', $exportedci->get_component());
        $this->assertEquals($context, $exportedci->get_context());
        $this->assertEquals(1, $exportedci->get_id());
        $this->assertEquals('my files', $exportedci->get_title());
        $this->assertEquals('file', $exportedci->get_type());
        $this->assertEquals('folder module files', $exportedci->get_path_in_zip());
    }

    /**
     * Test exported_contentitem::create() function.
     */
    public function test_create() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $contentitem = new contentitem($context,'mod_folder', 'course files', 'file', 1);

        $exportedci = exported_contentitem::create($contentitem, 'folder module files');
        $this->assertInstanceOf(exported_contentitem::class, $exportedci);
        $this->assertEquals('mod_folder', $exportedci->get_component());
        $this->assertEquals($context, $exportedci->get_context());
        $this->assertEquals(1, $exportedci->get_id());
        $this->assertEquals('course files', $exportedci->get_title());
        $this->assertEquals('file', $exportedci->get_type());
        $this->assertEquals('folder module files', $exportedci->get_path_in_zip());
    }
}
