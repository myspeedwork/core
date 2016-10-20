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

use Speedwork\Core\Http\Request;
use Speedwork\Util\Utility;
use Speedwork\Util\Xml;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class RestApi extends Api
{
    protected $cache    = null;
    protected $useronly = false;
    protected $public   = [];

    protected $headers = [];

    public function setCache($cache = '+10 MINUTE')
    {
        if (is_string($cache)) {
            $this->cache = $cache;
        }

        return $this;
    }

    public function change($request)
    {
        $this->headers = [
            'api_key'     => 'HTTP_X_AUTH_KEY',
            'signature'   => 'HTTP_X_AUTH_SIGNATURE',
            'auth_method' => 'HTTP_X_AUTH_METHOD',
            'nonce'       => 'HTTP_X_API_NONCE',
            'version'     => 'HTTP_X_API_VERSION',
            'timestamp'   => 'HTTP_X_API_TIMESTAMP',
            'format'      => 'HTTP_X_API_FORMAT',
            'method'      => 'HTTP_X_API_METHOD',
            'option'      => 'HTTP_X_API_OPTION',
            'view'        => 'HTTP_X_API_VIEW',
        ];

        $auth = env('HTTP_AUTHORIZATION');
        if ($auth) {
            list($type, $auth)     = explode(' ', $auth);
            list($key, $signature) = explode(':', $auth);

            $request['api_key']   = $key;
            $request['signature'] = $signature;
        }

        foreach ($this->headers as $key => $value) {
            $request[$key] = $request[$key] ?: env($value);
        }

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

        return $request;
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

    public function processMethod()
    {
        $data   = [];
        $status = [];

        //if method found
        if ($this->input('option')) {
            //validate Request
            $sig = $this->authenticate();

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
        $app_api = ($this->input('app')) ? true : false;

        if ($app_api) {
            return $this->processApp($request);
        }

        return $this->processApi($request);
    }

    public function processApi()
    {
        $option = $this->input('option');
        $view   = $this->input('view');

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

        return $this->formatResponse($controller->$method());
    }

    protected function processApp()
    {
        $option = $this->input('option');
        $view   = $this->input('view');

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
        $format   = strtolower($this->input('format'));
        $name     = ($this->input('view')) ?: $this->input('option');
        $callback = $this->input('callback');

        $this->setStatusCode($output['status'], $output['message']);

        switch ($format) {
            case 'none':
                return;
            break;

            case 'array':
                return $output;
            break;

            case 'xml':
                return $this->response(Xml::fromArray($output, 'api', $name), 'application/xml');
                break;

            case 'php':
                return $this->response(serialize($output));
                break;

            case 'jsonp':
            case 'json':
            default:
                if ($callback) {
                    return $this->response($callback.'('.json_encode($output).');', 'text/javascript');
                } else {
                    return $this->response(json_encode($output), 'application/json');
                }
                break;
        }
    }

    protected function authenticate()
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

        $option = $this->input('option');
        $view   = $this->input('view');

        $permissons = [
            $option.'.'.$view,
            $option.'.*',
        ];

        foreach ($permissons as $permisson) {
            if (in_array($permisson, $public)) {
                return true;
            }
        }

        if (in_array($this->input('method'), $public)) {
            return true;
        }

        $api_key = $this->input('api_key');
        if (!$api_key) {
            return ['A402' => trans('Api Key not found')];
        }

        if ($this->cache) {
            $catch_key = 'api_cache_'.$api_key.'_'.ip();

            $status = $this->get('cache')->remember($catch_key, function () {
                return $this->validate();
            }, $this->cache);
        } else {
            $status = $this->validate();
        }

        if ($status['status'] == 'OK') {
            $signature = $status['data']['signature'];
            if ($signature) {
                $signature = $this->signature($status['data']['api_secret']);

                if (strcmp($this->input('signature'), $signature) !== 0) {
                    return ['A408' => trans('Api Signature not equal')];
                }
            }

            foreach ($status['data'] as $key => $value) {
                $this->set($key, $value);
            }

            $this->set('is_user_logged_in', true);

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
     * @return bool response
     **/
    protected function validate()
    {
        $api_key = $this->input('api_key');
        if (!$api_key) {
            return ['A402' => trans('Api Key not found')];
        }

        $secret = $this->getSecret($api_key);

        if ($secret === false) {
            return ['A404' => trans('Your api account got suspended')];
        }

        if ($secret['signature']) {
            $signature = $this->input('signature');

            if (!$signature) {
                return ['A403' => trans('Api Signature not found')];
            }
        }

        if ($secret['signature'] && !$secret['api_secret']) {
            return ['A405' => trans('Api secret not found')];
        }

        $secret['api_key'] = $api_key;

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
            list($token, $password) = explode(':', $api_key);

            if ($password === 'x') {
                $user = $this->get('acl')->getUserBy('token', $token);
            } else {
                $password = ($this->useronly) ? null : $password;
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
    protected function signature($secret)
    {
        $request = $this->getAuthParams($this->get('request')->all());
        $payload = $this->payload([], $request);
        $payload = base64_encode(http_build_query($payload));

        $api_key = $this->input('api_key');
        $nonce   = $this->input('nonce');

        $payload = implode("\n", [$api_key, $nonce, $payload]);

        $auth = strtoupper($this->input('auth_method'));

        $methods = [
            'HMAC-SHA256' => 'SHA256',
            'HMAC-MD5'    => 'MD5',
            'HMAC-SHA1'   => 'SHA1',
        ];

        $method = $methods[$auth] ?: 'SHA256';

        return hash_hmac($method, $payload, $secret);
    }

    /**
     * Create the payload.
     *
     * @param array $auth
     * @param array $params
     *
     * @return array
     */
    protected function payload(array $auth, array $params)
    {
        $payload = array_merge($auth, $params);
        $payload = array_change_key_case($payload, CASE_LOWER);

        ksort($payload);

        return $payload;
    }

    public function handle(Request $request)
    {
        $data = $request->all();
        $request->replace($this->change($data));

        return $this;
    }

    /**
     * Send Resonse with proper content type.
     *
     * @param string $body        Response Body
     * @param string $contentType
     *
     * @return string
     */
    public function response($body = '', $contentType = 'text/html')
    {
        $contentType = $contentType ?: 'text/html';
        // Set the content type
        header('Content-type: '.$contentType.'; charset=utf8');

        return $body;
    }

    /**
     * Get the auth params.
     *
     * @param $prefix
     *
     * @return array
     */
    protected function getAuthParams($params)
    {
        return array_diff_key($params, $this->headers);
    }

    /**
     * Set the proper response header.
     *
     * @param string $code
     * @param string $message
     */
    protected function setStatusCode($code, $message)
    {
        $codes = [
            'A402'  => 401,
            'A403'  => 401,
            'A404'  => 401,
            'A405'  => 401,
            'A406'  => 401,
            'A407'  => 401,
            'A407A' => 401,
            'A401B' => 400,
            'A402'  => 400,
        ];

        if ($code == '200' || $code == 'OK') {
            header('HTTP/1.1 200 OK');
        } elseif (isset($codes[$code])) {
            header('HTTP/1.1 '.$codes[$code].' '.$message);
        } else {
            header('HTTP/1.1 404 '.$message);
        }
    }
}
