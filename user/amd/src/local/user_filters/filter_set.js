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

import Autocomplete from 'core/form-autocomplete';
import Selectors from './selectors';
//import {resetFilterRow} from './local/user_filters/reset_row';

// Submit filter values in the filter set.
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

// Clear all values in the filter set, leaving a single default filter.
const clearFilters = (filterSetDiv) => {
    const firstFilter = filterSetDiv.querySelector('[data-filter-uniqid]');
    const uniqid = firstFilter.getAttribute('data-filter-uniqid');
    const matchTypeElement = document.getElementById(Selectors.filters.row.matchType(uniqid));
    const filterTypeElement = document.getElementById(Selectors.filters.row.filterType(uniqid));
    const enhancedDropdown = firstFilter.querySelector('div [data-autocomplete-uniqueid]');
    const enhancedUniqueId = enhancedDropdown.getAttribute('data-autocomplete-uniqueid');

    // Clear data from first filter to return it to default.
    //resetFilterRow(uniqid); TODO - this should replace the lines below when it's working

    matchTypeElement.setAttribute('data-option-selected', matchTypeElement.getAttribute('data-default-selected'));
    matchTypeElement.innerText = matchTypeElement.getAttribute('data-default-label');
    filterTypeElement.setAttribute('data-option-selected', filterTypeElement.getAttribute('data-default-selected'));
    filterTypeElement.innerText = filterTypeElement.getAttribute('data-default-label');
    Autocomplete.remove(Selectors.filters.dropdown.select(uniqid), enhancedUniqueId);
    document.getElementById(`${uniqid}-filter-row-clear`).classList.add('disabled');

    // Remove all other filter rows.
    //TODO


//TODO: Some of this will be relevant for the row specific deletes, so might need to be moved out into functions.
//^^^^^^^^^ Perhaps this just loops, and if it's the first element, call one method (clearRow) else call another (remove Row).

//TODO: Set the first (currently only) filter back to defaults,
//THEN any subsequent ones should have their filter row divs completely removed, so we end up with a single filter.

//data-option-selected="{{matchtypesdefaultvalue}}"
  ///              data-default-selected="{{matchtypesdefaultvalue}}" data-default-label="{{matchtypesdefaultlabel}}">


};

// Initialise handlers in the filter set.
export const init = uniqid => {
    const filterSetDiv = document.getElementById(`${uniqid}-user-filters`);

    document.getElementById(`${uniqid}-submit`).addEventListener('click', function(){
        submitFilters(filterSetDiv);
    });

    document.getElementById(`${uniqid}-clearall`).addEventListener('click', function(){
        clearFilters(filterSetDiv);
    });
};
