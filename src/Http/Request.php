<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Http;

use ArrayAccess;
use Speedwork\Core\Interfaces\ArrayableInterface;
use Speedwork\Core\Traits\Macroable;
use Speedwork\Util\Arr;
use Speedwork\Util\Str;
use SplFileInfo;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Request extends SymfonyRequest implements ArrayableInterface, ArrayAccess
{
    use Macroable;

    /**
     * Response headers.
     *
     * @var \Symfony\Component\HttpFoundation\ParameterBag
     */
    protected $header;

    /**
     * Response cookies.
     *
     * @var \Symfony\Component\HttpFoundation\ParameterBag
     */
    protected $cookie;

    /**
     * The decoded JSON content for the request.
     *
     * @var string
     */
    protected $json;

    /**
     * Return the Request instance.
     *
     * @return $this
     */
    public function instance()
    {
        return $this;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public function method()
    {
        return $this->getMethod();
    }

    /**
     * Get the root URL for the application.
     *
     * @return string
     */
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost().$this->getBaseUrl(), '/');
    }

    /**
     * Get the URL (no query string) for the request.
     *
     * @return string
     */
    public function url()
    {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /**
     * Get the full URL for the request.
     *
     * @return string
     */
    public function fullUrl()
    {
        $query = $this->getQueryString();

        $question = $this->getBaseUrl().$this->getPathInfo() == '/' ? '/?' : '?';

        return $query ? $this->url().$question.$query : $this->url();
    }

    /**
     * Get the full URL for the request with the added query string parameters.
     *
     * @param array $query
     *
     * @return string
     */
    public function fullUrlWithQuery(array $query)
    {
        $question = $this->getBaseUrl().$this->getPathInfo() == '/' ? '/?' : '?';

        return count($this->query()) > 0
            ? $this->url().$question.http_build_query(array_merge($this->query(), $query))
            : $this->fullUrl().$question.http_build_query($query);
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function path()
    {
        $pattern = trim($this->getPathInfo(), '/');

        return $pattern == '' ? '/' : $pattern;
    }

    /**
     * Get the current encoded path info for the request.
     *
     * @return string
     */
    public function decodedPath()
    {
        return rawurldecode($this->path());
    }

    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function ajax()
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Determine if the request is the result of an PJAX call.
     *
     * @return bool
     */
    public function pjax()
    {
        return $this->headers->get('X-PJAX') === true;
    }

    /**
     * Determine if the request contains a given input item key.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function exists($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->all();

        foreach ($keys as $value) {
            if (!Arr::has($input, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request contains a non-empty value for an input item.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function has($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the given input key is an empty string for "has".
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isEmptyString($key)
    {
        $value = $this->input($key);

        $boolOrArray = is_bool($value) || is_array($value);

        return !$boolOrArray && trim((string) $value) === '';
    }

    /**
     * Get all of the input and files for the request.
     *
     * @return array
     */
    public function all()
    {
        return array_replace_recursive($this->input(), $this->files());
    }

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
        $input = $this->getInputSource()->all() + $this->query->all();
        if (is_null($key)) {
            return $input;
        }

        return data_get($input, $key, $default);
    }

    /**
     * Get a subset containing the provided keys with values from the input data.
     *
     * @param array|mixed $keys
     *
     * @return array
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = [];

        $input = $this->all();

        foreach ($keys as $key) {
            Arr::set($results, $key, data_get($input, $key));
        }

        return $results;
    }

    /**
     * Get all of the input except for a specified array of items.
     *
     * @param array|mixed $keys
     *
     * @return array
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = $this->all();

        Arr::forget($results, $keys);

        return $results;
    }

    /**
     * Intersect an array of items with the input data.
     *
     * @param array|mixed $keys
     *
     * @return array
     */
    public function intersect($keys)
    {
        return array_filter($this->only(is_array($keys) ? $keys : func_get_args()));
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
        return $this->retrieveItem('query', $key, $default);
    }

    /**
     * Determine if a cookie is set on the request.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasCookie($key)
    {
        return !is_null($this->cookie($key));
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
        return $this->retrieveItem('cookies', $key, $default);
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
        return $this->retrieveItem('files', $key, $default, true);
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
        return $this->retrieveItem('request', $key, $default);
    }

    /**
     * Determine if the uploaded data contains a file.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasFile($key)
    {
        if (!is_array($files = $this->files($key))) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if ($this->isValidFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the given file is a valid file instance.
     *
     * @param mixed $file
     *
     * @return bool
     */
    protected function isValidFile($file)
    {
        return $file instanceof SplFileInfo && $file->getPath() != '';
    }

    /**
     * Determine if a header is set on the request.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasHeader($key)
    {
        return !is_null($this->header($key));
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
        return $this->retrieveItem('headers', $key, $default);
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
        return $this->retrieveItem('server', $key, $default);
    }

    /**
     * Retrieve an old input item.
     *
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    public function old($key = null, $default = null)
    {
        return $this->session()->getOldInput($key, $default);
    }

    /**
     * Flash the input for the current request to the session.
     *
     * @param string $filter
     * @param array  $keys
     */
    public function flash($filter = null, $keys = [])
    {
        $flash = (!is_null($filter)) ? $this->$filter($keys) : $this->input();

        $this->session()->flashInput($flash);
    }

    /**
     * Flash only some of the input to the session.
     *
     * @param array|mixed $keys
     */
    public function flashOnly($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return $this->flash('only', $keys);
    }

    /**
     * Flash only some of the input to the session.
     *
     * @param array|mixed $keys
     */
    public function flashExcept($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return $this->flash('except', $keys);
    }

    /**
     * Flush all of the old input from the session.
     */
    public function flush()
    {
        $this->session()->flashInput([]);
    }

    /**
     * Retrieve a parameter item from a given source.
     *
     * @param string            $source
     * @param string            $key
     * @param string|array|null $default
     *
     * @return string|array
     */
    protected function retrieveItem($source, $key, $default)
    {
        if (is_null($key)) {
            return $this->$source->all();
        }

        return $this->$source->get($key, $default);
    }

    /**
     * Merge new input into the current request's input array.
     *
     * @param array $input
     */
    public function add(array $input)
    {
        $this->getInputSource()->add($input);
    }

    /**
     * Merge new input into the current request's input array.
     *
     * @param array $input
     */
    public function addInput(array $input)
    {
        $this->request->add($input);
        $this->query->add($input);
    }

    /**
     * Replace the input for the current request.
     *
     * @param array $input
     */
    public function replace(array $input)
    {
        $this->getInputSource()->replace($input);
    }

    /**
     * Get the JSON payload for the request.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function json($key = null, $default = null)
    {
        if (!isset($this->json)) {
            $this->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }

        if (is_null($key)) {
            return $this->json;
        }

        return $this->json->get($key, $default);
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
        if (!isset($this->header)) {
            $this->header = new ParameterBag();
        }

        if (is_array($key)) {
            foreach ($key as $baseKey => $baseValue) {
                $this->header->set($baseKey, [$baseValue, $replace]);
            }
        } else {
            $this->header->set($key, [$values, $replace]);
        }

        return $this;
    }

    /**
     * Sets a cookie.
     *
     * @param Cookie $cookie
     */
    public function setCookie(Cookie $cookie)
    {
        if (!isset($this->cookie)) {
            $this->cookie = new ParameterBag();
        }

        $this->cookie->set($cookie->getName(), $cookie);

        return $this;
    }

    /**
     * Get the response header.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getResponseHeader($key = null, $default = null)
    {
        if (!isset($this->header)) {
            $this->header = new ParameterBag();
        }

        if (is_null($key)) {
            return $this->header->all();
        }

        return $this->header->get($key, $default);
    }

    /**
     * Get the response header.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getResponseCookie($key = null, $default = null)
    {
        if (!isset($this->cookie)) {
            $this->cookie = new ParameterBag();
        }

        if (is_null($key)) {
            return $this->cookie->all();
        }

        return $this->cookie->get($key, $default);
    }

    /**
     * Get the input source for the request.
     *
     * @return \Symfony\Component\HttpFoundation\ParameterBag
     */
    protected function getInputSource()
    {
        if ($this->isJson()) {
            return $this->json();
        }

        return $this->getMethod() == 'GET' ? $this->query : $this->request;
    }

    /**
     * Determine if the given content types match.
     *
     * @param string $actual
     * @param string $type
     *
     * @return bool
     */
    public static function matchesType($actual, $type)
    {
        if ($actual === $type) {
            return true;
        }

        $split = explode('/', $actual);

        return isset($split[1]) && preg_match('#'.preg_quote($split[0], '#').'/.+\+'.preg_quote($split[1], '#').'#', $type);
    }

    /**
     * Determine if the request is sending JSON.
     *
     * @return bool
     */
    public function isJson()
    {
        return Str::contains($this->header('CONTENT_TYPE'), ['/json', '+json']);
    }

    /**
     * Determine if the current request probably expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson()
    {
        return ($this->ajax() && !$this->pjax()) || $this->wantsJson();
    }

    /**
     * Determine if the current request is asking for JSON in return.
     *
     * @return bool
     */
    public function wantsJson()
    {
        $acceptable = $this->getAcceptableContentTypes();

        return isset($acceptable[0]) && Str::contains($acceptable[0], ['/json', '+json']);
    }

    /**
     * Determines whether the current requests accepts a given content type.
     *
     * @param string|array $contentTypes
     *
     * @return bool
     */
    public function accepts($contentTypes)
    {
        $accepts = $this->getAcceptableContentTypes();

        if (count($accepts) === 0) {
            return true;
        }

        $types = (array) $contentTypes;

        foreach ($accepts as $accept) {
            if ($accept === '*/*' || $accept === '*') {
                return true;
            }

            foreach ($types as $type) {
                if ($this->matchesType($accept, $type) || $accept === strtok($type, '/').'/*') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return the most suitable content type from the given array based on content negotiation.
     *
     * @param string|array $contentTypes
     *
     * @return string|null
     */
    public function prefers($contentTypes)
    {
        $accepts = $this->getAcceptableContentTypes();

        $contentTypes = (array) $contentTypes;

        foreach ($accepts as $accept) {
            if (in_array($accept, ['*/*', '*'])) {
                return $contentTypes[0];
            }

            foreach ($contentTypes as $contentType) {
                $type = $contentType;

                if (!is_null($mimeType = $this->getMimeType($contentType))) {
                    $type = $mimeType;
                }

                if ($this->matchesType($type, $accept) || $accept === strtok($type, '/').'/*') {
                    return $contentType;
                }
            }
        }
    }

    /**
     * Determines whether a request accepts JSON.
     *
     * @return bool
     */
    public function acceptsJson()
    {
        return $this->accepts('application/json');
    }

    /**
     * Determines whether a request accepts HTML.
     *
     * @return bool
     */
    public function acceptsHtml()
    {
        return $this->accepts('text/html');
    }

    /**
     * Get the data format expected in the response.
     *
     * @param string $default
     *
     * @return string
     */
    public function format($default = 'html')
    {
        foreach ($this->getAcceptableContentTypes() as $type) {
            if ($format = $this->getFormat($type)) {
                return $format;
            }
        }

        return $default;
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public function bearerToken()
    {
        $header = $this->header('Authorization', '');

        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
    }

    /**
     * Get all of the input and files for the request.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->all();
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->all());
    }

    /**
     * Get the value at the given offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return data_get($this->all(), $offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value)
    {
        $this->getInputSource()->set($offset, $value);
    }

    /**
     * Remove the value at the given offset.
     *
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        $this->getInputSource()->remove($offset);
    }
}
