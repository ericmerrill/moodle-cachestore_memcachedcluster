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
 * This file is part of the memcached cluster cache store, it contains the API for interacting with an instance of the store.
 *
 * @package    cachestore_memcachedcluster
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/cache/stores/memcached/lib.php');

/**
 * The memcached cluster store class.
 *
 * (Not to be confused with memcache store)
 *
 * @copyright  2013 Eric Merrill
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_memcachedcluster extends cachestore_memcached {
    /**
     * The memcache connection once established.
     * @var Memcache
     */
    protected $getconnection;

    /**
     * The memcache connection once established.
     * @var Memcache
     */
    protected $setconnections;

    /**
     * An array of servers to use in the connection args.
     * @var array
     */
    protected $setservers = array();

    /**
     * Constructs the store instance.
     *
     * Noting that this function is not an initialisation. It is used to prepare the store for use.
     * The store will be initialised when required and will be provided with a cache_definition at that time.
     *
     * @param string $name
     * @param array $configuration
     */
    public function __construct($name, array $configuration = array()) {
        // Let the parent do the main setup.
        parent::__construct($name, $configuration);

        // Take the ready back, until we are ready.
        $ready = $this->isready;
        $this->isready = false;

        if (!array_key_exists('setservers', $configuration) || empty($configuration['setservers'])) {
            // Nothing configured.
            return;
        }

        // Get the connection and move it into getconnection.
        $this->getconnection = $this->connection;

        if (!is_array($configuration['setservers'])) {
            $configuration['setservers'] = array($configuration['setservers']);
        }

        foreach ($configuration['setservers'] as $setserver) {
            if (!is_array($setserver)) {
                $setserver = trim($setserver);
                $setserver = explode(':', $setserver, 3);
            }

            // We don't use weights, so display a debug message.
            if (count($setserver) > 2) {
                debugging('Memcached Set Server '.$setserver[0].' has too many parameters.');
            }

            if (!array_key_exists(1, $setserver)) {
                $setserver[1] = 11211;
            }
            $this->setservers[] = $setserver;
        }

        $this->setconnections = array();
        foreach ($this->setservers as $setserver) {
            // Since we will have a number of them with the same name, append server and port.
            $connection = new Memcached(crc32($this->name.$setserver[0].$setserver[1]));
            foreach ($this->options as $key => $value) {
                $connection->setOption($key, $value);
            }
            $connection->addServer($setserver[0], $setserver[1]);
            $ready = $ready && @$connection->set("ping", 'ping', 1);
            $this->setconnections[] = $connection;
        }

        $this->isready = $ready;
    }

    /**
     * Sets an item in the cache given its key and data value.
     *
     * @param string $key The key to use.
     * @param mixed $data The data to set.
     * @return bool True if the operation was a success false otherwise.
     */
    public function set($key, $data) {
        $status = true;
        foreach ($this->setconnections as $connection) {
            $this->connection = $connection;

            $status = parent::set($key, $data) && $status;
        }
        $this->connection = $this->getconnection;
        return $status;
    }

    /**
     * Sets many items in the cache in a single transaction.
     *
     * @param array $keyvaluearray An array of key value pairs. Each item in the array will be an associative array with two
     *      keys, 'key' and 'value'.
     * @return int The number of items successfully set. It is up to the developer to check this matches the number of items
     *      sent ... if they care that is.
     */
    public function set_many(array $keyvaluearray) {
        $count = count($keyvaluearray);
        foreach ($this->setconnections as $connection) {
            $this->connection = $connection;
            // The sucess count will be the minimum of any sub-attempt.
            $count = min(parent::set_many($keyvaluearray), $count);
        }
        $this->connection = $this->getconnection;

        return $count;
    }

    /**
     * Deletes an item from the cache store.
     *
     * @param string $key The key to delete.
     * @return bool Returns true if the operation was a success, false otherwise.
     */
    public function delete($key) {
        $success = true;
        foreach ($this->setconnections as $connection) {
            $this->connection = $connection;
            $success = parent::delete($key) && $success;
        }
        $this->connection = $this->getconnection;

        return $success;
    }

    /**
     * Deletes several keys from the cache in a single action.
     *
     * @param array $keys The keys to delete
     * @return int The number of items successfully deleted.
     */
    public function delete_many(array $keys) {
        $count = count($keys);
        foreach ($this->setconnections as $connection) {
            $this->connection = $connection;
            // The sucess count will be the minimum of any sub-attempt.
            $count = min(parent::delete_many($keys), $count);
        }
        $this->connection = $this->getconnection;

        return $count;
    }

    /**
     * Purges the cache deleting all items within it.
     *
     * @return boolean True on success. False otherwise.
     */
    public function purge() {
        $success = true;
        foreach ($this->setconnections as $connection) {
            $this->connection = $connection;
            $success = parent::purge() && $success;
        }
        $this->connection = $this->getconnection;

        return $success;
    }

    /**
     * Given the data from the add instance form this function creates a configuration array.
     *
     * @param stdClass $data
     * @return array
     */
    public static function config_get_configuration_array($data) {
        $config = parent::config_get_configuration_array($data);

        $lines = explode("\n", $data->setservers);
        $setservers = array();
        foreach ($lines as $line) {
            $line = trim($line, ':');
            $line = trim($line);
            $setserver = explode(':', $line, 3);
            // We don't use weights, so display a debug message.
            if (count($setserver) > 2) {
                debugging('Memcached Set Server '.$setserver[0].' has too many parameters.');
            }
            $setservers[] = $setserver;
        }

        $config['setservers'] = $setservers;

        return $config;
    }

    /**
     * Allows the cache store to set its data against the edit form before it is shown to the user.
     *
     * @param moodleform $editform
     * @param array $config
     */
    public static function config_set_edit_form_data(moodleform $editform, array $config) {
        parent::config_set_edit_form_data($editform, $config);
        $data = (array)$editform->get_data();

        if (!empty($config['setservers'])) {
            $setservers = array();
            foreach ($config['setservers'] as $setserver) {
                $setservers[] = join(":", $setserver);
            }
            $data['setservers'] = join("\n", $setservers);
        }

        $editform->set_data($data);
    }

    /**
     * Performs any necessary clean up when the store instance is being deleted.
     */
    public function instance_deleted() {
        if ($this->getconnection) {
            $connection = $this->getconnection;
        } else {
            $connection = new Memcached(crc32($this->name));
            foreach ($this->servers as $server) {
                $connection->addServer($server[0], $server[1], true, $server[2]);
            }
        }
        @$connection->flush();
        unset($connection);
        unset($this->connection);

        if (!$this->setconnections) {
            $this->setconnections = array();
            foreach ($this->setservers as $setserver) {
                $connection = new Memcached(crc32($this->name.$setserver[0].$setserver[1]));
                $connection->addServer($setserver[0], $setserver[1]);
                $this->setconnections[] = $connection;
            }
        }
        foreach ($this->setconnections as $connection) {
            @$connection->flush();
        }
        unset($this->setconnections);
    }

    /**
     * Generates an instance of the cache store that can be used for testing.
     *
     * @param cache_definition $definition
     * @return cachestore_memcachedcluster|false
     */
    public static function initialise_test_instance(cache_definition $definition) {
        if (!self::are_requirements_met()) {
            return false;
        }

        $config = get_config('cachestore_memcachedcluster');
        if (empty($config->testserversget) || empty($config->testserverset)) {
            return false;
        }

        $configuration = array();
        $configuration['servers'] = explode("\n", $config->testserversget);
        $configuration['setservers'] = explode("\n", $config->testserverset);

        $store = new cachestore_memcachedcluster('Test memcached cluster', $configuration);
        $store->setup_test_getserver();
        $store->initialise($definition);

        return $store;
    }

    /**
     * Remakes the Memcached receiver instance without persistance for unit tests.
     * 
     * Without this, unit tests can false pass, because the checking object will
     * get the same persistant connection, even though it's to a different server.
     */
    public function setup_test_getserver() {
        $this->connection = new Memcached();
        $servers = $this->connection->getServerList();
        if (empty($servers)) {
            foreach ($this->options as $key => $value) {
                $this->connection->setOption($key, $value);
            }
            $this->connection->addServers($this->servers);
        }

        // Get the connection and move it into getconnection.
        $this->getconnection = $this->connection;
    }
}
