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

use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class RedirectResponse
{
    protected $response = [];

    /**
     * Creates a redirect response so that it conforms to the rules defined for a redirect status code.
     *
     * @param string $url     The URL to redirect to. The URL should be a full URL, with schema etc.,
     *                        but practically every browser redirects on paths only as well
     * @param int    $status  The status code (302 by default)
     * @param array  $headers The headers (Location is always set to the given URL)
     *
     * @throws \InvalidArgumentException
     *
     * @see http://tools.ietf.org/html/rfc2616#section-10.3
     */
    public function __construct($url, $status = 302, $headers = [])
    {
        $this->response['url']     = $url;
        $this->response['status']  = $status;
        $this->response['headers'] = $headers;
    }

    /**
     * {@inheritdoc}
     */
    public static function create($url = '', $status = 302, $headers = [])
    {
        return new static($url, $status, $headers);
    }

    public function getResponse()
    {
        return new SymfonyRedirectResponse($this->response['url'], $this->response['status'], $this->response['headers']);
    }
}
