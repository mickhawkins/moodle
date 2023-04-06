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

namespace core\moodlenet;

use context_course;
use core\http_client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use stdClass;
use testing_data_generator;

/**
 * Unit tests for {@see activity_sender}.
 *
 * @coversDefaultClass activity_sender
 * @package core
 * @copyright 2023 Huong Nguyen <huongnv13@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodlenet_activity_sender_test extends \advanced_testcase {

    /** @var testing_data_generator Data generator. */
    private testing_data_generator $generator;
    /** @var stdClass Course object. */
    private stdClass $course;
    /** @var stdClass Activity object, */
    private stdClass $moduleinstance;
    /** @var context_course Course context instance. */
    private context_course $coursecontext;

    /**
     * Set up function for tests.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator();
        $this->course = $this->generator->create_course();
        $this->moduleinstance = $this->generator->create_module('assign', ['course' => $this->course->id]);
        $this->coursecontext = context_course::instance($this->course->id);
    }

    /**
     * Test share_activity() method.
     *
     * @dataProvider share_activity_provider
     * @covers ::share_activity
     * @covers ::log_event
     * @return void
     */
    public function test_share_activity(ResponseInterface $httpresponse, array $expected) {
        global $CFG, $USER;
        $this->setAdminUser();

        // Enable the experimental flag.
        $CFG->enablesharingtomoodlenet = true;

        // Create dummy issuer.
        $issuer = new \core\oauth2\issuer(0);
        $issuer->set('enabled', 1);
        $issuer->set('servicetype', 'moodlenet');
        $issuer->set('baseurl', 'https://moodlenet.example.com');

        // Set OAuth 2 service in the outbound setting to the dummy issuer.
        set_config('oauthservice', $issuer->get('id'), 'moodlenet');

        // Generate access token for the mock.
        $accesstoken = new stdClass();
        $accesstoken->token = random_string(64);

        // Create mock builder for OAuth2 client.
        $mockbuilder = $this->getMockBuilder('core\oauth2\client');
        $mockbuilder->onlyMethods(['get_issuer', 'is_logged_in', 'get_accesstoken']);
        $mockbuilder->setConstructorArgs([$issuer, "", ""]);

        // Get the OAuth2 client mock and set the return value for necessary methods.
        $mockOauthClient = $mockbuilder->getMock();
        $mockOauthClient->method('get_issuer')->will($this->returnValue($issuer));
        $mockOauthClient->method('is_logged_in')->will($this->returnValue(true));
        $mockOauthClient->method('get_accesstoken')->will($this->returnValue($accesstoken));

        // Create Guzzle mock.
        $mockGuzzleHandler = new MockHandler([$httpresponse]);
        $handlerstack = HandlerStack::create($mockGuzzleHandler);
        $httpclient = new http_client(['handler' => $handlerstack]);

        // Create events sink.
        $sink = $this->redirectEvents();

        // Call the API.
        $result = activity_sender::share_activity($this->course->id, $this->moduleinstance->cmid, $USER->id, $httpclient,
            $mockOauthClient,
            activity_sender::SHARE_FORMAT_BACKUP);

        // Verify the result.
        $this->assertEquals($expected['response_code'], $result['responsecode']);
        $this->assertEquals($expected['resource_url'], $result['drafturl']);

        // Verify the events.
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\core\event\moodlenet_resource_exported', $event);
        $this->assertEquals($USER->id, $event->userid);

        if ($result['responsecode'] == 201) {
            $description = "The user with id '{$USER->id}' successfully shared activities to MoodleNet with the " .
                "following course module ids, from context with id '{$this->coursecontext->id}': '{$this->moduleinstance->cmid}'.";
        } else {
            $description = "The user with id '{$USER->id}' failed to share activities to MoodleNet with the " .
                "following course module ids, from context with id '{$this->coursecontext->id}': '{$this->moduleinstance->cmid}'.";
        }
        $this->assertEquals($description, $event->get_description());
    }

    /**
     * Provider for test share_activity().
     *
     * @return array Test data.
     */
    public function share_activity_provider(): array {
        return [
            'Success' => [
                'http_response' => new Response(
                    201,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'homepage' => 'https://moodlenet.example.com/drafts/view/activity_backup_1.mbz',
                    ]),
                ),
                'expected' => [
                    'response_code' => 201,
                    'resource_url' => 'https://moodlenet.example.com/drafts/view/activity_backup_1.mbz',
                ],
            ],
            'Fail with 200 status code' => [
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'homepage' => 'https://moodlenet.example.com/drafts/view/activity_backup_2.mbz',
                    ]),
                ),
                'expected' => [
                    'response_code' => 200,
                    'resource_url' => '#',
                ],
            ],
            'Fail with 401 status code' => [
                'http_response' => new Response(
                    401,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'homepage' => 'https://moodlenet.example.com/drafts/view/activity_backup_3.mbz',
                    ]),
                ),
                'expected' => [
                    'response_code' => 401,
                    'resource_url' => '#',
                ],
            ],
            'Fail with 404 status code' => [
                'http_response' => new Response(
                    404,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'homepage' => '',
                    ]),
                ),
                'expected' => [
                    'response_code' => 401,
                    'resource_url' => '#',
                ],
            ],
        ];
    }

    /**
     * Test is_valid_instance method.
     *
     * @covers ::is_valid_instance
     * @return void
     */
    public function test_is_valid_instance() {
        global $CFG;
        $this->setAdminUser();

        // Create dummy issuer.
        $issuer = new \core\oauth2\issuer(0);
        $issuer->set('enabled', 0);
        $issuer->set('servicetype', 'google');

        // Can not share if the experimental flag it set to false.
        $CFG->enablesharingtomoodlenet = false;
        $this->assertFalse(activity_sender::is_valid_instance($issuer));

        // Enable the experimental flag.
        $CFG->enablesharingtomoodlenet = true;

        // Can not share if the OAuth 2 service in the outbound setting is not matched the given one.
        set_config('oauthservice', random_int(1, 30), 'moodlenet');
        $this->assertFalse(activity_sender::is_valid_instance($issuer));

        // Can not share if the OAuth 2 service in the outbound setting is not enabled.
        set_config('oauthservice', $issuer->get('id'), 'moodlenet');
        $this->assertFalse(activity_sender::is_valid_instance($issuer));

        // Can not share if the OAuth 2 service type is not moodlenet.
        $issuer->set('enabled', 1);
        $this->assertFalse(activity_sender::is_valid_instance($issuer));

        // All good now.
        $issuer->set('servicetype', 'moodlenet');
        $this->assertTrue(activity_sender::is_valid_instance($issuer));
    }

    /**
     * Test can_user_share method.
     *
     * @covers ::can_user_share
     * @return void
     */
    public function test_can_user_share() {
        global $DB;

        // Generate data.
        $student1 = $this->generator->create_user();
        $teacher1 = $this->generator->create_user();
        $teacher2 = $this->generator->create_user();
        $manager1 = $this->generator->create_user();

        // Enrol users.
        $this->generator->enrol_user($student1->id, $this->course->id, 'student');
        $this->generator->enrol_user($teacher1->id, $this->course->id, 'teacher');
        $this->generator->enrol_user($teacher2->id, $this->course->id, 'editingteacher');
        $this->generator->enrol_user($manager1->id, $this->course->id, 'manager');

        // Get roles.
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], 'id', MUST_EXIST);
        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', MUST_EXIST);

        // Test with default settings.
        // Student and Teacher cannot share the activity.
        $this->assertFalse(activity_sender::can_user_share($this->coursecontext, $student1->id));
        $this->assertFalse(activity_sender::can_user_share($this->coursecontext, $teacher1->id));
        // Editing-teacher and Manager can share the activity.
        $this->assertTrue(activity_sender::can_user_share($this->coursecontext, $teacher2->id));
        $this->assertTrue(activity_sender::can_user_share($this->coursecontext, $manager1->id));

        // Teacher who has the capabilities can share the activity.
        assign_capability('moodle/moodlenet:shareactivity', CAP_ALLOW, $teacherrole->id, $this->coursecontext);
        assign_capability('moodle/backup:backupactivity', CAP_ALLOW, $teacherrole->id, $this->coursecontext);
        $this->assertTrue(activity_sender::can_user_share($this->coursecontext, $teacher1->id));

        // Editing-teacher who does not have the capabilities can not share the activity.
        assign_capability('moodle/moodlenet:shareactivity', CAP_PROHIBIT, $editingteacherrole->id, $this->coursecontext);
        $this->assertFalse(activity_sender::can_user_share($this->coursecontext, $teacher2->id));
    }

    /**
     * Test prepare_share_contents method.
     *
     * @covers ::prepare_share_contents
     * @return void
     */
    public function test_prepare_share_contents() {
        $this->setAdminUser();

        // Get activity resource.
        $resourceinfo = new activity_resource($this->course->id, $this->moduleinstance->cmid);

        // Set get_file method accessibility.
        $method = new ReflectionMethod(activity_sender::class, 'prepare_share_contents');
        $method->setAccessible(true);

        // Test with invalid share format.
        $package = $method->invoke(new activity_sender(), $resourceinfo, random_int(1, 30));
        $this->assertEmpty($package);

        // Test with valid share format.
        $package = $method->invoke(new activity_sender(), $resourceinfo, activity_sender::SHARE_FORMAT_BACKUP);
        $this->assertNotEmpty($package);
        // Confirm there are backup file contents returned.
        $this->assertTrue(array_key_exists('filecontents', $package));
        $this->assertNotEmpty($package['filecontents']);

        // Confirm the expected stored_file object is returned.
        $this->assertTrue(array_key_exists('storedfile', $package));
        $this->assertInstanceOf(\stored_file::class, $package['storedfile']);
    }
}
