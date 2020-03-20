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
 * Module containing the selectors for user filters.
 *
 * @module     core_user/local/user_filter/selectors
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    filters: {
        types: {
            text: 'input[type="text"]',
        },
        dropdown: {
            base: filterid => `${filterid}-filter-options-dropdown`,
            enhanced: uniqid => `${uniqid}-options-dropdown`,
            enhancedSelector: uniqid => `#${uniqid}-options-dropdown`,
        },
        row: {
            id: uniqid => `${uniqid}-filter-row`,
            clear: uniqid => `${uniqid}-filter-row-clear`,
            joinType: uniqid => `${uniqid}-join-type`,
            filterType: uniqid => `${uniqid}-filter-type`,
            enhancedValuesClass: '.form-autocomplete-selection',
            getModuleSelector: uniqid => `[role="menuitem"][data-modname="${uniqid}"]`,
        },
    },
    filterset: {
        addCondition: uniqid => `${uniqid}-addcondition`,
        baseData: uniqid => `${uniqid}-filterset-base-data`,
        clearAll: uniqid => `${uniqid}-clearall`,
        submit: uniqid => `${uniqid}-submit`,
        uniqidSelector: 'div[data-filterset-uniqid]',
        uniqidAttr: 'data-filterset-uniqid',
        userFilters: uniqid => `${uniqid}-user-filters`,
    }
};
