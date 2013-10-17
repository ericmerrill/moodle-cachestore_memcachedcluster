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
 * The library file for the memcached cluster cache store.
 *
 * @package    cachestore_memcachedcluster
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/cache/stores/memcached/addinstanceform.php');

/**
 * Form for adding a memcachedcluster instance.
 *
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_memcachedcluster_addinstance_form extends cachestore_memcached_addinstance_form {

    /**
     * Add the desired form elements.
     */
    protected function configuration_definition() {
        parent::configuration_definition();

        $form = $this->_form;

        // Rename the servers element.
        $servers = $form->getElement('servers');
        $servers->_label = get_string('servers', 'cachestore_memcachedcluster');

        // Rewrite the help button.
        $form->addHelpButton('servers', 'servers', 'cachestore_memcachedcluster');

        // Make the new element, and insert it before prefix.
        $setservers = $form->createElement('textarea', 'setservers', get_string('setservers', 'cachestore_memcachedcluster'),
                array('cols' => 75, 'rows' => 5));
        $form->insertElementBefore($setservers, 'compression');
        $form->addHelpButton('setservers', 'setservers', 'cachestore_memcachedcluster');
        $form->addRule('setservers', get_string('required'), 'required');
        $form->setType('setservers', PARAM_RAW);
    }
}
