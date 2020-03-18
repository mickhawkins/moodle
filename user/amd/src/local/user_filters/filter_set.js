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

import Notification from 'core/notification';
import {resetFilterRow} from './reset_row';
import Selectors from './selectors';
import Templates from 'core/templates';

// Submit filter values in the filter set.
const submitFilters = (filterSetDiv) => {
    const filterSet = [];

    // Prepare data from each filter in the filter set to submit.
    filterSetDiv.querySelectorAll('[data-filter-uniqid]').forEach((filterRow) => {
        let uniqid = filterRow.getAttribute('data-filter-uniqid');
        let joinType = document.getElementById(Selectors.filters.row.joinType(uniqid)).getAttribute('data-option-selected');
        let filterType = document.getElementById(Selectors.filters.row.filterType(uniqid)).getAttribute('data-option-selected');
        let filterValuesDiv = filterRow.querySelector(Selectors.filters.row.enhancedValuesClass);

        // Include any filters that have values set.
        if (filterValuesDiv !== null) {
            let filterValues = filterValuesDiv.querySelectorAll('span[role="listitem"]');

            if (filterValues.length > 0) {
                let filterObj = {
                    "jointype": joinType,
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

// Clear all values in the filter set, leaving a single default filter.
const clearFilters = (filterSetDiv) => {
    const firstFilter = filterSetDiv.querySelector('[data-filter-uniqid]');
    const uniqid = firstFilter.getAttribute('data-filter-uniqid');

    // Clear data from first filter to return it to default.
    resetFilterRow(uniqid);

    // Remove all other filter rows.
    // TODO when more than one filter row can exist.
};

// Add a new condition (filter row) to the filter set.
const addCondition = (filterSetUniqid) => {
    const userFiltersDiv = document.getElementById(Selectors.filterset.userFilters(filterSetUniqid));
    const baseFilterData = document.getElementById(Selectors.filterset.baseData(filterSetUniqid));
    const joinTypesDefault = JSON.parse(baseFilterData.getAttribute('data-join-types-default'));
    const filterTypesDefault = JSON.parse(baseFilterData.getAttribute('data-filter-types-default'));

    const context = {
        "jointypes": JSON.parse(baseFilterData.getAttribute('data-join-types')),
        "jointypesdefaultvalue": joinTypesDefault.value,
        "jointypesdefaultlabel": joinTypesDefault.label,
        "filtertypes": JSON.parse(baseFilterData.getAttribute('data-filter-types')),
        "filtertypesdefaultvalue": filterTypesDefault.value,
        "filtertypesdefaultlabel": filterTypesDefault.label
    };

    Templates.render('core_user/user_filter_row', context)
        .then((html, js) => {
            Templates.appendNodeContents(userFiltersDiv, html, js);
        })
        .fail(Notification.exception);
};

// Initialise handlers in the filter set.
export const init = uniqid => {
    const filterSetDiv = document.getElementById(`${uniqid}-user-filterset`);

    document.getElementById(Selectors.filterset.submit(uniqid)).addEventListener('click', () => {
        submitFilters(filterSetDiv);
    });

    document.getElementById(Selectors.filterset.clearAll(uniqid)).addEventListener('click', () => {
        clearFilters(filterSetDiv);
    });

    document.getElementById(Selectors.filterset.addCondition(uniqid)).addEventListener('click', () => {
        addCondition(uniqid);
    });
};
