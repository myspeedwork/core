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

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
interface KernelInterface
{
    /**
     * Bootstrap the application for HTTP requests.
     */
    public function bootstrap();

    /**
     * Handle an incoming HTTP request.
     *
     * @param \Symfony\Component\HttpFoundation\Request  $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, $response);

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param \Symfony\Component\HttpFoundation\Request  $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    public function terminate($request, $response);

    /**
     * Get the Laravel application instance.
     *
     * @return \Speedwork\Container\Container
     */
    public function getApplication();
}
