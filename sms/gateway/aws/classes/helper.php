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

/**
 * AWS SMS gateway helpers.
 *
 * @package    smsgateway_aws
 * @copyright  2024 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * This function internationalises a number to E.164 standard.
     * https://46elks.com/kb/e164
     *
     * @param string $phonenumber the phone number to format.
     * @param ?string $countrycode The country code of the phone number.
     * @return string the formatted phone number.
     */
    public static function format_number(
        string $phonenumber,
        ?string $countrycode = null,
    ): string {
        // Remove all whitespace, dashes, and brackets in one step.
        $phonenumber = preg_replace('/[ ()-]/', '', $phonenumber);

        // Check if the number is already in international format or if it starts with a 0.
        if (!str_starts_with($phonenumber, '+') && str_starts_with($phonenumber, '0')) {
            // Strip leading 0 and prepend country code if not already in international format.
            $phonenumber = !empty($countrycode) ? '+' . $countrycode .
                substr($phonenumber, 1) : substr($phonenumber, 1);
        }

        return $phonenumber;
    }

}
