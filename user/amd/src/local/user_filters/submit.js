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
 * Module responsible for submitting any participants filters that have been applied.
 *
 * @module     core_user/local/user_filter/submit
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';

const submitFilters = (filterSetDiv) => {
    //const filterSetJson = {};

    // Prepare data from each filter in the filter set to submit.
    filterSetDiv.querySelectorAll('[data-filter-uniqid]').forEach((filterRow) => {
        const uniqid = filterRow.getAttribute('data-filter-uniqid');
        const matchType = document.getElementById(Selectors.filters.submit.matchType(uniqid)).getAttribute('data-option-selected');
        window.console.log(Selectors.filters.submit.matchType(uniqid));
        window.console.log(matchType);
    });
};

export const init = uniqid => {
    const filterSetDiv = document.getElementById(`${uniqid}-user-filters`);
    document.getElementById(`${uniqid}-submit`).addEventListener('click', function(){ submitFilters(filterSetDiv); });
};
