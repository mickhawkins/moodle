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
 * @module     core_user/local/user_filter/filter_set
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';

const submitFilters = (filterSetDiv) => {
    const filterSet = [];

    // Prepare data from each filter in the filter set to submit.
    filterSetDiv.querySelectorAll('[data-filter-uniqid]').forEach((filterRow) => {
        let uniqid = filterRow.getAttribute('data-filter-uniqid');
        let matchType = document.getElementById(Selectors.filters.row.matchType(uniqid)).getAttribute('data-option-selected');
        let filterType = document.getElementById(Selectors.filters.row.filterType(uniqid)).getAttribute('data-option-selected');
        let filterValuesDiv = filterRow.querySelector(Selectors.filters.row.enhancedValuesClass);

        // Include any filters that have values set.
        if (filterValuesDiv !== null) {
            let filterValues = filterValuesDiv.querySelectorAll('span[role="listitem"]');

            if (filterValues.length > 0) {
                let filterObj = {
                    "matchtype": matchType,
                    "filtertype": filterType,
                    "filtervalues": []
                };

                filterValues.forEach((filterValueItem) => {
                    filterObj.filtervalues.push(filterValueItem.getAttribute('data-value'));
                });

                filterSet.push(filterObj);
            }
        }
    });

    // TEMP: Output the applied filters until there's somewhere to AJAX the request to.
    window.console.log(filterSet);
};

export const init = uniqid => {
    const filterSetDiv = document.getElementById(`${uniqid}-user-filters`);
    document.getElementById(`${uniqid}-submit`).addEventListener('click', function(){
        submitFilters(filterSetDiv);
    });
};
