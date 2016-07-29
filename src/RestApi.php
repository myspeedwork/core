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

use Speedwork\Util\RestUtils;
use Speedwork\Util\Utility;
use Speedwork\Util\Xml;

class RestApi extends Api
{
    protected $cache    = null;
    protected $useronly = false;
    protected $public   = [];
    protected $request  = [];

    public function setCache($cache = '+10 MINUTE')
    {
        $this->cache = $cache;

        return $this;
    }

    public function setRequest($request)
    {
        $request['api_key'] = $request['api_key'] ?: env('HTTP_API_KEY');
        $request['api_sig'] = $request['api_sig'] ?: env('HTTP_API_SIG');
        $request['format']  = $request['format'] ?: $request['output'];
        $request['format']  = $request['format'] ?: env('HTTP_API_FORMAT');
        $request['method']  = $request['method'] ?: env('HTTP_API_METHOD');
        $request['option']  = $request['option'] ?: env('HTTP_API_OPTION');
        $request['view']    = $request['view'] ?: env('HTTP_API_VIEW');

        if ($request['option']) {
            $method = explode('.', strtolower($request['option']));

            $request['option'] = $method[0];
            $request['view']   = $method[1] ?: $request['view'];
        } else {
            // Method is combination of option and view separated by . (dot)
            $method = explode('.', strtolower($request['method']));
            //format speedwork specification for component/view
            $request['option'] = $method[0];
            $request['view']   = $method[1];
        }

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
            $status['A401B'] = trans('Method Not Found');
        }

        return $this->outputFormat($status, $data);
    }

    private function outputFormat($status = [], $output = [])
    {
        // there is no status messages other than 200
        if (!empty($status)) {
            $key               = array_keys($status);
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

        if (!method_exists($controller, $method)) {
            $status['A401A'] = trans('Method Not Implemented');

            return $this->outputFormat($status, $data);
        }

        $controller->setData($this->request);

        return $this->formatResponse($controller->$method());
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
            $status['A400'] = trans('Api Not Implemented');

            return $this->outputFormat($status, $data);
        }

        $method = ($view) ? $view : 'index';

        if (!method_exists($controller, $method)) {
            $status['A401A'] = trans('Method Not Implemented');

            return $this->outputFormat($status, $data);
        }

        $controller->setData($this->request);

        return $this->formatResponse($controller->$method());
    }

    protected function formatResponse($response = [])
    {
        $api = $response['api'];
        unset($response['api']);

        if ($api === false) {
            $status['A401A'] = trans('Method Not Allowed');

            return $this->outputFormat($status, $data);
        }

        $output = null;

        $return            = [];
        $return['status']  = (empty($response['status'])) ? 'OK' : $response['status'];
        $return['message'] = (empty($response['message'])) ? 'OK' : $response['message'];
        $return['data']    = $output;

        unset($response['status'], $response['message']);

        if ($api && is_callable($api)) {
            $output = $api($response);
        } elseif ($api && is_array($api)) {
            foreach ($api as $key => $value) {
                $value = explode('.', $value);
                $key   = (!is_numeric($key)) ? $key : end($value);

                $data = $response;
                foreach ($value as $val) {
                    $data = $data[$val];
                }
                $output[$key] = $data;
            }
        } else {
            $output = $response;
        }

        if ($output['data']) {
            $output['rows'] = $output['data'];
            unset($output['data']);
        } elseif (isset($output['rows'])) {
            $data   = $output['rows']['data'];
            $paging = $output['rows']['paging'];

            unset($output['rows']['data'], $output['rows']['paging']);
            $items = $output['rows'];
            unset($output['rows']);

            $output           = array_merge($items, $output);
            $output['rows']   = $data;
            $output['paging'] = $paging;
        }

        if (isset($output['paging'])) {
            unset($output['paging']['html']);
        }

        $return['data'] = $output;

        unset($response, $output, $data, $paging, $items);

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
    public function output($output = null)
    {
        $format   = strtolower($this->request['format']);
        $name     = ($this->request['view']) ?: $this->request['option'];
        $callback = $this->request['callback'];

        $this->setRequest([]);

        switch ($format) {
            case 'none':
                return;
            break;

            case 'array':
                return $output;
            break;

            case 'xml':
                return RestUtils::sendResponse(Xml::fromArray($output, 'api', $name), 'application/xml');
                break;

            case 'php':
                return RestUtils::sendResponse(serialize($output));
                break;

            case 'jsonp':
                if ($callback) {
                    $output = json_encode($output);
                    header('Content-Type: text/javascript; charset=utf8');

                    return $callback.'('.$output.');';
                } else {
                    return RestUtils::sendResponse(json_encode($output), 'application/json');
                }
                break;

            case 'json':
            default:
                return RestUtils::sendResponse(json_encode($output), 'application/json');
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

        $option = $this->request['option'];
        $view   = $this->request['view'];

        $permissons = [
            $option.'.'.$view,
            $option.'.*',
        ];

        foreach ($permissons as $permisson) {
            if (in_array($permisson, $public)) {
                return true;
            }
        }

        if (in_array($this->request['method'], $public)) {
            return true;
        }

        $api_key = $this->request['api_key'];
        if (!$api_key) {
            return ['A402' => trans('Api Key not found')];
        }

        if ($this->cache) {
            $catch_key = 'api_cache_'.$api_key.'_'.ip();
            $status    = $this->get('cache')->remember($catch_key, function () use ($sig) {
                return $this->validate($sig);
            }, $this->cache);
        } else {
            $status = $this->validate($sig);
        }

        if ($status['status'] == 'OK') {
            if ($sig) {
                $signature = $this->generateSignature($status['api_secret']);

                if (strcmp($this->request['api_sig'], $signature) != 0) {
                    return ['A408' => trans('Api Signature not equal')];
                }
            }

            $this->set('api_key', $status['data']['api_key']);
            $this->set('user', $status['data']['user']);
            $this->set('userid', $status['data']['userid']);
            $this->set('is_user_logged_in', true);

            $sets = $status['data']['set'];
            if (is_array($sets)) {
                foreach ($sets as $key => $value) {
                    $this->set($key, $value);
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
            return ['A402' => trans('Api Key not found')];
        }

        if ($sig) {
            $api_sig = $this->request['api_sig'];

            if (!$api_sig) {
                return ['A403' => trans('Api Signature not found')];
            }
        }

        $secret = $this->getSecret($api_key);

        if ($secret === false) {
            return ['A404' => trans('Your api account got suspended')];
        }

        $secret['api_key'] = $api_key;

        if ($sig && !$secret['api_secret']) {
            return ['A405' => trans('Api secret not found')];
        }

        if ($secret['allowed_ip']) {
            $ipaddr = ip();

            $allowed = explode(',', $secret['allowed_ip']);
            $allowed = array_map('trim', $allowed);

            if (!in_array($ipaddr, $allowed)) {
                $result = Utility::ipMatch($allowed);
                if (!$result) {
                    return ['A406' => trans('Request is not allowed from this ip :0', [$ipaddr])];
                }
            }
        }

        if ($secret['header']) {
            if (env($secret['header']['custom_key']) != $secret['header']['custom_value']) {
                return ['A407' => trans('Header misconfigured')];
            }
        }

        if ($secret['protocol']) {
            if ((env('HTTPS') && env('HTTPS') == 'off') || env('SERVER_PORT') != 443) {
                return ['A407A' => trans('Protocol not allowed')];
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
                $user = $this->get('acl')->getUserBy('token', $token);
            } else {
                $password = ($this->useronly) ? null : $api_key[1];
                $user     = $this->get('acl')->isValidUser($token, $password);
            }

            if ($user === false) {
                return false;
            }
        } else {
            $row = $this->database->find('#__users_api', 'first', [
                'conditions' => ['status' => 1, 'api_key' => $api_key],
                'ignore'     => true,
            ]);

            if ($row['status'] != 1) {
                return false;
            }

            $user = $this->get('acl')->getUserBy('userid', $row['user_id']);
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
     * @param string $secret Secret Key
     *
     * @return array data
     **/
    protected function generateSignature($secret)
    {
        $str = '';

        ksort($this->params);
        // Note: make sure that the signature parameter is not already included in $params
        foreach ($this->params as $k => $v) {
            $str .= $k.$v;
        }
        $str = $secret.$str;

        return md5($str);
    }
}
