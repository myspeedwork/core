<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stechno.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Speedwork\Core;

use Cake\Event\Event;
use Cake\Event\EventManagerTrait;
use Speedwork\Config\Configure;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Acl extends Di
{
    use EventManagerTrait;

    private $permissions = [];

    public function getLoginFields()
    {
        $fields = Configure::read('members.login_fields');
        if (empty($fields)) {
            $fields = ['username'];
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        return $fields;
    }

    private function getMatches($username)
    {
        $username = strtolower(trim($username));
        $fields   = $this->getLoginFields();

        $matches = [];
        foreach ($fields as $field) {
            $matches[$field] = $username;
        }

        return ['or' => $matches];
    }

    private function lookupCookies()
    {
        if (empty($_COOKIE[COOKIE_NAME]) || empty($_COOKIE[COOKIE_KEY])) {
            return false;
        }

        return true;
    }

    /*
        this functions checks if there are any existing session variables set at last login
        returns 0 if no sessions were found and 1 otherwise
    */
    private function lookupSessions()
    {
        if (!$this->session->has(COOKIE_NAME) || !$this->session->has(COOKIE_KEY)) {
            return false;
        }

        return true;
    }

    /*
        this function checks if the current user is logged in.
    */
    public function isUserLoggedIn()
    {
        if ($this->lookupCookies()) {
            if (!$this->checkIsUserLoggedIn($_COOKIE[COOKIE_NAME], $_COOKIE[COOKIE_KEY])) {
                $this->logout();

                return false;
            } else {
                if (!$this->lookupSessions()) {
                    $this->session->set(COOKIE_NAME, $_COOKIE[COOKIE_NAME]);
                    $this->session->set(COOKIE_KEY, $_COOKIE[COOKIE_KEY]);
                } elseif (strcmp($_COOKIE[COOKIE_NAME], $this->session->get(COOKIE_NAME))
                    || strcmp($_COOKIE[COOKIE_KEY], $this->session->get(COOKIE_KEY))) {
                    $this->logout();

                    return false;
                }

                return true;
            }
        }
        if ($this->lookupSessions()) {
            if (!$this->checkIsUserLoggedIn($this->session->get(COOKIE_NAME), $this->session->get(COOKIE_KEY))) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Check the user login.
     *
     * @param [type] $email    [description]
     * @param [type] $user_key [description]
     *
     * @return [type] [description]
     */
    private function checkIsUserLoggedIn($username, $user_key)
    {
        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $data = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            ]
        );

        if (empty($data['userid'])) {
            return false;
        }
        // check if passwords match
        if (strcmp($user_key, $data['password'])) {
            return false;
        }

        $this->sets('userid', $data['userid']);
        $this->sets('username', $username);
        $this->sets('user', $data);

        Registry::set('power', $data['power']);

        return true;
    }

    /**
     * [LogUserIn description].
     *
     * @param string $username Username or email address
     * @param string $password password
     * @param bool   $remember Remember the password and username
     * @param bool   $hash     Is password is alreay hashed
     */
    public function logUserIn($username, $password, $remember = false, $hash = true)
    {
        //call the Event
        $event = new Event('event.members.before.login', $this, [
            'username' => $username,
            'passowrd' => $password,
        ]);
        $this->eventManager()->dispatch($event);

        if ($event->isStopped()) {
            if (isset($event->result)) {
                return $event->result;
            }

            return false;
        };

        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }

        $key = ($hash) ?  unsalt($password, $row['password']) : $password;
        // check if passwords match
        if (strcmp($key, $row['password'])) {
            return false;
        }

        // if that user is inactive send status
        if ($row['status'] != 1) {
            return $row['status'];
        }

        $userid = $row['userid'];
        $this->sets('userid', $userid);
        Registry::set('power', $row['power']);

        // Check whether can allow to view index
        if (!$this->isAllowed()) {
            return false;
        }

        $this->session->set(COOKIE_NAME, $username);
        $this->session->set(COOKIE_KEY, $key);

        $this->sets('username', $username);
        $this->sets('user', $row);

        if ($remember) {
            setcookie(COOKIE_NAME, $username, time() + COOKIE_TIME, COOKIE_PATH);
            setcookie(COOKIE_KEY, $key, time() + COOKIE_TIME, COOKIE_PATH);
        }

        $this->database->update('#__users', [
            'last_signin' => time(),
            'ip'          => $this->server['REMOTE_ADDR'],
            ], ['userid' => $userid]
        );

        $row['plain_password'] = $password;
        //call the Event
        $event = new Event('event.members.after.login', $this, [
            'userid'         => $userid,
            'username'       => $username,
            'plain_password' => $password,
            'user'           => $row,
        ]);
        $this->eventManager()->dispatch($event);

        return true;
    }

    public function checkUserByLogin(&$data, $conditions = [])
    {
        $fields   = $this->getLoginFields();
        $patterns = Configure::read('members.login_filters');
        if (!is_array($patterns)) {
            $patterns = [];
        }

        foreach ($fields as $field) {
            if (empty($data[$field])) {
                return ['required', $field];
            }
            // match pattern
            if (isset($patterns[$field]) && is_array($patterns[$field])) {
                foreach ($patterns[$field] as $pattern) {
                    if (!preg_match($pattern, $data[$field])) {
                        return ['required', $field];
                    }
                }
            }
        }

        foreach ($fields as $field) {
            $row = $this->getUserBy($field, $data[$field], $conditions);
            if (!empty($row['userid'])) {
                return ['exist', $field];
            }
        }

        return true;
    }

    public function isValidUser($username, $password = null, $hash = true)
    {
        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }

        if ($password !== null) {
            $key = ($hash) ?  unsalt($password, $row['password']) : $password;

            // check if passwords match
            if (strcmp($key, $row['password'])) {
                return false;
            }
        }

        // if that user is inactive send status
        if ($row['status'] != 1) {
            return false;
        }

        return $row;
    }

    /*
     This function logs the current user out
    */
    public function logout()
    {
        //call the hooks
        $this->eventManager()->dispatch('event.members.before.logout');

        @setcookie(COOKIE_NAME, '', time() - COOKIE_TIME, COOKIE_PATH);
        @setcookie(COOKIE_KEY, '', time() - COOKIE_TIME, COOKIE_PATH);
        @setcookie(COOKIE_UID, '', time() - COOKIE_TIME, COOKIE_PATH);

        $this->session->clear();

        return true;
    }

    public function isValidPassword($password)
    {
        $row = $this->database->find('#__users', 'first', [
            'conditions' => ['userid' => $this->userid],
            'fields'     => ['password'],
        ]);

        $oldpass = unsalt($password, $row['password']);

        if (strcmp($row['password'], $oldpass)) {
            return false;
        }

        return true;

        $new_pass       = $this->generateUniqueId();
        $activation_key = $this->generateActivationKey();

        $new_md5 = salt($new_pass);

        $result = $this->database->update('#__users', [
            'password'       => $new_md5,
            'last_pw_change' => time(),
            'activation_key' => $activation_key,
            ], ['userid' => $row['userid']]
        );

        if (!$result) {
            return false;
        }

        return ['pass' => $new_pass, 'key' => $activation_key];
    }

    public function updatePassword($new_password, $userid = null)
    {
        $new_password = salt(trim($new_password));
        $userid       = ($userid === null) ? $this->userid : $userid;

        if (empty($userid)) {
            return false;
        }

        //call the Event
        $event = new Event('event.members.update.password', $this, [
            'userid'   => $userid,
            'passowrd' => $new_password,
        ]);
        $this->eventManager()->dispatch($event);

        return $this->database->update('#__users', [
            'password' => $new_password, 'last_pw_change' => time(),
            ], [
            'userid' => $userid,
            ]
        );
    }

    public function resetPassword($username)
    {
        if (!$username) {
            return false;
        }

        //find user exists in database
        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }

        $new_pass       = $this->generateUniqueId();
        $activation_key = $this->generateActivationKey();

        $new_md5 = salt($new_pass);

        $result = $this->database->update('#__users', [
            'password'       => $new_md5,
            'last_pw_change' => time(),
            'activation_key' => $activation_key,
            ], ['userid' => $row['userid']]
        );

        if (!$result) {
            return false;
        }

        return ['pass' => $new_pass, 'key' => $activation_key];
    }

    /**
     * Generates a random password drawn from the defined set of characters.
     *
     * @param int  $length        The length of password to generate
     * @param bool $special_chars Whether to include standard special characters
     *
     * @return string The random password
     **/
    public function generatePassword($length = 12, $special_chars = false)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }

        $password = '';
        for ($i = 0; $i < $length; ++$i) {
            $password .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }

        return $password;
    }

    /**
     * Checks whether the given username exists.
     *
     * @param string $username Username.
     *
     * @return null|int The user's ID on success, and null on failure.
     */
    public function usernameExists($username)
    {
        if ($user = $this->getUserBy('username', $username)) {
            return $user;
        } else {
            return;
        }
    }

    /**
     * Checks whether the given email exists.
     *
     * @param string $email Email.
     *
     * @return bool|int The user's ID on success, and false on failure.
     */
    public function emailExists($email)
    {
        if ($user = $this->getUserByEmail($email)) {
            return $user;
        }

        return false;
    }

    /**
     * Retrieve user info by login name.
     *
     * @param string $user_login User's username
     *
     * @return bool|object False on failure, User DB row object
     */
    public function getUserByLogin($username, $conditions = [])
    {
        $conditions[] = $this->getMatches($username);

        return $this->getUserBy('', $username, $conditions);
    }

    /**
     * Retrieve user info by email.
     *
     * @param string $email User's email address
     *
     * @return bool|object False on failure, User DB row object
     */
    public function getUserByEmail($email)
    {
        return $this->getUserBy('email', $email);
    }

    /**
     * Retrieve user info by a given field.
     *
     * @param string     $field The field to retrieve the user with.  id | slug | email | login
     * @param int|string $value A value for $field.  A user ID, slug, email address, or login name.
     *
     * @return bool|object False on failure, User DB row object
     */
    public function getUserBy($field, $value, $conditions = [])
    {
        switch ($field) {
            case 'id':
                $field = 'userid';
                break;
        }

        if ($field) {
            $conditions[] = [$field => $value];
        }

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }

        return $row;
    }

    /**
     * Retrieve user info by a given field.
     *
     * @param string     $field The field to retrieve the user with.  id | slug | email | login
     * @param int|string $value A value for $field.  A user ID, slug, email address, or login name.
     *
     * @return bool|object False on failure, User DB row object
     */
    public function getUserIdBy($field, $value, $conditions = [])
    {
        switch ($field) {
            case 'id':
                $field = 'userid';
                break;
        }

        if ($field) {
            $conditions[] = [$field => $value];
        }

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            'fields'     => ['userid'],
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }

        return $row['userid'];
    }

    public function generateUniqueId()
    {
        return substr(uniqid(rand(), true), 0, 10);
    }

    public function generateActivationKey($length = 9)
    {
        return $this->generatePassword($length);
    }

    /**
     * Get user Permissions.
     *
     * @param string|int $userid
     *
     * @return null|array group id's array on success, and null on failure
     **/
    public function userGroupPermissions($userid)
    {
        if (!$userid) {
            return [];
        }

        if (Configure::read('use_power')) {
            $power = $this->userGroups($userid);

            $rows = $this->database->find('#__user_groups', 'all', [
                'conditions' => ['groupid' => $power],
            ]);
        } else {
            $join   = [];
            $join[] = [
                'table'      => '#__user_groups',
                'alias'      => 'gp',
                'type'       => 'INNER',
                'conditions' => ['g.fkgroupid = gp.groupid'],
            ];

            $rows = $this->database->find('#__user_to_group', 'all', [
                'alias'      => 'g',
                'conditions' => ['g.fkuserid' => $userid],
                'joins'      => $join,
            ]);
        }

        if (count($rows) == 0) {
            return [];
        }

        $permission = [
            'include' => [],
            'exclude' => [],
        ];

        foreach ($rows as $row) {
            $permissions = json_decode($row['permissions'], true);

            if (is_array($permissions['include'])) {
                $permission['include'] = array_merge($permission['include'], $permissions['include']);
                if (is_array($permissions['exclude'])) {
                    $permission['exclude'] = array_merge($permission['exclude'], $permissions['exclude']);
                }
            } else {
                $permission['include'] = array_merge($permission['include'], explode('||', $row['permissions']));
            }
        }

        return $permission;
    }

    /**
     * Get user groups.
     *
     * @param mixed $userid
     *
     * @return string;
     **/
    public function userGroups($userid)
    {
        if (Configure::read('use_power')) {
            if ($userid == $this->userid) {
                $power = Registry::get('power');

                return [$power];
            }

            $row = $this->database->find('#__users', 'first', [
                'conditions' => ['userid' => $userid],
                'fields'     => ['power'],
                ]
            );

            return [$row['power']];
        }

        $rows = $this->database->find('#__user_to_group', 'all', [
            'conditions' => ['fkuserid' => $userid],
            ]
        );

        $groups = [];
        foreach ($rows as $row) {
            $groups[] = $row['groupid'];
        }

        return $groups;
    }

    /**
     * Get user groups.
     *
     * @param mixed $userid
     *
     * @return string;
     **/
    public function userPermissions($userid)
    {
        $row = $this->database->find('#__user_permissions', 'first', [
            'conditions' => ['fkuserid' => $userid],
            ]
        );

        $permissions = [];
        if ($row['permissions']) {
            $permissions = json_decode($row['permissions'], true);
        }

        return $permissions;
    }

    /**
     * Get Group Permissions.
     *
     * @param int $groupid
     *
     * @return null|array group id's array on success, and null on failure
     **/
    public function groupPermissions($groupid)
    {
        $rows = $this->database->find('#__user_groups', 'all', [
            'conditions' => ['groupid' => $groupid],
        ]);

        $permission = [];
        foreach ($rows as $row) {
            $permissions = json_decode($rows['permissions'], true);
            if (is_array($permissions['include'])) {
                $permission = array_merge($permission, $permissions['include']);
            } else {
                $permission = array_merge($permission, explode('||', $row['permissions']));
            }
        }

        return $permission;
    }

    public function getPermissions($userid = '')
    {
        // Every one get these permissions
        $permissions = [
            'errors:**',
            'admin_errors:**',
            'members:logout',
            'members:login',
            'members:auth',
            'members:endpoint',
            'members:resetpass:*',
            'members:pwreset:*',
            'members:activate:*',
            'admin_members:logout',
            'admin_members:login',
            'admin_members:auth',
            'admin_members:endpoint',
            'admin_members:resetpass:*',
            'admin_members:pwreset:*',
            'admin_members:activate:*',
        ];

        $default = Configure::read('public_permissions');
        if (!is_array($default)) {
            $default = explode('||', $default);
        }

        if (count($default) > 0) {
            $permissions = array_merge($permissions, $default);
        }

        $_permissions                 = [];
        $_permissions['global']       = $permissions;
        $_permissions['user_exclude'] = [];
        $_permissions['user_include'] = [];
        $_permissions['exclude']      = [];
        $_permissions['include']      = [];
        $_permissions['group']        = [];

        if (!empty($userid)) {
            //userpermissions
            $permissions = $this->userPermissions($userid);

            if ($permissions && is_array($permissions)) {
                if (is_array($permissions['exclude'])) {
                    $_permissions['user_exclude'] = $permissions['exclude'];
                }
                if (is_array($permissions['include'])) {
                    $_permissions['user_include'] = $permissions['include'];
                }
            }

            //group premissions
            $permissions = $this->userGroupPermissions($userid);
            if ($permissions && is_array($permissions)) {
                if (is_array($permissions['exclude'])) {
                    $_permissions['exclude'] = $permissions['exclude'];
                }
                if (is_array($permissions['include'])) {
                    $_permissions['group'] = $permissions['include'];
                }
            }
        }

        $include = Configure::read('permissions.include');
        $exclude = Configure::read('permissions.exclude');

        if (is_array($include)) {
            $_permissions['include'] = array_merge($_permissions['include'], $include);
        }

        if (is_array($exclude)) {
            $_permissions['exclude'] = array_merge($_permissions['exclude'], $exclude);
        }

        $include = Configure::read('permissions.user_include');
        $exclude = Configure::read('permissions.user_exclude');

        if (is_array($include)) {
            $_permissions['user_include'] = array_merge($_permissions['user_include'], $include);
        }

        if (is_array($exclude)) {
            $_permissions['user_include'] = array_merge($_permissions['user_include'], $exclude);
        }

        $_permissions['group'] = array_merge($_permissions['global'], $_permissions['group']);

        Configure::write('permissions', $_permissions);

        unset($permissions);

        return $_permissions;
    }

    /**
     * Checking user is permitted to that component or view.
     *
     * @params string $component,$view,$task
     * returns true or false
     **/
    public function isAllowed($component = 'home', $view = '', $task = '', $userid = null)
    {
        $userid = ($userid) ? $userid : $this->userid;

        if (empty($component)) {
            $component = 'home';
        }

        if (!isset($this->permissions[$userid])) {
            $this->permissions[$userid] = $this->getPermissions($userid);
        }

        $_permissions = $this->permissions[$userid];

        $prefix   = '';
        $is_admin = Registry::get('is_admin');

        if ($is_admin) {
            $prefix = 'admin_';
        }

        $component = $prefix.$component;
        $return    = false;

        // get group permissions
        $permissions = $_permissions['group'];
        if ($permissions && is_array($permissions)) {
            $return = $this->isPermitted($component, $view, $task, $permissions);
        }

        // get include permissions
        $permissions = $_permissions['include'];
        if (!$return && $permissions && is_array($permissions)) {
            if ($this->isPermitted($component, $view, $task, $permissions)) {
                $return = true;
            }
        }

        // get exclude permissions
        $permissions = $_permissions['exclude'];
        if ($return && $permissions && is_array($permissions)) {
            if ($this->isPermitted($component, $view, $task, $permissions)) {
                $return = false;
            }
        }

        // get user exclude permissions
        $permissions = $_permissions['user_exclude'];
        if ($return && $permissions && is_array($permissions)) {
            if ($this->isPermitted($component, $view, $task, $permissions)) {
                $return = false;
            }
        }

        // get user include permissions
        $permissions = $_permissions['user_include'];
        if (!$return && $permissions && is_array($permissions)) {
            if ($this->isPermitted($component, $view, $task, $permissions)) {
                $return = true;
            }
        }

        $permissions = $_permissions['mixed'];
        if ($permissions && is_array($permissions)) {
            $perms = [];
            foreach ($permissions as $value) {
                if (is_array($value)) {
                    $perms = array_merge($perms, $value);
                } else {
                    $perms[] = $value;
                }
            }

            $returns = $this->isPermitted($component, $view, $task, $perms, true);
            $return  = (is_bool($returns)) ? $returns : $return;
        }

        unset($permissions, $perms);

        return $return;
    }

    public function isPermitted($component = 'home', $view = '', $task = '', &$permissions = [], $mixed = false)
    {
        $component = str_replace('com_', '', $component);

        $return = false;
        foreach ($permissions as $permission) {
            $permission = trim($permission);
            $return     = substr($permission, 0, 1) == '-' ? false : true;
            $permission = str_replace(['-','+'], '', $permission);

            if ($permission == '*' &&  $component != 'home' && $component != 'admin_home') {
                return $return; // Super Admin Bypass found
            }

            if ($permission == $component.':**') {
                return $return; // Component Wide Bypass with tasks
            }

            if (!$task && $permission == $component.':*') {
                return $return; // Component Wide Bypass without tasks found
            }

            if (!$view && !$task && $permission == $component.':') {
                return $return; // Component Wide Bypass found
            }

            if (!$task && $permission == $component.':'.$view) {
                return $return; // Specific view without any task like view
            }

            if ($task && $permission == $component.':'.$view.':'.$task) {
                return $return; // Specific view with perticular task found
            }

            if ($task && $permission == $component.':*:'.$task) {
                return $return; // Any view with perticular task found
            }

            if ($task && $permission == $component.':*:*') {
                return $return; // Any view and task
            }

            if ($permission == $component.':'.$view.':*') {
                return $return; // Specific view with all tasks permission found
            }
        }

        if (!$mixed) {
            return false;
        }
    }
}
