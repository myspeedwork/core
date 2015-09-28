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

use Cake\Cache\Cache;
use Speedwork\Util\RestUtils;
use Speedwork\Util\Xml;

class RestApi extends Api
{
    private $cache    = false;
    private $useronly = false;
    private $public   = [];
    private $request  = [];

    public function setCache($cache = false)
    {
        $this->cache = $cache;

        return $this;
    }

    public function setRequest($request)
    {
        if (empty($request['api_key'])) {
            $request['api_key'] = $this->server['api_key'];
        }

        if (empty($request['format'])) {
            $request['format'] = $request['output'];
        }

        $request['format'] = strtolower($request['format']);

        // Method is combination of option and view separated by . (dot)
        $method = explode('.', strtolower($request['method']));

        //format speedwork specification for component/view
        $request['option'] = $method[0];
        $request['view']   = $method[1];

        $this->request = $request;

        return $this;
    }

    public function setPublicMethods($public = [])
    {
        if ($public === false) {
            $this->public = false;

            return $this;
        }

        if (is_array($public)) {
            $this->public = array_merge($this->public, $public);
        } else {
            $this->public[] = $public;
        }

        return $this;
    }

    public function setUserOnly($value = false)
    {
        $this->useronly = $value;

        return $this;
    }

    /**
     * Functions to process given method.
     *
     * @param array &$request    Element array
     * @param bool  $authenicate Flag to specify force authentication
     * @param array $useronly    Username validation only
     *
     * @return none
     **/
    public function processMethod($authenicate = true)
    {
        $data   = [];
        $status = [];
        $sig    = true;

        //if method found
        if ($this->request['option']) {
            //validate Request
            if ($authenicate) {
                $sig = $this->authenticate(false);
            }

            if ($sig === true) {
                return $this->process();
            } else {
                $status = $sig;
            }
        } else {
            $status['A401B'] = 'Method Not Found';
        }

        return $this->outputFormat($status, $data);
    }

    private function outputFormat($status = [], &$output = [])
    {
        // there is no status messages other than 200
        if (!empty($status)) {
            $key               = @array_keys($status);
            $output['status']  = $key[0];
            $output['message'] = $status[$key[0]];
        }

        if (empty($output['status'])) {
            $output['status'] = 'OK';
        }

        unset($status);

        return $this->output($output);
    }

    protected function process()
    {
        $app_api = ($this->request['app']) ? true : false;

        if ($app_api) {
            return $this->processApp($request);
        }

        return $this->processApi($request);
    }

    public function processApi()
    {
        $option = $this->request['option'];
        $view   = $this->request['view'];

        $data   = [];
        $status = [];

        $controller = $this->get('resolver')->requestApi($option);
        if (is_array($controller)) {
            return $this->outputFormat($controller, $data);
        }

        $method = ($view) ? $view : 'index';

        if (method_exists($controller, $method)) {
            $controller->setData($this->request);

            $data = $controller->$method();
        } else {
            $status['A401A'] = 'Method Not Implemented';
        }

        return $this->outputFormat($status, $data);
    }

    protected function processApp()
    {
        $option = $this->request['option'];
        $view   = $this->request['view'];

        $status = [];
        $data   = [];

        try {
            $controller = $this->get('resolver')->requestController($option);
        } catch (\Exception $e) {
            $status['A400'] = 'Api Not Implemented';

            return $this->outputFormat($status, $data);
        }

        $method = ($view) ? $view : 'index';

        if (!method_exists($controller, $method)) {
            $status['A401A'] = 'Method Not Implemented';

            return $this->outputFormat($status, $data);
        }

        $controller->setData($this->request);

        $response = $controller->$method();
        $api      = $response['api'];
        unset($response['api']);

        if ($api === false) {
            $status['A401A'] = 'Method Not Allowed';

            return $this->outputFormat($status, $data);
        }

        $return            = [];
        $return['status']  = (empty($response['status'])) ? 'OK' : $response['status'];
        $return['message'] = (empty($response['message'])) ? 'OK' : $response['message'];

        if ($api) {
            if (is_callable($api)) {
                $return['data'] = $api($response);
            } elseif (is_array($api)) {
                foreach ($api as $key => $value) {
                    $value = explode('.', $value);
                    $key   = (!is_numeric($key)) ? $key : end($value);

                    $data = $response;
                    foreach ($value as $val) {
                        $data = $data[$val];
                    }
                    $return['data'][$key] = $data;
                }
            }
        } else {
            unset($response['status'], $response['message']);

            if (isset($response['rows'])) {
                $data = $response['rows']['data'];
                unset($response['rows']['data']);
                $pagination                   = $response['rows'];
                $return['data'][$option.'s']  = $data;
                $return['data']['pagination'] = $pagination;
            } else {
                $return['data'] = $response;
            }
        }

        unset($response, $pagination, $data);

        return $this->output($return);
    }

    /**
     * Formats the data according to specified format.
     *
     * @param string $outputType Output type
     * @param array  &$output    Response data
     * @param string $name       Element name
     * @param string $root       RootElement name
     * @param string $clubnodes  Flag to club child nodes in single parent
     *
     * @return mixed
     */
    public function output(&$output)
    {
        $outputType = $this->request['format'];
        $name       = ($this->request['view']) ? $this->request['view'] : $this->request['option'];

        $this->setRequest([]);

        switch ($outputType) {
            case 'none':
                return;
            break;

            case 'array':
                return $output;
            break;

            case 'xml':
                $output = Xml::fromArray($output, 'api', $name);
                RestUtils::sendResponse($output, 'application/xml');
                break;
            case 'php':
                RestUtils::sendResponse(serialize($output));
                break;
            case 'jsonp':
                if ($request['callback']) {
                    $output = json_encode($output);
                    header('Content-Type: text/javascript; charset=utf8');
                    $callback = $request['callback'];
                    echo $callback.'('.$output.');';
                } else {
                    RestUtils::sendResponse(json_encode($output), 'application/json', $name);
                }
                break;
            case 'json':
            default:
                RestUtils::sendResponse(json_encode($output), 'application/json', $name);
                break;
        }
    }

    /**
     * [authenticate description].
     *
     * @param [type] $request  [description]
     * @param bool   $sig      [description]
     * @param bool   $useronly [description]
     *
     * @return [type] [description]
     */
    protected function authenticate($sig = true)
    {
        $public = [];
        if (is_array($this->public)) {
            $public = array_merge($this->public, [
                'members.register',
                'members.login',
                'members.signin',
                'members.activate',
                'members.resetpass',
                'members.activate',
                'members.pwreset',
            ]);
        }

        if (in_array($this->request['method'], $public)) {
            return true;
        }

        $api_key = $this->request['api_key'];
        if (!$api_key) {
            return ['A402' => 'Api Key not found'];
        }

        if ($this->cache) {
            $cache_key = 'api_cache_'.$api_key;

            $status = Cache::remember($cache_key, function () use ($sig) {
                return $this->validate($sig);
            }, 'api');
        } else {
            $status = $this->validate($sig);
        }

        if ($status['status'] == 'OK') {
            if ($sig) {
                $signature = $this->generateSignature($status['api_secret']);

                if (strcmp($this->request['api_sig'], $signature) != 0) {
                    return ['A408' => 'Api Signature not equal'];
                }
            }

            Registry::set('api_key', $status['data']['api_key']);
            Registry::set('user', $status['data']['user']);
            Registry::set('userid', $status['data']['userid']);
            Registry::set('is_user_logged_in', true);
            Registry::set('is_api_request', true);

            $sets = $status['data']['set'];
            if (is_array($sets)) {
                foreach ($sets as $key => $value) {
                    Registry::set($key, $value);
                }
            }

            return true;
        } elseif (is_array($status) && !isset($status['data'])) {
            //return in case of error
            return $status;
        }

        return false;
    }

    /**
     * Functions to validate request.
     *
     * @param array &$request Element array
     * @param bool  $sig      Flag to specify force authentication
     * @param bool  $useronly Flag to check user only
     *
     * @return Boolean response
     **/
    protected function validate($sig = true)
    {
        $api_key = $this->request['api_key'];
        if (!$api_key) {
            return ['A402' => 'Api Key not found'];
        }

        if ($sig) {
            $api_sig = $this->request['api_sig'];

            if (!$api_sig) {
                return ['A403' => 'Api Signature not found'];
            }
        }

        $secret = $this->getSecret($api_key);

        if ($secret === false) {
            return ['A404' => 'Your api account got suspended'];
        }

        $secret['api_key'] = $api_key;

        if ($sig && !$secret['api_secret']) {
            return ['A405' => 'Api secret not found'];
        }

        if ($secret['allowed_ip']) {
            $ipaddr = Utility::ip();

            $allowed = explode(',', $secret['allowed_ip']);
            $allowed = array_map('trim', $allowed);

            if (!in_array($ipaddr, $allowed)) {
                $result = Utility::ipMatch($allowed);
                if (!$result) {
                    return ['A406' => 'Request is not allowed from this ip '.$ipaddr];
                }
            }
        }

        if ($secret['header']) {
            if ($this->server[$secret['header']['custom_key']] != $secret['header']['custom_value']) {
                return ['A407' => 'Header misconfigured'];
            }
        }

        if ($secret['protocol']) {
            if (($this->server['HTTPS'] && $this->server['HTTPS'] == 'off') || $this->server['SERVER_PORT'] != 443) {
                return ['A407A' => 'Protocol not allowed'];
            }
        }

        return [
            'status' => 'OK',
            'data'   => $secret,
        ];
    }

    /**
     * Functions to get secret key.
     *
     * @param string $api_key  API key
     * @param bool   $useronly Flag to check user only
     *
     * @return array data
     **/
    protected function getSecret($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        $row = [];

        if (strpos($api_key, ':') !== false) {
            $api_key = explode(':', $api_key);
            $token   = $api_key[0];

            if ($api_key[1] === 'x') {
                $user = $this->acl->getUserBy('token', $token);
            } else {
                $password = ($this->useronly) ? null : $api_key[1];
                $user     = $this->acl->isValidUser($token, $password);
            }

            if ($user === false) {
                return false;
            }
        } else {
            $row = $this->database->find('#__api_users', 'first', [
                'conditions' => ['status' => 1, 'api_key' => $api_key],
                'ignore'     => true,
            ]);

            if ($row['status'] != 1) {
                return false;
            }

            $user = $this->acl->getUserBy('userid', $row['fkuserid']);
        }

        if ($user['status'] != 1) {
            return false;
        }

        $row['userid'] = $user['userid'];
        $row['user']   = $user;

        return $row;
    }

    /**
     * Generate signature and validate against user signature.
     *
     * @param array  &$params Parameters
     * @param string $secret  Secret Key
     *
     * @return array data
     **/
    protected function generateSignature($secret)
    {
        $str = '';

        @ksort($this->params);
        // Note: make sure that the signature parameter is not already included in $params
        foreach ($this->params as $k => $v) {
            $str .= $k.$v;
        }
        $str = $secret.$str;

        return md5($str);
    }
}
