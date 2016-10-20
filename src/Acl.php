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

use Speedwork\Core\Traits\HttpTrait;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Acl extends Di
{
    use HttpTrait;

    protected $grants = [];

    /**
     * Get column names used for login.
     *
     * @return array
     */
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

    /**
     * Populate the database condition based login columns.
     *
     * @param string $username
     *
     * @return array
     */
    protected function getMatches($username)
    {
        $username = strtolower(trim($username));
        $fields   = $this->getLoginFields();

        $matches = [];
        foreach ($fields as $field) {
            $matches[$field] = $username;
        }

        return ['or' => $matches];
    }

    /**
     * Check cookies avaliable from for this user.
     *
     * @return bool [description]
     */
    protected function hasCookies()
    {
        if (empty($this->cookie(COOKIE_NAME)) || empty($this->cookie(COOKIE_KEY))) {
            return false;
        }

        return true;
    }

    /**
     * Check any exising sessions avialable.
     *
     * @return boolen [description]
     */
    protected function hasSessions()
    {
        if (!$this->get('session')->has(COOKIE_NAME) || !$this->get('session')->has(COOKIE_KEY)) {
            return false;
        }

        return true;
    }

    /**
     * Check the current user is logged in.
     *
     * @return bool
     */
    public function isUserLoggedIn()
    {
        if ($this->hasCookies()) {
            if (!$this->checkIsUserLoggedIn(
                $this->cookie(COOKIE_NAME),
                $this->cookie(COOKIE_KEY)
            )) {
                $this->logout();

                return false;
            } else {
                if (!$this->hasSessions()) {
                    $this->setSession(COOKIE_NAME, $this->cookie(COOKIE_NAME));
                    $this->setSession(COOKIE_KEY, $this->cookie(COOKIE_KEY));
                } elseif (strcmp($this->cookie(COOKIE_NAME), $this->getSession(COOKIE_NAME))
                    || strcmp($this->cookie(COOKIE_KEY), $this->getSession(COOKIE_KEY))) {
                    $this->logout();

                    return false;
                }

                return true;
            }
        }

        if ($this->hasSessions()) {
            if (!$this->checkIsUserLoggedIn(
                $this->getSession(COOKIE_NAME),
                $this->getSession(COOKIE_KEY)
            )) {
                $this->logout();

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Check whether logged in user is valid or not.
     *
     * @param string $username
     * @param string $user_key
     *
     * @return bool
     */
    protected function checkIsUserLoggedIn($username, $user_key)
    {
        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
        ]);

        if (empty($row['userid'])) {
            return false;
        }

        // check if passwords match
        if (strcmp($user_key, $row['password'])) {
            return false;
        }

        if ($row['status'] != 1) {
            return false;
        }

        $this->sets('userid', $row['userid']);
        $this->sets('username', $username);
        $this->sets('user', $row);

        $this->set('role_id', $row['role_id']);

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
            $this->dispatch($eventName, $event);

            return false;
        }

        $key = ($hash) ? unsalt($password, $row['password']) : $password;
        // check if passwords match
        if (strcmp($key, $row['password'])) {
            $this->dispatch($eventName, $event);

            return false;
        }

        // if that user is inactive send status
        if ($row['status'] != 1) {
            $this->dispatch($eventName, $event);

            return $row['status'];
        }

        $userid = $row['userid'];
        $this->sets('userid', $userid);
        $this->set('role_id', $row['role_id']);

        // Check whether can allow to view index
        if (!$this->isGranted()) {
            $this->dispatch($eventName, $event);

            return false;
        }

        $this->setSession(COOKIE_NAME, $username);
        $this->setSession(COOKIE_KEY, $key);

        $this->sets('username', $username);
        $this->sets('user', $row);

        if ($remember) {
            $this->setCookie(COOKIE_NAME, $username, time() + COOKIE_TIME);
            $this->setCookie(COOKIE_KEY, $key, time() + COOKIE_TIME);
        }

        $this->database->update('#__users', [
            'last_signin' => time(),
            'ip'          => ip(),
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
            unset($value);
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

    /**
     * Verify is valid user based on username and password.
     *
     * @param string $username
     * @param string $password
     * @param bool   $hash     Is hashed password?
     *
     * @return bool
     */
    public function isValidUser($username, $password = null, $hash = true)
    {
        $conditions   = [];
        $conditions[] = $this->getMatches($username);

        $row = $this->database->find('#__users', 'first', [
            'conditions' => $conditions,
        ]);

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

    /**
     * Logout the current logged in user.
     *
     * @return bool
     */
    public function logout()
    {
        $this->dispatch('members.before.logout');
        $this->get('session')->clear();

        $this->setCookie(COOKIE_NAME, '', time() - COOKIE_TIME);
        $this->setCookie(COOKIE_KEY, '', time() - COOKIE_TIME);

        return true;
    }

    /**
     * Determine provided password is valid.
     *
     * @param string $password
     *
     * @return bool
     */
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
    }

    /**
     * Update the user password with new one.
     *
     * @param string $new_password
     * @param string $userid
     *
     * @return boolen
     */
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
            'password'       => $new_password,
            'last_pw_change' => time(), ],
            ['userid' => $userid]
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
        ]);

        if (empty($row['userid'])) {
            return false;
        }

        $new_pass       = $this->generateRandomKey();
        $activation_key = $this->generateRandomKey();

        $new_md5 = salt($new_pass);

        $result = $this->database->update('#__users', [
            'password'       => $new_md5,
            'last_pw_change' => time(),
            'activation_key' => $activation_key,
            ], ['userid'     => $row['userid'],
        ]);

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
    public function generateRandomKey($length = 12, $special_chars = false)
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
        ]);

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
        ]);

        if (empty($row['userid'])) {
            return false;
        }

        return $row['userid'];
    }

    public function getUniqId()
    {
        return substr(uniqid(rand(), true), 0, 10);
    }

    /**
     * Get user Permissions.
     *
     * @param string|int $userid
     *
     * @return null|array role id's array on success, and null on failure
     **/
    public function getUserRoleGrants($userid)
    {
        if (!$userid) {
            return [];
        }

        if ($this->config('auth.role_id')) {
            $roles = $this->getUserRoles($userid);
        } else {
            $roles = $this->getUserRoles($userid);
        }

        $rows = $this->database->find('#__user_roles', 'all', [
            'conditions' => ['role_id' => $roles],
            'fields'     => ['grants'],
        ]);

        $grants = ['include' => [], 'exclude' => []];

        foreach ($rows as $row) {
            $grants = array_merge($grants, json_decode($row['grants'], true));
        }

        return $grants;
    }

    /**
     * Get user roles.
     *
     * @param mixed $userid
     *
     * @return string;
     **/
    public function getUserRoles($userid)
    {
        if ($this->config('auth.role_id')) {
            if ($userid == $this->get('userid')) {
                return [$this->get('role_id')];
            }

            $row = $this->database->find('#__users', 'first', [
                'conditions' => ['userid' => $userid],
                'fields'     => ['role_id'],
            ]);

            return [$row['role_id']];
        }

        return $this->database->find('#__user_to_role', 'list', [
            'conditions' => ['user_id' => $userid],
            'fields'     => ['role_id'],
        ]);
    }

    /**
     * Get user roles.
     *
     * @param mixed $userid
     *
     * @return string;
     **/
    public function getUserGrants($userid)
    {
        $row = $this->database->find('#__user_grants', 'first', [
            'conditions' => ['user_id' => $userid],
        ]);

        return array_merge(['include' => [], 'exclude' => []], (array) json_decode($row['grants'], true));
    }

    /**
     * Get the granted permissions for give user.
     *
     * @param string $userid userid for which grants required
     *
     * @return array All Allowed grants
     */
    public function getGrants($userid = null)
    {
        // Every one get these grants
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

        $firewall = $this->config('auth.firewall');
        foreach ($public as &$value) {
            $value = $firewall.$value;
        }

        $grants = $this->config('auth.grants');

        foreach (['public', 'user', 'role'] as $value) {
            if (is_array($grants[$value])) {
                if (!is_array($grants[$value]['exclude'])) {
                    $grants[$value]['exclude'] = [];
                }

                if (!is_array($grants[$value]['include'])) {
                    $grants[$value]['include'] = [];
                }
            } else {
                $grants[$value] = ['include' => [], 'exclude' => []];
            }
        }

        $grants['public']['include'] = array_merge($grants['public']['include'], $public);

        if ($userid) {
            $grants['user'] = array_merge($grants['user'], $this->getUserGrants($userid));
            $grants['role'] = array_merge($grants['role'], $this->getUserRoleGrants($userid));
        }

        return $grants;
    }

    /**
     * Checking user is permitted to that component or view.
     *
     * @param string $component,$view,$task
     *
     * @return true or false
     **/
    public function isGranted($rule = 'home', $userid = null)
    {
        $userid = ($userid) ? $userid : $this->get('userid');

        if (!isset($this->grants[$userid])) {
            $this->grants[$userid] = $this->getGrants($userid);
        }

        $grants = $this->grants[$userid];

        //Exclude has more priority than include
        $perms = $grants['user'];
        if ($perms && is_array($perms)) {
            if (is_array($perms['exclude'])) {
                if ($this->isGrantMatched($rule, $perms['exclude'])) {
                    return false;
                }
            }

            if (is_array($perms['include'])) {
                if ($this->isGrantMatched($rule, $perms['include'])) {
                    return true;
                }
            }
        }

        $perms = $grants['role'];
        if ($perms && is_array($perms)) {
            if (is_array($perms['exclude'])) {
                if ($this->isGrantMatched($rule, $perms['exclude'])) {
                    return false;
                }
            }

            if (is_array($perms['include'])) {
                if ($this->isGrantMatched($rule, $perms['include'])) {
                    return true;
                }
            }
        }

        $perms = $grants['public'];
        if ($perms && is_array($perms)) {
            if (is_array($perms['exclude'])) {
                if ($this->isGrantMatched($rule, $perms['exclude'])) {
                    return false;
                }
            }

            if (is_array($perms['include'])) {
                if ($this->isGrantMatched($rule, $perms['include'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isGrantMatched($rule = 'home', $grants = [])
    {
        $rule = trim($rule, '.');
        $rule = $rule ?: 'home';

        list($component, $view, $task) = explode('.', $rule);

        $firewall = $this->config('auth.firewall').'_home';

        foreach ($grants as $permission) {
            $permission = trim($permission);

            if ($permission == '*' && $component != 'home' && $component != $firewall) {
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
