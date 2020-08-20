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
 * Mustache helper to load strings from string_manager.
 *
 * @package    core
 * @category   output
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\output;

use Mustache_LambdaHelper;
use stdClass;

/**
 * This class will load language strings in a template.
 *
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      2.9
 */
class mustache_string_helper {

    /**
     * Read a lang string from a template and get it from get_string.
     *
     * Some examples for calling this from a template are:
     *
     * {{#str}}activity{{/str}}
     * {{#str}}actionchoice, core, {{#str}}delete{{/str}}{{/str}} (Nested)
     * {{#str}}addinganewto, core, {"what":"This", "to":"That"}{{/str}} (Complex $a)
     *
     * The args are comma separated and only the first is required.
     * The last is a $a argument for get string. For complex data here, use JSON.
     * Note: Nested variables will only be rendered if passed in as the value of a JSON variable & wrapped by quote helper tags. Eg:
     * {{#str}}namedate, core, {"name":{{# quote }}{{fullname}}{{/ quote }}, "date": {{# quote }}{{date}}{{/ quote }}}{{/str}}
     *
     * @param string $text The text to parse for arguments.
     * @param Mustache_LambdaHelper $helper Used to render nested mustache variables.
     * @return string
     */
    public function str($text, Mustache_LambdaHelper $helper) {
        // Split the text into an array of variables.
        $key = strtok($text, ",");
        $key = trim($key);
        $component = strtok(",");
        $component = trim($component);
        if (!$component) {
            $component = '';
        }

        $a = trim(strtok(''));

        // If there are JSON encoded variables, render any content wrapped in quote helpers, then JSON decode the variables.
        // Note: This allows the quoted values to be rendered, while preventing other values being rendered (eg from user input).
        if ((strpos($a, '{') === 0) && (strpos($a, '{{') !== 0)) {
            $quotevalueregex = '/"[a-zA-Z0-9]+":\s*{{#\s*quote\s*}}.*?{{\/\s*quote\s*}}/';

            // Find all values wrapped in quote helpers.
            preg_match_all($quotevalueregex, $a, $quotedvalues);

            // Render each of the quoted values, then substitute them back into the JSON string.
            foreach ($quotedvalues[0] as $quotetorender) {
                $quoteposition = strpos($a, $quotetorender);
                $renderedquote = $helper->render($quotetorender);
                $a = substr_replace($a, $renderedquote, $quoteposition, strlen($quotetorender));
            }

            // Decode the JSON string.
            $a = json_decode($a);
        }

        return get_string($key, $component, $a);
    }
}
