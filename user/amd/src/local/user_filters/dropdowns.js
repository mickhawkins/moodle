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
import {get_string as getString} from 'core/str';
import Selectors from './selectors';

// Set values for single dropdowns.
const setDropdownValue = (e, uniqid) => {
    e.preventDefault();

    const optionSelected = e.target;

    // Only handle specific items being selected.
    if (optionSelected.classList.contains('dropdown-item')) {
        const dropdownButton = optionSelected.parentNode.parentNode.querySelector('button');
        const previouslySet = dropdownButton.getAttribute('data-option-selected');
        const selectedValue = optionSelected.getAttribute('data-value');

        // Display the selection and set the hidden value.
        dropdownButton.innerText = optionSelected.innerText;
        dropdownButton.setAttribute('data-option-selected', selectedValue);

        // Enable the 'clear' button for the filter row, if the dropdown changes from default.
        if (dropdownButton.getAttribute('data-option-selected') !== dropdownButton.getAttribute('data-default-selected')) {
            document.getElementById(Selectors.filters.row.clear(uniqid)).classList.remove('disabled');
        }

        // Filter type enhanced dropdowns need to display the filter options.
        if (optionSelected.hasAttribute("data-filter-type") && !previouslySet &&
                optionSelected.getAttribute("data-filter-type") === 'enhanceddropdown') {

                insertEnhanced(uniqid, selectedValue);
        }
    }
};

// Insert enhanced dropdown for selected filter type.
const insertEnhanced = async(uniqid, filterType) => {
    const selectString = await getString('typeorselect', 'core');
    const filterset = document.querySelector(Selectors.filterset.uniqidSelector);
    const filtersetUniqid = filterset.getAttribute(Selectors.filterset.uniqidAttr);
    const baseFilterDropdown = document.getElementById(Selectors.filters.dropdown.base(`${filtersetUniqid}-${filterType}`));
    const filterRow = document.getElementById(Selectors.filters.row.id(uniqid));
    const clearRow = document.getElementById(Selectors.filters.row.clear(uniqid));
    const rowFilterDropdown = baseFilterDropdown.cloneNode(true);
    rowFilterDropdown.id = Selectors.filters.dropdown.enhanced(uniqid);

    filterRow.insertBefore(rowFilterDropdown, clearRow);
    Autocomplete.enhance(Selectors.filters.dropdown.enhancedSelector(uniqid), false, null,
            selectString, false, true, null, false, true, 'right');
};

// Initialise the dropdowns in a participants filter row.
export const init = uniqid => {
    // Set listener on each filter dropdown menu.
    document.querySelectorAll('.dropdown-menu.user-filter-select').forEach((dropdown) => {
        dropdown.addEventListener('click', function(e) {
            setDropdownValue(e, uniqid);
        });
    });
};
