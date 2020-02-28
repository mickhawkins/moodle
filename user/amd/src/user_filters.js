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
 * Module responsible for handling forum summary report filters.
 *
 * @module     core_user/user_filter
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (uniqid) => {

//TODO: Most of this is probably generic enough that it can be initialised in the outer filter,
//          rather than once per filter (so uniqid wouldnt be passed in).
//TODO: Need to remember that additional ones will be added dynamically, and will need the
//       same handlers applied to them, so it should support that.
//TODO: Any of the type in search stuff / handling of the selection probably need to be
//      imported from a new file (or at least set as a separate const).

    // Handler for setting values for single dropdowns.
    const setDropdownValue = e => {
        e.preventDefault();

        const dropdown = e.target;
        const dropdownType = dropdown.parentNode.parentNode.querySelector('button').id;

        // Display the selection and set the hidden value.
        document.getElementById(dropdownType).innerText = dropdown.innerText;
        document.getElementById(`${dropdownType}-selected`).value = dropdown.getAttribute('data-value');

        // Filter type dropdowns need to display the filter options.
        if (dropdown.hasAttribute("data-filter-options-id")) {
            const optionsid = dropdown.getAttribute("data-filter-options-id");

            document.getElementById(optionsid).classList.add('d-inline-block');
            document.getElementById(optionsid).classList.remove('hidden');
        }

    };

    const setOptionValue = e => {
        const value = e.target.getAttribute('data-value');
        let valueTag = document.createElement('span');
        let crossicon = document.createElement('i');
        //TODO: Figure out if we need to store the values somewhere hidden as well, for easy sending in the web service

        // Set up and display new tag for chosen value.
        valueTag.setAttribute('data-value', value);
        valueTag.classList.add('d-inline-block', 'bg-dark', 'text-white', 'rounded', 'font-weight-bold', 'px-1');
        valueTag.innerText = e.target.innerText;
        crossicon.classList.add('icon', 'fa', 'fa-times', 'pl-2', 'mr-0');
        valueTag.appendChild(crossicon);
        document.getElementById(`${uniqid}-filter-options-set`).appendChild(valueTag);
    };

    const toggleFilterDropdown = e => {
        const dropdownList = e.target.parentNode.querySelector('ul');

        //TODO: This is the start of selecting the filter type, where it shows a dropdown when selected,
                //there's also the case of the value being selected on the next dropdown, which adds the
                //little removable tag bits. Need to see if they can be differentiated (maybe a data- tag) that
            //allows generic code here to set one of two different event listeners, depending which type it is.
        dropdownList.addEventListener('click', setOptionValue);

        dropdownList.classList.remove('hidden');
    };

    // Set listener on each filter dropdown menu.
    document.querySelectorAll('.dropdown-menu.user-filter-select').forEach((dropdown) => {
        dropdown.addEventListener('click', setDropdownValue);
    });

    // Add listeners for 'type or select' filter options type.
    const filterdiv = document.getElementById(`${uniqid}-filter-options`);

    filterdiv.querySelector('input[type="text"]').addEventListener('focus', toggleFilterDropdown);
    filterdiv.querySelector('span').addEventListener('click', toggleFilterDropdown);

};
