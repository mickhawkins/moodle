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

namespace smsgateway_aws;

use core_sms\message;
use MoodleQuickForm;

/**
 * AWS SMS gateway.
 *
 * @package    smsgateway_aws
 * @copyright  2024 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_sms\gateway {

    public function send(
        message $message,
        bool $async = false,
    ): message {
        global $DB;
        // Get the config from the message record.
        $awsconfig = $DB->get_record(
            table: 'sms_gateways',
            conditions: ['id' => $message->gatewayid, 'enabled' => 1, 'gateway' => 'smsgateway_aws\gateway',],
            fields: 'config',
        );
        $status = \core_sms\message_status::GATEWAY_NOT_AVAILABLE;
        if ($config = $awsconfig->config) {
            $config = (object)json_decode($config, true, 512, JSON_THROW_ON_ERROR);
            $class = '\smsgateway_aws\local\service\\' . $config->gateway;
            $recipientnumber = helper::format_number(
                phonenumber: $message->recipientnumber,
                countrycode: isset($config->countrycode) ?? null,
            );
            $status = call_user_func(
                $class . '::send_sms_message',
                $message->content,
                $recipientnumber,
                $config,
            );
        }

        return $message->with(
            status: $status,
        );
    }

    public function get_send_priority(message $message): int {
        return 50;
    }

    /**
     * Set form definition.
     *
     * @param MoodleQuickForm $mform Moodle form element.
     * @return void
     * @todo MDL-81732 Implement the api in gateway and use this/add to the api.
     */
    public static function set_form_definition(
        MoodleQuickForm $mform,
    ): void {
        $codeslink = 'https://en.wikipedia.org/wiki/List_of_country_calling_codes';
        $link = \html_writer::link($codeslink, $codeslink);
        $mform->addElement(
            'text',
            'countrycode',
            get_string('countrycode', 'smsgateway_aws'),
            'maxlength="255" size="20"',
        );
        $mform->addHelpButton(
            elementname: 'countrycode',
            identifier: 'countrycode',
            component: 'smsgateway_aws',
            a: $link,
        );
        $mform->setDefault(
            elementName: 'countrycode',
            defaultValue: 0,
        );

        $gateways = [
            'aws_sns' => get_string('aws_sns', 'smsgateway_aws'),
        ];
        $mform->addElement(
            'select',
            'gateway',
            get_string('gateway', 'smsgateway_aws'),
            $gateways,
        );
        $mform->addHelpButton(
            elementname: 'gateway',
            identifier: 'gateway',
            component: 'smsgateway_aws',
        );
        $mform->setDefault(
            elementName: 'gateway',
            defaultValue: 'aws_sns',
        );

        $mform->addElement(
            'checkbox',
            'usecredchain',
            get_string('usecredchain', 'smsgateway_aws'),
        );
        $mform->setDefault(
            elementName: 'usecredchain',
            defaultValue: 0,
        );

        $mform->addElement(
            'text',
            'api_key',
            get_string('api_key', 'smsgateway_aws'),
            'maxlength="255" size="20"',
        );
        $mform->addHelpButton(
            elementname: 'api_key',
            identifier: 'api_key',
            component: 'smsgateway_aws',
        );
        $mform->setDefault(
            elementName: 'api_key',
            defaultValue: '',
        );

        $mform->addElement(
            'text',
            'api_secret',
            get_string('api_secret', 'smsgateway_aws'),
            'maxlength="255" size="20"',
        );
        $mform->addHelpButton(
            elementname: 'api_secret',
            identifier: 'api_secret',
            component: 'smsgateway_aws',
        );
        $mform->setDefault(
            elementName: 'api_secret',
            defaultValue: '',
        );

        // TODO MDL-81732 move admin_settings_aws_region to form element.
        $mform->addElement(
            'text',
            'api_region',
            get_string('api_region', 'smsgateway_aws'),
            'maxlength="255" size="20"',
        );
        $mform->addHelpButton(
            elementname: 'api_region',
            identifier: 'api_region',
            component: 'smsgateway_aws',
        );
        $mform->setDefault(
            elementName: 'api_region',
            defaultValue: 'ap-southeast-2',
        );
    }
}
