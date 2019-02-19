<?php
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
 * Navigation step definition overrides for the Classic theme.
 *
 * @package    theme_classic
 * @category   test
 * @copyright  2019 Michael Hawkins
 * @copyright  Based on 2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: No MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/tests/behat/behat_navigation.php');

use Behat\Mink\Exception\ExpectationException as ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

/**
 * Step definitions and overrides to navigate through the navigation tree nodes in the Classic theme.
 *
 * @package    theme_classic
 * @category   test
 * @copyright  2019 Michael Hawkins
 * @copyright  Based on 2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_theme_classic_behat_navigation extends behat_navigation {
    /**
     * Navigate to an item in a current page administration menu.
     *
     * @throws ExpectationException
     * @param string $nodetext The navigation node/path to follow, eg "Course administration > Edit settings"
     * @return void
     */
    public function i_navigate_to_in_current_page_administration($nodetext) {
        $parentnodes = array_map('trim', explode('>', $nodetext));

        // Find the name of the first category of the administration block tree.
        $xpath = "//section[contains(@class,'block_settings')]//div[@id='settingsnav']/ul[1]/li[1]/p[1]/span";
        $node = $this->find('xpath', $xpath);

        array_unshift($parentnodes, $node->getText());
        $lastnode = array_pop($parentnodes);
        $this->select_node_in_navigation($lastnode, $parentnodes);
    }

    /**
     * Navigate to an item within the site administration menu.
     *
     * @throws ExpectationException
     * @param string $nodetext The navigation node/path to follow, excluding "Site administration" itself, eg "Grades > Scales"
     * @return void
     */
    public function i_navigate_to_in_site_administration($nodetext) {
        $parentnodes = array_map('trim', explode('>', $nodetext));
        array_unshift($parentnodes, get_string('administrationsite'));
        $lastnode = array_pop($parentnodes);
        $this->select_node_in_navigation($lastnode, $parentnodes);
    }

    protected function get_top_navigation_node($nodetext) {
        // Avoid problems with quotes.
        $nodetextliteral = behat_context_helper::escape($nodetext);
        $exception = new ExpectationException('Top navigation node "' . $nodetext . '" not found', $this->getSession());

        $xpath = // Navigation block.
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' content ')]" .
                "/ul[contains(concat(' ', normalize-space(@class), ' '), ' block_tree ')]" .
                "/li[contains(concat(' ', normalize-space(@class), ' '), ' contains_branch ')]" .
                "/ul/li[contains(concat(' ', normalize-space(@class), ' '), ' contains_branch ')]" .
                "[p[contains(concat(' ', normalize-space(@class), ' '), ' branch ')]" .
                "[span[normalize-space(.)={$nodetextliteral}] or a[normalize-space(.)={$nodetextliteral}]]]" .
                "|" .
                // Administration block.
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' content ')]/div" .
                "/ul[contains(concat(' ', normalize-space(@class), ' '), ' block_tree ')]" .
                "/li[contains(concat(' ', normalize-space(@class), ' '), ' contains_branch ')]" .
                "/ul/li[contains(concat(' ', normalize-space(@class), ' '), ' contains_branch ')]" .
                "[p[contains(concat(' ', normalize-space(@class), ' '), ' branch ')]" .
                "/span[normalize-space(.)={$nodetextliteral}]]" .
                "|" .
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' content ')]/div" .
                "/ul[contains(concat(' ', normalize-space(@class), ' '), ' block_tree ')]" .
                "/li[p[contains(concat(' ', normalize-space(@class), ' '), ' branch ')]" .
                "/span[normalize-space(.)={$nodetextliteral}]]" .
                "|" .
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' content ')]/div" .
                "/ul[contains(concat(' ', normalize-space(@class), ' '), ' block_tree ')]" .
                "/li[p[contains(concat(' ', normalize-space(@class), ' '), ' branch ')]" .
                "/a[normalize-space(.)={$nodetextliteral}]]";

        $node = $this->find('xpath', $xpath, $exception);

        return $node;
    }

    /**
     * Check that current page administration contains an element.
     *
     * @throws ElementNotFoundException
     * @param string $element The locator of the specified selector.
     *     This may be a path, for example "Subscription mode > Forced subscription"
     * @param string $selectortype The selector type (link or text)
     * @return void
     */
    public function should_exist_in_current_page_administration($element, $selectortype) {
        $nodes = array_map('trim', explode('>', $element));
        $roottext = '';//(count($nodes) === 1 && $selectortype === 'text') ? $nodes[0] : '';
        $nodetext = end($nodes);
        $closemenu = false;

        // Find administration menu.
        $rootxpath = $this->find_header_administration_menu() ?: $this->find_page_administration_menu(true, $roottext);
        $menuxpath = $rootxpath;

        // If we are checking for a sub-menu node, complete the xpath.
        if (count($nodes) > 1) {
            // Ensure the menu is open before trying to access sub-menus.
            $closemenu = $this->open_page_administration_menu($rootxpath);

            for ($i = 0; $i < (sizeof($nodes) - 1); $i++) {
                if ($i === 0) {
                    // Root navigation level is text and not a link.
                    $menuxpath .= "/../../ul[1]/li";
                } else {
                    $menuxpath .= "/p[a[contains(text(), '{$nodes[$i]}')]/../../../ul[1]/li";
                    $menuxpath .= "|a/span[contains(text(), '{$nodes[$i]}')]/../../../ul[1]/li]";
                }
            }

            if ($selectortype == 'link') {
                $menuxpath .= "/p[a[contains(text(), '{$nodetext}')]";
                $menuxpath .= "|a/span[contains(text(), '{$nodetext}')]]";
            }
        }

        $exception = new ElementNotFoundException($this->getSession(), "Failed xpath: {$menuxpath}");
        $this->find('xpath', $menuxpath, $exception);

        // Close the menu if it was opened by this method.
        if ($closemenu) {
            $this->close_page_administration_menu($rootxpath);
        }
    }

    /**
     * Check that current page administration does not contains an element.
     *
     * @throws ExpectationException
     * @param string $element The locator of the specified selector.
     *     This may be a path, for example "Subscription mode > Forced subscription"
     * @param string $selectortype The selector type (link or text)
     * @return void
     */
    public function should_not_exist_in_current_page_administration($element, $selectortype) {
        $nodes = array_map('trim', explode('>', $element));
        $roottext = '';//(count($nodes) === 1 && $selectortype === 'text') ? $nodes[0] : '';

        try {
            $menuxpath = $this->find_header_administration_menu() ?: $this->find_page_administration_menu(true, $roottext);
        } catch (Exception $e) {
            // If an exception was thrown, it means the root note does not exist, so we can conclude the test is a success.
            return;
        }

        // Check whether page administration is closed.
        $menunode = $this->find('xpath', "{$menuxpath}/..");
        $closemenu = ($menunode->getAttribute('aria-expanded') === 'false');

        // Test if the element exists.
        try {
            $this->should_exist_in_current_page_administration($element, $selectortype);
        } catch(ElementNotFoundException $e) {
            //Collapse the menu if it was closed before this test and we are checking a sub-menu.
            // Note: This is necessary because the try block will open the menu, but throws an exception before closing it again.
            if (count($nodes) > 1 && $closemenu) {
                $this->close_page_administration_menu($menuxpath);
            }

            // If an exception was thrown, it means the element does not exist, so the test is successful.
            return;
        }

        // If the try block passed, the element exists, so throw an exception.
        $exception = 'The "' . $element . '" "' . $selectortype . '" was found, but should not exist';
        throw new ExpectationException($exception, $this->getSession());
    }

    /**
     * Check that the page administration menu does not exist on the page.
     *
     * This confirms the absence of the menu, which unauthorised users should not have access to.
     * @Given /^I should not see the page administration menu$/
     *
     * @throws ExpectationException
     * @return void
     */
    public function page_administration_does_not_exist() {
        $menuxpath = "//section[contains(@class,'block_settings')]//div[@id='settingsnav']";
        $this->ensure_element_does_not_exist($menuxpath, 'xpath_element');
    }

    /**
     * Locate the administration menu on the page (but not in the header) and return its xpath.
     *
     * @throws ElementNotFoundException
     * @param bool $mustexist If true, throws an exception if menu is not found
     * @param bool $nodetext (optional) The name of the administration menu to find
     * @return null|string
     */
    protected function find_page_administration_menu($mustexist = false, $nodetext = '') {
        $menuxpath = "//section[contains(@class,'block_settings')]//div[@id='settingsnav']/ul[1]/li";

        if (!empty($nodetext)) {
            $menuxpath .= "/p/span[contains(text(), '{$nodetext}')]";
        }

        if ($mustexist) {
            $exception = new ElementNotFoundException($this->getSession(), 'Page administration menu is not found');
            $this->find('xpath', $menuxpath, $exception);

        } else if (!$this->getSession()->getPage()->find('xpath', $menuxpath)) {
            return null;
        }

        return $menuxpath;
    }

    /**
     * Open the administration menu if it is closed/collapsed.
     *
     * @param string $menuxpath (optional) Xpath to the page administration menu if already known
     * @param string $nodetext (optional) The name of the administration menu being opened, if no $menuxpath is being provided
     * @return bool True if the menu needed to be opened (was closed previously)
     */
    protected function open_page_administration_menu($menuxpath = null, $nodetext = '') {
        $actioned = false;

        if (!$menuxpath) {
            $menuxpath = $this->find_header_administration_menu() ?: $this->find_page_administration_menu(false, $nodetext);
        }

        if ($menuxpath && $this->running_javascript()) {
            // The node we need is the <p> above the menu xpath.
            $menunode = $this->find('xpath', "{$menuxpath}/..");

            // If menu is collapsed, open it.
            if ($menunode->getAttribute('aria-expanded') === 'false') {
                $menunode->click();
                $this->wait_for_pending_js();
                $actioned = true;
            }
        }

        return $actioned;
    }

    /**
     * Close/collapse the administration menu if it is open.
     *
     * @param string $menuxpath (optional) Xpath to the page administration menu if already known
     * @param string $nodetext (optional) The name of the administration menu being closed, if no $menuxpath is being provided
     *
     * @return bool True if the menu needed to be closed (was open previously)
     */
    protected function close_page_administration_menu($menuxpath = null, $nodetext = '') {
        $actioned = false;

        if (!$menuxpath) {
            $menuxpath = $this->find_header_administration_menu() ?: $this->find_page_administration_menu(false, $nodetext);
        }

        if ($menuxpath && $this->running_javascript()) {
            // The node we need is the <p> above the menu xpath.
            $menunode = $this->find('xpath', "{$menuxpath}/..");

            // If menu is open, collapse it.
            if ($menunode->getAttribute('aria-expanded') === 'true') {
                $menunode->click();
                $this->wait_for_pending_js();
                $actioned = true;
            }
        }

        return $actioned;
    }
}
