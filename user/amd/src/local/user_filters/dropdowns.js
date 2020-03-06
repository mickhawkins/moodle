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
import {get_string} from 'core/str';
import Selectors from './selectors';

// Set values for single dropdowns.
const setDropdownValue = async (e, uniqid) => {
    e.preventDefault();

    const optionSelected = e.target;

    // Only handle specific items being selected.
    if (optionSelected.classList.contains('dropdown-item')) {
        const dropdownButton = optionSelected.parentNode.parentNode.querySelector('button');
        const previouslySet = dropdownButton.getAttribute('data-option-selected');

        // Display the selection and set the hidden value.
        dropdownButton.innerText = optionSelected.innerText;
        dropdownButton.setAttribute('data-option-selected', optionSelected.getAttribute('data-value'));

        // Filter type enhanced dropdowns need to display the filter options.
        if (optionSelected.hasAttribute("data-filter-type") && !previouslySet &&
                optionSelected.getAttribute("data-filter-type") === 'enhanceddropdown') {

            const selectString = await get_string('typeorselect', 'core');

            Autocomplete.enhance(Selectors.filters.dropdown.toEnhance(uniqid), true, null, selectString,
                    false, true, null, true);
        }
    }
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
