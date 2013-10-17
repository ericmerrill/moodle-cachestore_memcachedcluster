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
 * Memcached Cluster unit tests.
 *
 * If you wish to use these unit tests all you need to do is run memcached servers on two ports
 * (11211 and 11210), and add the following definitions to your config.php file.
 *
 * define('TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1', '127.0.0.1:11211');
 * define('TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_2', '127.0.0.1:11210');
 *
 * @package    cachestore_memcachedcluster
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/cache/tests/fixtures/stores.php');
require_once($CFG->dirroot.'/cache/stores/memcachedcluster/lib.php');

/**
 * Memcached cluster unit test class.
 *
 * @package    cachestore_memcachedcluster
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_memcachedcluster_test extends cachestore_tests {
    /**
     * Prepare to run tests.
     */
    public function setUp() {
        if (defined('TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1')
                && defined('TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_2')) {
            // Default instance reads server 1, and sets servers 1 and 2.
            set_config('testserversget', TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1, 'cachestore_memcachedcluster');
            $setservers = TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1."\n".TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_2;
            set_config('testserverset', $setservers, 'cachestore_memcachedcluster');
            $this->resetAfterTest();
        }
        parent::setUp();
    }
    /**
     * Returns the memcached cluster class name
     * @return string
     */
    protected function get_class_name() {
        return 'cachestore_memcachedcluster';
    }

    /**
     * Tests the valid keys to ensure they work.
     */
    public function test_valid_keys() {
        if (!defined('TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1')
                || !defined('TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_2')) {
            $this->markTestSkipped();
        }
        $this->resetAfterTest();

        // Default instance reads server 1, and sets servers 1 and 2.
        set_config('testserversget', TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1, 'cachestore_memcachedcluster');
        $setservers = TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_1."\n".TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_2;
        set_config('testserverset', $setservers, 'cachestore_memcachedcluster');

        $definition = cache_definition::load_adhoc(cache_store::MODE_APPLICATION, 'cachestore_memcachedcluster', 'phpunit_test');
        $instance = cachestore_memcachedcluster::initialise_test_instance($definition);

        // Now we are going to setup a second instance that retreives from server 2.
        set_config('testserversget', TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVER_2, 'cachestore_memcachedcluster');
        $checkinstance = cachestore_memcachedcluster::initialise_test_instance($definition);

        // Something prevented memcached store to be inited (extension, TEST_CACHESTORE_MEMCACHEDCLUSTER_TESTSERVERS...).
        if (!$instance || !$checkinstance) {
            $this->markTestSkipped();
        }

        $keys = array(
            // Alphanumeric.
            'abc', 'ABC', '123', 'aB1', '1aB',
            // Hyphens.
            'a-1', '1-a', '-a1', 'a1-',
            // Underscores.
            'a_1', '1_a', '_a1', 'a1_'
        );

        // Set each key.
        foreach ($keys as $key) {
            $this->assertTrue($instance->set($key, $key), "Failed to set key `$key`");
        }

        // Check each key.
        foreach ($keys as $key) {
            $this->assertEquals($key, $instance->get($key), "Failed to get key `$key`");
            $this->assertEquals($key, $checkinstance->get($key), "Failed to get key `$key` from server 2");
        }

        // Reset a key.
        $this->assertTrue($instance->set($keys[0], 'New'), "Failed to reset key `$key`");
        $this->assertEquals('New', $instance->get($keys[0]), "Failed to get reset key `$key`");
        $this->assertEquals('New', $checkinstance->get($keys[0]), "Failed to get reset key `$key` from server 2");

        // Delete and check that we can't retrieve.
        foreach ($keys as $key) {
            $this->assertTrue($instance->delete($key), "Failed to delete key `$key`");
            $this->assertFalse($instance->get($key), "Retrieved deleted key `$key`");
            $this->assertFalse($checkinstance->get($key), "Retrieved deleted key `$key` from server 2");
        }

        // Try set many, and check that count is correct.
        $many = array();
        foreach ($keys as $key) {
            $many[] = array('key' => $key, 'value' => $key);
        }
        $returncount = $instance->set_many($many);
        $this->assertEquals(count($many), $returncount, 'Set many count didn\'t match');

        // Check keys retrieved with get_many.
        $values = $instance->get_many($keys);
        foreach ($keys as $key) {
            $this->assertTrue(isset($values[$key]));
            $this->assertEquals($key, $values[$key]);
        }
        $values = $checkinstance->get_many($keys);
        foreach ($keys as $key) {
            $this->assertTrue(isset($values[$key]));
            $this->assertEquals($key, $values[$key]);
        }

        // Delete many, make sure count matches.
        $returncount = $instance->delete_many($keys);
        $this->assertEquals(count($many), $returncount, 'Delete many count didn\'t match');

        // Check that each key was deleted.
        foreach ($keys as $key) {
            $this->assertFalse($instance->get($key), "Retrieved many deleted key `$key`");
            $this->assertFalse($checkinstance->get($key), "Retrieved many deleted key `$key` from server 2");
        }

        // Set the keys again.
        $returncount = $instance->set_many($many);
        $this->assertEquals(count($many), $returncount, 'Set many count didn\'t match');

        // Purge.
        $this->assertTrue($instance->purge(), 'Failure to purge');

        // Delete and check that we can't retrieve.
        foreach ($keys as $key) {
            $this->assertFalse($instance->get($key), "Retrieved purged key `$key`");
            $this->assertFalse($checkinstance->get($key), "Retrieved purged key `$key` from server 2");
        }
    }
}
