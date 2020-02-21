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

//TODO: CLEANUP imports
//import $ from 'jquery';
//import Popper from 'core/popper';
//import CustomEvents from 'core/custom_interaction_events';
//import Selectors from 'forumreport_summary/selectors';
//import Y from 'core/yui';
//import Ajax from 'core/ajax';
//import KeyCodes from 'core/key_codes';

export const init = (uniqid) => {

//TODO: Most of this is probably generic enough that it can be initialised in the outer filter,
//          rather than once per filter (so uniqid wouldnt be passed in).
//TODO: Need to remember that additional ones will be added dynamically, and will need the
//       same handlers applied to them, so it should support that.
//TODO: Any of the type in search stuff / handling of the selection probably need to be
//      imported from a new file (or at least set as a separate const).

    const chooseOption = e => {
        window.console.log(e.target.getAttribute('data-value'));
        //TODO: This is fetching the correct value. The template will need a select to replace the "type or select"
                //with in the UI, and then re-appear if they click it?
    };

    const toggleFilterDropdown = e => {
        const dropdownList = e.target.parentNode.querySelector('ul');

        //TODO: This is the start of selecting the filter type, where it shows a dropdown when selected,
                //there's also the case of the value being selected on the next dropdown, which adds the
                //little removable tag bits. Need to see if they can be differentiated (maybe a data- tag) that
            //allows generic code here to set one of two different event listeners, depending which type it is.
        dropdownList.addEventListener('click', chooseOption);

        dropdownList.classList.remove('hidden');
    };

    const filterdiv = document.getElementById(`filter-types-${uniqid}`);

    filterdiv.querySelector('input[type="text"]').addEventListener('focus', toggleFilterDropdown);
    filterdiv.querySelector('span').addEventListener('click', toggleFilterDropdown);

};
