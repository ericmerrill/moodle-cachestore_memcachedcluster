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
 * The settings for the memcached cluster store.
 *
 * This file is part of the memcached cluster cache store, it contains the API for interacting with an instance of the store.
 *
 * @package    cachestore_memcachedcluster
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$settings->add(new admin_setting_configtextarea(
        'cachestore_memcachedcluster/testserversget',
        new lang_string('testfetchservers', 'cachestore_memcachedcluster'),
        new lang_string('testfetchservers_desc', 'cachestore_memcachedcluster'),
        '', PARAM_RAW, 60, 3));

$settings->add(new admin_setting_configtextarea(
        'cachestore_memcachedcluster/testserverset',
        new lang_string('testsetservers', 'cachestore_memcachedcluster'),
        new lang_string('testsetservers_desc', 'cachestore_memcachedcluster'),
        '', PARAM_RAW, 60, 3));