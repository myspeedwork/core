<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class User extends Di
{
    private $data = [];

    protected $table = '#__user_options';

    /**
     * Get option from array of data.
     *
     * @param string $key
     *
     * @return string|int if exists or NULL if not
     **/
    public function get($key, $key1 = '')
    {
        return ($key1) ? $this->data[$key][$key1] :  $this->data[$key];
    }

    /**
     * Store option and it's value in data array.
     *
     * @param string $key, mixed $value
     **/
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Check whether option key exists or not in data array.
     *
     * @param string $key
     *
     * @return bool
     **/
    public function has($key, $key1 = '')
    {
        return ($key1) ? isset($this->data[$key][$key1]) :  isset($this->data[$key]);
    }

    /**
     * Retrive option from database.
     *
     * @Params string $option_name, boole $default
     *
     * @Return string option_value
     **/
    public function getOption($option_name)
    {
        $data = $this->database->find($this->table, 'first', [
            'conditions' => ['option_name' => $option_name],
            'fields'     => ['option_value'],
            ]
        );

        $value = $data['option_value'];
        // if the value is json encoded we need to decode that
        if ($this->isSerialized($value)) {
            $value = json_decode($value, true);
        }

        return $value;
    }

    /** update option if already exists or create new option of not
     * @params string $option_name | mixed $newvalue
     *
     * @return true on success or false on fail
     **/
    public function updateOption($option_name, $value, $status = 1)
    {
        $oldvalue = $this->get($option_name);

        if ($oldvalue == $value) {
            return true;
        }

        if (!$this->optionExits($option_name)) {
            return $this->addOption($option_name, $value, $status);
        }
        $value = $this->mayBeSerialize($value);

        return $this->database->update($this->table,
            ['option_value' => $value, 'modified' => time(), 'status' => $status],
            ['option_name'  => $option_name]
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
        return $this->database->find($this->table, 'count', [
            'conditions' => ['option_name' => $option_name],
        ]);
    }

    /** Add new option to database
     * string $name
     * string or array or int $value
     * $autoload not implimented.
     *
     * @return true on success or mysql error on fail
     **/
    private function addOption($name, $value = '', $status = 1)
    {
        $value = $this->mayBeSerialize($value);

        $this->database->save($this->table, [
            'option_name'  => $name,
            'option_value' => $value,
            'status'       => $status,
            'modified'     => time(),
            'created'      => time(),
            ]
        );

        return true;
    }

    /**
     * Load all options into data array.
     *
     * @return array
     **/
    public function loadAllOptions($return = false, $conditions = [])
    {
        $res = $this->database->find($this->table, 'all', ['conditions' => $conditions]);

        $alloptions = [];
        foreach ($res as $data) {
            $value = $data['option_value'];
            if ($this->isSerialized($value)) {
                $value = json_decode($value, true);
            }

            if ($return) {
                $alloptions[$data['option_name']] = $value;
            } else {
                $this->set($data['option_name'], $value);
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
        return $this->database->delete($this->table, ['option_name' => $name]);
    }

    /**
     * Serialize data, if needed.
     *
     * @param mixed $data Data that might be serialized.
     *
     * @return mixed A scalar data
     */
    private function mayBeSerialize($data)
    {
        if (is_array($data) || is_object($data)) {
            array_walk_recursive($data, function ($value) {
                return trim($value);
            });

            return json_encode($data);
        }

        return $data;
    }

    public function isSerialized($var)
    {
        $check = json_decode($var);

        return ($check == null || $check == false) ? false : true;
    }
}
