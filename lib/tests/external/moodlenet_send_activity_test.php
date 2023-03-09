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

namespace core\external;

global $CFG;

use core\oauth2\api;
use externallib_advanced_testcase;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External functions test for moodlenet_send_activity.
 *
 * @package    core
 * @category   test
 * @copyright  2023 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core\external\moodlenet_send_activity
 */
class moodlenet_send_activity_test extends externallib_advanced_testcase {

    /**
     * Test the behaviour of moodlenet_send_activity().
     *
     * @covers ::execute
     */
    public function test_moodlenet_send_activity() {
        if (!defined('TEST_MOODLENET_MOCK_SERVER')) {
            $this->markTestSkipped('TEST_MOODLENET_MOCK_SERVER is not defined');
        }

        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Generate data.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $moduleinstance = $generator->create_module('assign', ['course' => $course->id]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $issuer = api::create_standard_issuer('moodlenet', TEST_MOODLENET_MOCK_SERVER);
        $issuer->set('enabled', 0);

        // Test with the experimental flag off.
        $result = moodlenet_send_activity::execute($issuer->get('id'), $course->id, $moduleinstance->cmid, 0);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('errorissuernotenabled', $result['warnings'][0]['warningcode']);

        $CFG->enablesharingtomoodlenet = true;

        // Test with invalid format.
        $result = moodlenet_send_activity::execute($issuer->get('id'), $course->id, $moduleinstance->cmid, 5);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('errorinvalidformat', $result['warnings'][0]['warningcode']);

        // Test with the user does not have permission.
        $this->setUser($user);
        $result = moodlenet_send_activity::execute($issuer->get('id'), $course->id, $moduleinstance->cmid, 0);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('errorpermission', $result['warnings'][0]['warningcode']);

        $this->setAdminUser();

        // Test with the issuer is not enabled.
        $result = moodlenet_send_activity::execute($issuer->get('id'), $course->id, $moduleinstance->cmid, 0);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('errorissuernotenabled', $result['warnings'][0]['warningcode']);

        // Test with the issuer is enabled but not set in the MN Outbound setting.
        $issuer->set('enabled', 1);
        $result = moodlenet_send_activity::execute($issuer->get('id'), $course->id, $moduleinstance->cmid, 0);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('errorissuernotenabled', $result['warnings'][0]['warningcode']);

        set_config('oauthservice', $issuer->get('id'), 'moodlenet');
        // Test cannot communicate with the MN server.
        $result = moodlenet_send_activity::execute($issuer->get('id'), $course->id, $moduleinstance->cmid, 0);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('errorsendingactivity', $result['warnings'][0]['warningcode']);
        $this->assertEquals(404, $result['warnings'][0]['item']);

        // TODO: Test the response when we can mock the authorization token..
    }
}
