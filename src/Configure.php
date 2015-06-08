<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
App::uses('Hash', 'library/Utility');
App::uses('ConfigReaderInterface', 'core/Config');

class Config
{
    /**
     * Array of values currently stored in Configure.
     *
     * @var array
     */
    protected static $_values = [
        'debug' => 0,
    ];

    /**
     * Configured reader classes, used to load config files from resources.
     *
     * @var array
     *
     * @see Configure::load()
     */
    protected static $_readers = [];

    /**
     * site id to get the settings.
     *
     * @var int
     */
    public static $_getsite    = 1;
    /**
     * site id to update settings.
     *
     * @var int
     */
    public static $_upsite    = 1;

    public static $database;

    public static function init()
    {
        $siteid = Registry::get('configid');
        if ($siteid) {
            self::$_getsite = $siteid;
        }

        $siteid = Registry::get('siteid');
        if ($siteid) {
            self::$_upsite = $siteid;
        }

        self::$database = Registry::get('database');
    }

    /**
     * Initializes configure and runs the bootstrap process.
     * Bootstrapping includes the following steps:.
     *
     * - Setup App array in Configure.
     * - Include app/Config/core.php.
     * - Configure core cache configurations.
     * - Load App cache files.
     * - Include app/Config/bootstrap.php.
     * - Setup error/exception handlers.
     *
     * @param bool $boot
     */
    public static function bootstrap($boot = true)
    {
        if ($boot) {
            //connect to database if set
            App::uses('Database', 'database');

            $datasource  = self::read('database.config');
            $datasource  = ($datasource) ? $datasource : 'default';
            $config      = self::read('database.'.$datasource);
            $database    = false;

            if (is_array($config)) {
                $database    = new Database();
                $db          = $database->connect($config);
                if (!$db) {
                    if (php_sapi_name() == 'cli') {
                        echo json_encode([
                            'status'  => 'ERROR',
                            'message' => 'database was gone away',
                            'error'   => $database->lastError(),
                        ]);
                    } else {
                        $path = SYS.'public'.DS.'templates'.DS.'system'.DS.'databasegone.tpl';
                        echo @file_get_contents($path);
                        die('<!-- Database was gone away... -->');
                    }
                }
            }
            Registry::set('database', $database);

            register_shutdown_function(function () use ($database) {
                $database->disConnect();
            });
        }
    }

    /**
     * Used to read information stored in Configure. Its not
     * possible to store `null` values in Configure.
     *
     * Usage:
     * {{{
     * Configure::read('Name'); will return all values for Name
     * Configure::read('Name.key'); will return only the value of Configure::Name[key]
     * }}}
     *
     * @link http://book.cakephp.org/2.0/en/development/configuration.html#Configure::read
     *
     * @param string $var Variable to obtain. Use '.' to access array elements.
     *
     * @return mixed value stored in configure, or null.
     */
    public static function read($var = null)
    {
        if ($var === null) {
            return self::$_values;
        }

        return Hash::get(self::$_values, $var);
    }

    /**
     * Returns true if given variable is set in Configure.
     *
     * @param string $var Variable name to check for
     *
     * @return bool True if variable is there
     */
    public static function check($var = null)
    {
        if (empty($var)) {
            return false;
        }

        return Hash::get(self::$_values, $var) !== null;
    }

    /**
     * Used to store a dynamic variable in Configure.
     *
     * Usage:
     * {{{
     * Configure::write('One.key1', 'value of the Configure::One[key1]');
     * Configure::write(array('One.key1' => 'value of the Configure::One[key1]'));
     * Configure::write('One', array(
     *     'key1' => 'value of the Configure::One[key1]',
     *     'key2' => 'value of the Configure::One[key2]'
     * );
     *
     * Configure::write(array(
     *     'One.key1' => 'value of the Configure::One[key1]',
     *     'One.key2' => 'value of the Configure::One[key2]'
     * ));
     * }}}
     *
     * @link http://book.cakephp.org/2.0/en/development/configuration.html#Configure::write
     *
     * @param string|array $config The key to write, can be a dot notation value.
     *                             Alternatively can be an array containing key(s) and value(s).
     * @param mixed        $value  Value to set for var
     *
     * @return bool True if write was successful
     */
    public static function write($config, $value = null)
    {
        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $name => $value) {
            self::$_values = Hash::insert(self::$_values, $name, $value);
        }

        return true;
    }

    /**
     * Used to delete a variable from Configure.
     *
     * Usage:
     * {{{
     * Configure::delete('Name'); will delete the entire Configure::Name
     * Configure::delete('Name.key'); will delete only the Configure::Name[key]
     * }}}
     *
     * @link http://book.cakephp.org/2.0/en/development/configuration.html#Configure::delete
     *
     * @param string $var the var to be deleted
     */
    public static function delete($var = null)
    {
        self::$_values = Hash::remove(self::$_values, $var);
    }

    /**
     * Clear all values stored in Configure.
     *
     * @return bool success.
     */
    public static function clear()
    {
        self::$_values = [];

        return true;
    }

    /**
     * Loads stored configuration information from a resource.  You can add
     * config file resource readers with `Configure::config()`.
     *
     * Loaded configuration information will be merged with the current
     * runtime configuration. You can load configuration files from plugins
     * by preceding the filename with the plugin name.
     *
     * `Configure::load('Users.user', 'default')`
     *
     * Would load the 'user' config file using the default config reader.  You can load
     * app config files by giving the name of the resource you want loaded.
     *
     * `Configure::load('setup', 'default');`
     *
     * If using `default` config and no reader has been configured for it yet,
     * one will be automatically created using PhpReader
     *
     * @link http://book.cakephp.org/2.0/en/development/configuration.html#Configure::load
     *
     * @param string $key    name of configuration resource to load.
     * @param string $config Name of the configured reader to use to read the resource identified by $key.
     * @param bool   $merge  if config files should be merged instead of simply overridden
     *
     * @return mixed false if file not found, void if load successful.
     *
     * @throws ConfigureException Will throw any exceptions the reader raises.
     */
    public static function load($key, $config = 'default', $merge = true)
    {
        if (!isset(self::$_readers[$config])) {
            if ($config === 'default') {
                App::uses('PhpReader', 'core/Config');
                self::$_readers[$config] = new PhpReader();
            } else {
                $class = ucfirst($config).'Reader';
                App::uses($class, 'core/Config');
                if (!class_exists($class)) {
                    return false;
                }

                self::$_readers[$config] = new $class();
                //return false;
            }
        }

        $values = self::$_readers[$config]->read($key);

        if ($merge) {
            $keys = array_keys($values);
            foreach ($keys as $key) {
                if (($c = self::read($key)) && is_array($values[$key]) && is_array($c)) {
                    $values[$key] = Hash::merge($c, $values[$key]);
                }
            }
        }

        return self::write($values);
    }

    /**
     * Add a new reader to Configure.  Readers allow you to read configuration
     * files in various formats/storage locations.  CakePHP comes with two built-in readers
     * PhpReader and IniReader.  You can also implement your own reader classes in your application.
     *
     * To add a new reader to Configure:
     *
     * `Config::reader('ini', new IniReader());`
     *
     * @param string                $name   The name of the reader being configured.  This alias is used later to
     *                                      read values from a specific reader.
     * @param ConfigReaderInterface $reader The reader to append.
     */
    public static function reader($name, ConfigReaderInterface $reader)
    {
        self::$_readers[$name] = $reader;
    }

    /**
     * Gets the names of the configured reader objects.
     *
     * @param string $name
     *
     * @return array Array of the configured reader objects.
     */
    public static function readers($name = null)
    {
        if ($name) {
            return isset(self::$_readers[$name]);
        }

        return array_keys(self::$_readers);
    }

    /**
     * Remove a configured reader.  This will unset the reader
     * and make any future attempts to use it cause an Exception.
     *
     * @param string $name Name of the reader to drop.
     *
     * @return bool Success
     */
    public static function drop($name)
    {
        if (!isset(self::$_readers[$name])) {
            return false;
        }
        unset(self::$_readers[$name]);

        return true;
    }

    /**
     * Used to write runtime configuration into Cache. Stored runtime configuration can be
     * restored using `Configure::restore()`. These methods can be used to enable configuration managers
     * frontends, or other GUI type interfaces for configuration.
     *
     * @param string $name        The storage name for the saved configuration.
     * @param string $cacheConfig The cache configuration to save into. Defaults to 'default'
     * @param array  $data        Either an array of data to store, or leave empty to store all values.
     *
     * @return bool Success
     */
    public static function store($name, $data = null, $cacheConfig = 'default')
    {
        if ($data === null) {
            $data = self::$_values;
        }

        return Cache::write($name, $data, $cacheConfig);
    }

    /**
     * Restores configuration data stored in the Cache into configure. Restored
     * values will overwrite existing ones.
     *
     * @param string $name        Name of the stored config file to load.
     * @param string $cacheConfig Name of the Cache configuration to read from.
     *
     * @return bool Success.
     */
    public static function restore($name, $cacheConfig = 'default')
    {
        $values = Cache::read($name, $cacheConfig);
        if ($values) {
            return self::write($values);
        }

        return false;
    }

    /**
     * alias function for read.
     *
     * @param [type] $val [description]
     * @param [type] $old [description]
     *
     * @return [type] [description]
     */
    public static function get($val = null, $old = null)
    {
        if ($val && $old) {
            return self::$_values[$val][$old];
        }

        return self::read($val);
    }

    /**
     * Get option from array of data.
     *
     * @param string $key
     *
     * @return string|int if exists or NULL if not
     **/
    public static function getAll()
    {
        return self::get();
    }

    public static function set($config, $value = null)
    {
        return self::write($config, $value);
    }

    /**
     * Store option and it's value in data array.
     *
     * @param string $key, mixed $value
     **/
    public function setMulti($name, $key, $value)
    {
        self::$_values[$name][$key] = $value;
    }

    /**
     * Retrive option from database.
     *
     * @Params string $option_name, boolean $default
     *
     * @Return string option_value
     **/
    public function getOption($option_name, $key = '')
    {
        $data  = $this->database->find('#__core_options', 'first', [
            'conditions' => ['option_name' => $option_name,'fksiteid' => self::$_getsite],
            'fields'     => ['option_value'],
            ]
        );

        $value = $data['option_value'];
        // if the value is json encoded we need to decode that
        if (self::isJson($value)) {
            $value = json_decode($value, true);
        }
        if ($key) {
            $value = $value[$key];
        }

        return $value;
    }

    /** update option if already exists or create new option of not
     * @params string $option_name | mixed $newvalue
     *
     * @return true on success or false on fail
     **/
    public function updateOption($option_name, $newvalue)
    {
        if (is_array($newvalue) || is_object($newvalue)) {
            $newvalue = array_map('ttrim', $newvalue);
            $newvalue = json_encode($newvalue);
        }
        $oldvalue = self::get($option_name);

        if ($oldvalue == $newvalue) {
            return true;
        }

        if (!self::optionExits($option_name)) {
            self::addOption($option_name, $newvalue);

            return true;
        }

        return $this->database->update('#__core_options', [
            'option_value' => $newvalue,
            ], [
            'option_name' => $option_name,
            'fksiteid'    => self::$_upsite,
            ]
        );
    }

    /**
     * check whether the option is exists or not
     * string $option_name.
     *
     * @return true on success or false on fail
     **/
    private function optionExits($option_name)
    {
        return $this->database->find('#__core_options', 'count', [
            'conditions' => ['option_name' => $option_name,'fksiteid' => self::$_getsite],
            ]
        );
    }

    /** Add new option to database
     * string $name
     * string or array or int $value
     * $autoload not implimented.
     *
     * @return true on success or mysql error on fail
     **/
    private function addOption($name, $value = '')
    {
        $value = self::maybeSerialize($value);

        $this->database->save('#__core_options', [
            'option_name'  => $name,
            'option_value' => $value,
            'fksiteid'     => self::$_upsite,
            ]
        );

        return true;
    }

    /**
     * Load all options into data array.
     *
     * @return array
     **/
    public function loadAllOptions($return = false)
    {
        $res = $this->database->find('#__core_options', 'all', [
            'conditions' => ['fksiteid' => self::$_getsite],
            'cache'      => 'daily',
            ]
        );

        $alloptions = [];
        foreach ($res as $data) {
            $value = $data['option_value'];
            if (self::isJson($value)) {
                $value = json_decode($value, true);
            }
            if ($return) {
                $alloptions[$data['option_name']] = $value;
            } else {
                self::set($data['option_name'], $value);
            }
        }

        return $alloptions;
    }

    /**
     * Removes option by name.
     *
     * @param string $name Option name to remove.
     *
     * @return bool True, if succeed. False, if failure.
     */
    public function deleteOption($name)
    {
        $this->database->delete('#__core_options', [
            'option_name' => $name,
            'fksiteid'    => self::$_getsite,
            ]
        );

        return true;
    }

    /**
     * Serialize data, if needed.
     *
     * @param mixed $data Data that might be serialized.
     *
     * @return mixed A scalar data
     */
    private function maybeSerialize($data)
    {
        if (is_array($data) || is_object($data)) {
            return json_encode($data);
        }

        return $data;
    }

    public function isJson($var)
    {
        $check = @json_decode($var);

        return ($check == null || $check == false) ? false : true;
    }
}
