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
 * Module responsible for resetting a filter row to its default values.
 *
 * @module     core_user/local/user_filter/reset_row
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Autocomplete from 'core/form-autocomplete';
import Selectors from './selectors';

export const resetFilterRow = (uniqid) => {
    const filterRow = document.getElementById(Selectors.filters.row.id(uniqid));
    const joinTypeElement = document.getElementById(Selectors.filters.row.joinType(uniqid));
    const filterTypeElement = document.getElementById(Selectors.filters.row.filterType(uniqid));
    const enhancedDropdown = filterRow.querySelector('div [data-autocomplete-uniqueid]');

    joinTypeElement.setAttribute('data-option-selected', joinTypeElement.getAttribute('data-default-selected'));
    joinTypeElement.innerText = joinTypeElement.getAttribute('data-default-label');
    filterTypeElement.setAttribute('data-option-selected', filterTypeElement.getAttribute('data-default-selected'));
    filterTypeElement.innerText = filterTypeElement.getAttribute('data-default-label');

    if (enhancedDropdown) {
        const enhancedUniqueId = enhancedDropdown.getAttribute('data-autocomplete-uniqueid');
        Autocomplete.remove(Selectors.filters.dropdown.select(uniqid), enhancedUniqueId);
    }

    document.getElementById(`${uniqid}-filter-row-clear`).classList.add('disabled');
};

// Initialise the button that resets a row.
export const initResetRowButton = (uniqid) => {
    document.getElementById(Selectors.filters.row.clear(uniqid)).addEventListener('click', () => {
        resetFilterRow(uniqid);
    });
};
