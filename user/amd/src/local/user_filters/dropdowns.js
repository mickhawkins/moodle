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
 * Module containing the handlers that control user filter dropdown functionality.
 *
 * @module     core_user/local/user_filter/dropdowns
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Autocomplete from 'core/form-autocomplete';
import Selectors from './selectors';

// Set values for single dropdowns.
const setDropdownValue = (e, uniqid) => {
    e.preventDefault();

    const dropdown = e.target;

    // Only handle specific items being selected.
    if (dropdown.classList.contains('dropdown-item')) {
        const dropdownType = dropdown.parentNode.parentNode.querySelector('button').id;

        // Display the selection and set the hidden value.
        document.getElementById(dropdownType).innerText = dropdown.innerText;
        document.getElementById(`${dropdownType}-selected`).value = dropdown.getAttribute('data-value');

        // Filter type dropdowns need to display the filter options.
        if (dropdown.hasAttribute("data-filter-options-id")) {
            const optionsid = dropdown.getAttribute("data-filter-options-id");

            document.getElementById(optionsid).classList.add('d-inline-block');
            document.getElementById(optionsid).classList.remove('hidden');

            //TODO This should only happy if it's a select
            Autocomplete.enhance(`#${uniqid}-filter-options-listTODO`, true, null, 'String goes here',
                   false, true, null, true);
        }
    }
};

// Toggle the visibility of an option.
const toggleVisibility = option => {
    option.classList.toggle('hidden');
};

// Display a selected item and remove it from the available options.
const setOptionValue = (e, uniqid) => {
    const selectedOption = e.target;
    const value = selectedOption.getAttribute('data-value');
    let valueTag = document.createElement('span');
    let crossIcon = document.createElement('i');
    //TODO: Figure out if we need to store the values somewhere hidden as well, for easy sending in the web service

    // Set up and display new tag for chosen value.
    valueTag.setAttribute('data-value', value);
    valueTag.classList.add('d-inline-block', 'bg-dark', 'text-white', 'rounded', 'font-weight-bold', 'px-1');
    valueTag.innerText = selectedOption.innerText;
    crossIcon.classList.add('icon', 'fa', 'fa-times', 'pl-2', 'mr-0');

    // Handle cross removing tag and re-adding option to filter dropdown.
    crossIcon.addEventListener('click', function() {
        toggleVisibility(selectedOption);
        valueTag.parentNode.removeChild(valueTag);
    });

    valueTag.appendChild(crossIcon);
    document.getElementById(`${uniqid}-filter-options-set`).appendChild(valueTag);

    toggleVisibility(selectedOption);
};

// Display the dropdown for filter options.
const toggleFilterDropdown = (e, uniqid) => {
    const dropdownList = e.target.parentNode.querySelector('ul');

    //TODO: This is the start of selecting the filter type, where it shows a dropdown when selected,
            //there's also the case of the value being selected on the next dropdown, which adds the
            //little removable tag bits. Need to see if they can be differentiated (maybe a data- tag) that
        //allows generic code here to set one of two different event listeners, depending which type it is.
    dropdownList.addEventListener('click', function(e) {setOptionValue(e, uniqid);});

    dropdownList.classList.remove('hidden');
};

// Initialise the dropdowns in a participants filter row.
export const init = uniqid => {
    // Set listener on each filter dropdown menu.
    document.querySelectorAll('.dropdown-menu.user-filter-select').forEach((dropdown) => {
        dropdown.addEventListener('click', function(e) {setDropdownValue(e, uniqid);});
    });

    // Add listeners for 'type or select' filter options type.
    const filterdiv = document.getElementById(`${uniqid}-filter-options`);

    filterdiv.querySelector(Selectors.filters.types.text).addEventListener('focus', function(e) {toggleFilterDropdown(e, uniqid);});
    filterdiv.querySelector('span').addEventListener('click', toggleFilterDropdown);
};
