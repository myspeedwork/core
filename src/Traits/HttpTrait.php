<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Traits;

use Exception;
use Speedwork\Core\Http\RedirectResponse;
use Speedwork\Core\Router;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Request variables handle.
 *
 * @author sankar <sankar.suda@gmail.com>
 */
trait HttpTrait
{
    /**
     * Retrieve an input item from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function input($key = null, $default = null)
    {
        return $this->get('request')->input($key, $default);
    }

    /**
     * Retrieve a query string item from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function query($key = null, $default = null)
    {
        return $this->get('request')->query($key, $default);
    }

    /**
     * Retrieve a cookie from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function cookie($key = null, $default = null)
    {
        return $this->get('request')->cookie($key, $default);
    }

    /**
     * Retrieve a files from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function files($key = null, $default = null)
    {
        return $this->get('request')->files($key, $default, true);
    }

    /**
     * Retrieve a post items from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function post($key = null, $default = null)
    {
        return $this->get('request')->post($key, $default);
    }

    /**
     * Retrieve a header from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function header($key = null, $default = null)
    {
        return $this->get('request')->headers($key, $default);
    }

    /**
     * Retrieve a server variable from the request.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function server($key = null, $default = null)
    {
        return $this->get('request')->server($key, $default);
    }

    /**
     * Sets a header by name.
     *
     * @param string|array $key     The key
     * @param string|array $values  The value or an array of values
     * @param bool         $replace Whether to replace the actual value or not (true by default)
     */
    public function setHeader($key, $values = null, $replace = true)
    {
        if (is_array($key)) {
            foreach ($key as $baseKey => $baseValue) {
                $this->get('request')->setHeader($baseKey, $baseValue, $replace);
            }
        } else {
            $this->get('request')->setHeader($key, $values, $replace);
        }

        return $this;
    }

    /**
     * Set Cookie.
     *
     * @param string                        $name   The name of the cookie
     * @param string                        $value  The value of the cookie
     * @param int|string|\DateTimeInterface $expire The time the cookie expires
     */
    public function setCookie($name, $value = null, $expire = 0)
    {
        try {
            $this->get('request')->setCookie(new Cookie($name, $value, $expire));
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Set Session.
     *
     * @param string $name  Name of the Session
     * @param string $value Value of the Session
     */
    public function setSession($name, $value = null)
    {
        return $this->get('session')->set($name, $value);
    }

    /**
     * Get Session.
     *
     * @param string $name    Name of the Session
     * @param mixed  $default The default value if not found
     */
    public function getSession($name, $default = null)
    {
        return $this->get('session')->get($name, $default);
    }

    /**
     * Delete Session.
     *
     * @param string $name Name of the Session
     */
    public function removeSession($name)
    {
        return $this->get('session')->remove($name);
    }

    /**
     * Generate Proper link.
     *
     * @param string $url
     *
     * @return string
     */
    public function link($url)
    {
        return Router::link($url);
    }

    /**
     * Redirect the url.
     *
     * @param [type] $url  [description]
     * @param int    $time [description]
     * @param bool   $html [description]
     *
     * @return [type] [description]
     */
    public function redirect($url, $status = 302, $rewrite = true)
    {
        if (empty($url)) {
            $url = 'index.php';
        }

        if ($rewrite) {
            $url = $this->link($url);
        }

        $ajax = $this->get('is_ajax_request');
        if ($ajax) {
            $status = $this->release('status');

            $status['redirect'] = $url;

            return $status;
        }

        return new RedirectResponse($url, $status);
    }

    /**
     * Creates a streaming response.
     *
     * @param mixed $callback A valid PHP callback
     * @param int   $status   The response status code
     * @param array $headers  An array of response headers
     *
     * @return StreamedResponse
     */
    public function stream($callback = null, $status = 200, array $headers = [])
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Escapes a text for HTML.
     *
     * @param string $text         The input text to be escaped
     * @param int    $flags        The flags (@see htmlspecialchars)
     * @param string $charset      The charset
     * @param bool   $doubleEncode Whether to try to avoid double escaping or not
     *
     * @return string Escaped text
     */
    public function escape($text, $flags = ENT_COMPAT, $charset = null, $doubleEncode = true)
    {
        return htmlspecialchars($text, $flags, $charset ?: $this['charset'], $doubleEncode);
    }

    /**
     * Convert some data into a JSON response.
     *
     * @param mixed $data    The response data
     * @param int   $status  The response status code
     * @param array $headers An array of response headers
     *
     * @return JsonResponse
     */
    public function json($data = [], $status = 200, array $headers = [])
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Sends a file.
     *
     * @param \SplFileInfo|string $file        The file to stream
     * @param int                 $status      The response status code
     * @param array               $headers     An array of response headers
     * @param null|string         $disposition The type of Content-Disposition to set automatically with the filename
     *
     * @return BinaryFileResponse
     */
    public function sendFile($file, $status = 200, array $headers = [], $disposition = null)
    {
        return new BinaryFileResponse($file, $status, $headers, true, $disposition);
    }

    /**
     * Aborts the current request by sending a proper HTTP error.
     *
     * @param int    $statusCode The HTTP status code
     * @param string $message    The status message
     * @param array  $headers    An array of HTTP headers
     */
    public function abort($statusCode, $message = '', array $headers = [])
    {
        if ($statusCode == 404) {
            throw new NotFoundHttpException($message);
        }

        throw new HttpException($statusCode, $message, null, $headers);
    }
}
