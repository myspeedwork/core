<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Acl extends Di
{
    private $permissions = [];

    public function getLoginFields()
    {
        $fields = $this->config('auth.account.login_fields');
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
        if (empty($this->cookie[COOKIE_NAME]) || empty($this->cookie[COOKIE_KEY])) {
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
        if (!$this->get('session')->has(COOKIE_NAME) || !$this->get('session')->has(COOKIE_KEY)) {
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
            if (!$this->checkIsUserLoggedIn($this->cookie[COOKIE_NAME], $this->cookie[COOKIE_KEY])) {
                $this->logout();

                return false;
            } else {
                if (!$this->lookupSessions()) {
                    $this->get('session')->set(COOKIE_NAME, $this->cookie[COOKIE_NAME]);
                    $this->get('session')->set(COOKIE_KEY, $this->cookie[COOKIE_KEY]);
                } elseif (strcmp($this->cookie[COOKIE_NAME], $this->get('session')->get(COOKIE_NAME))
                    || strcmp($this->cookie[COOKIE_KEY], $this->get('session')->get(COOKIE_KEY))) {
                    $this->logout();

                    return false;
                }

                return true;
            }
        }
        if ($this->lookupSessions()) {
            if (!$this->checkIsUserLoggedIn($this->get('session')->get(COOKIE_NAME), $this->get('session')->get(COOKIE_KEY))) {
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

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }
        // check if passwords match
        if (strcmp($user_key, $row['password'])) {
            return false;
        }

        $this->sets('userid', $row['userid']);
        $this->sets('username', $username);
        $this->sets('user', $row);

        $this->set('power', $row['power']);

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
        $event = $this->fire('members.before.login', [
            'username' => $username,
            'password' => $password,
        ]);

        if ($event->isPropagationStopped()) {
            if (isset($event->results)) {
                return $event->results;
            }

            return false;
        }

        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
        ]);

        $eventName = 'members.login.failed';
        $event     = $this->event($eventName, [
            'username' => $username,
            'passowrd' => $password,
        ]);

        if (empty($row['userid'])) {
            $this->eventManager()->dispatch($eventName, $event);

            return false;
        }

        $key = ($hash) ? unsalt($password, $row['password']) : $password;
        // check if passwords match
        if (strcmp($key, $row['password'])) {
            $this->eventManager()->dispatch($eventName, $event);

            return false;
        }

        // if that user is inactive send status
        if ($row['status'] != 1) {
            $this->eventManager()->dispatch($eventName, $event);

            return $row['status'];
        }

        $userid = $row['userid'];
        $this->sets('userid', $userid);
        $this->set('power', $row['power']);

        // Check whether can allow to view index
        if (!$this->isAllowed()) {
            $this->eventManager()->dispatch($eventName, $event);

            return false;
        }

        $this->get('session')->set(COOKIE_NAME, $username);
        $this->get('session')->set(COOKIE_KEY, $key);

        $this->sets('username', $username);
        $this->sets('user', $row);

        if ($remember) {
            setcookie(COOKIE_NAME, $username, time() + COOKIE_TIME, COOKIE_PATH);
            setcookie(COOKIE_KEY, $key, time() + COOKIE_TIME, COOKIE_PATH);
        }

        $this->database->update('#__users', [
            'last_signin' => time(),
            'ip'          => env('REMOTE_ADDR'),
            ], ['userid' => $userid]
        );

        $row['plain_password'] = $password;
        //call the Event
        $this->fire('members.after.login', [
            'userid'         => $userid,
            'username'       => $username,
            'plain_password' => $password,
            'user'           => $row,
        ]);

        return true;
    }

    public function checkUserByLogin($data = [], $conditions = [], $exists = false)
    {
        $fields = $this->getLoginFields();
        if ($exists) {
            $newFields = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $fields)) {
                    $newFields[] = $key;
                }
            }
            $fields = $newFields;
        }

        $patterns = $this->config('auth.account.patterns');
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
            $key = ($hash) ? unsalt($password, $row['password']) : $password;

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
        $this->dispatch('members.before.logout');
        $this->get('session')->clear();

        setcookie(COOKIE_NAME, '', time() - COOKIE_TIME, COOKIE_PATH);
        setcookie(COOKIE_KEY, '', time() - COOKIE_TIME, COOKIE_PATH);
        setcookie(COOKIE_UID, '', time() - COOKIE_TIME, COOKIE_PATH);

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

        $new_pass       = $this->generatePassword();
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
        $this->fire('members.update.password', [
            'userid'   => $userid,
            'passowrd' => $new_password,
        ]);

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

        $new_pass       = $this->generatePassword();
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

        return [
            'pass' => $new_pass,
            'key'  => $activation_key,
        ];
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
     * @param string $username Username
     *
     * @return null|int The user's ID on success, and null on failure
     */
    public function isUsernameExists($username)
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
     * @param string $email Email
     *
     * @return bool|int The user's ID on success, and false on failure
     */
    public function isEmailExists($email)
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
     * @param int|string $value A value for $field.  A user ID, slug, email address, or login name
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
     * @param int|string $value A value for $field.  A user ID, slug, email address, or login name
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

    public function getUniqId()
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

        if ($this->config('auth.power')) {
            $power = $this->getUserGroups($userid);

            $rows = $this->database->find('#__user_groups', 'all', [
                'conditions' => ['groupid' => $power],
            ]);
        } else {
            $joins   = [];
            $joins[] = [
                'table'      => '#__user_groups',
                'alias'      => 'gp',
                'type'       => 'INNER',
                'conditions' => ['g.group_id = gp.groupid'],
            ];

            $rows = $this->database->find('#__user_to_group', 'all', [
                'alias'      => 'g',
                'conditions' => ['g.user_id' => $userid],
                'joins'      => $joins,
            ]);
        }

        $permissions = ['include' => [], 'exclude' => []];

        foreach ($rows as $row) {
            $permissions = array_merge($permissions, json_decode($row['permissions'], true));
        }

        return $permissions;
    }

    /**
     * Get user groups.
     *
     * @param mixed $userid
     *
     * @return string;
     **/
    public function getUserGroups($userid)
    {
        if ($this->config('auth.power')) {
            if ($userid == $this->get('userid')) {
                return [$this->get('power')];
            }

            $row = $this->database->find('#__users', 'first', [
                'conditions' => ['userid' => $userid],
                'fields'     => ['power'],
                ]
            );

            return [$row['power']];
        }

        return $this->database->find('#__user_to_group', 'list', [
            'conditions' => ['user_id' => $userid],
            'fields'     => ['group_id'],
            ]
        );
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
            'conditions' => ['user_id' => $userid],
            ]
        );

        return array_merge(['include' => [], 'exclude' => []], (array) json_decode($row['permissions'], true));
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

        $permissions = ['include' => [], 'exclude' => []];
        foreach ($rows as $row) {
            $permissions = array_merge($permissions, json_decode($row['permissions'], true));
        }

        return $permissions;
    }

    public function getPermissions($userid = null)
    {
        // Every one get these permissions
        $public = [
            'errors:**',
            'members:logout',
            'members:login',
            'members:auth',
            'members:endpoint',
            'members:resetpass:*',
            'members:pwreset:*',
            'members:activate:*',
        ];

        $firewall = config('auth.firewall');
        foreach ($public as &$value) {
            $value = $firewall.$value;
        }

        $permissions = config('auth.permissions');

        foreach (['public', 'user', 'group'] as $value) {
            if (is_array($permissions[$value])) {
                if (!is_array($permissions[$value]['exclude'])) {
                    $permissions[$value]['exclude'] = [];
                }

                if (!is_array($permissions[$value]['include'])) {
                    $permissions[$value]['include'] = [];
                }
            } else {
                $permissions[$value] = ['include' => [], 'exclude' => []];
            }
        }

        $permissions['public']['include'] = array_merge($permissions['public']['include'], $public);

        if ($userid) {
            $permissions['user']  = array_merge($permissions['user'], $this->userPermissions($userid));
            $permissions['group'] = array_merge($permissions['group'], $this->userGroupPermissions($userid));
        }

        config(['auth.permissions' => $permissions]);

        return $permissions;
    }

    /**
     * Checking user is permitted to that component or view.
     *
     * @params string $component,$view,$task
     * returns true or false
     **/
    public function isAllowed($component = 'home', $view = '', $task = '', $userid = null)
    {
        $userid = ($userid) ? $userid : $this->get('userid');

        if (empty($component)) {
            $component = 'home';
        }

        if (!isset($this->permissions[$userid])) {
            $this->permissions[$userid] = $this->getPermissions($userid);
        }

        $component = config('auth.firewall').$component;

        $permissions = $this->permissions[$userid];

        //Exclude has more priority than include
        $perms = $permissions['user'];
        if ($perms && is_array($perms)) {
            if (is_array($perms['exclude'])) {
                if ($this->isPermitted($component, $view, $task, $perms['exclude'])) {
                    return false;
                }
            }

            if (is_array($perms['include'])) {
                if ($this->isPermitted($component, $view, $task, $perms['include'])) {
                    return true;
                }
            }
        }

        $perms = $permissions['group'];
        if ($perms && is_array($perms)) {
            if (is_array($perms['exclude'])) {
                if ($this->isPermitted($component, $view, $task, $perms['exclude'])) {
                    return false;
                }
            }

            if (is_array($perms['include'])) {
                if ($this->isPermitted($component, $view, $task, $perms['include'])) {
                    return true;
                }
            }
        }

        $perms = $permissions['public'];
        if ($perms && is_array($perms)) {
            if (is_array($perms['exclude'])) {
                if ($this->isPermitted($component, $view, $task, $perms['exclude'])) {
                    return false;
                }
            }

            if (is_array($perms['include'])) {
                if ($this->isPermitted($component, $view, $task, $perms['include'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isPermitted($component = 'home', $view = '', $task = '', $permissions = [])
    {
        $component = str_replace('com_', '', $component);

        foreach ($permissions as $permission) {
            $permission = trim($permission);

            if ($permission == '*' && $component != 'home' && $component != config('auth.firewall').'_home') {
                return true; // Super Admin Bypass found
            }

            if ($permission == $component) {
                return true; // Component Wide Bypass with tasks
            }

            if ($permission == $component.':**') {
                return true; // Component Wide Bypass with tasks
            }

            if (!$task && $permission == $component.':*') {
                return true; // Component Wide Bypass without tasks found
            }

            if (!$view && !$task && $permission == $component.':') {
                return true; // Component Wide Bypass found
            }

            if (!$task && $permission == $component.':'.$view) {
                return true; // Specific view without any task like view
            }

            if ($task && $permission == $component.':'.$view.':'.$task) {
                return true; // Specific view with perticular task found
            }

            if ($task && $permission == $component.':*:'.$task) {
                return true; // Any view with perticular task found
            }

            if ($task && $permission == $component.':*:*') {
                return true; // Any view and task
            }

            if ($permission == $component.':'.$view.':*') {
                return true; // Specific view with all tasks permission found
            }
        }

        return false;
    }
}
