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

use Speedwork\Container\Container;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Kernel implements KernelInterface
{
    /**
     * The application implementation.
     *
     * @var \Speedwork\Container\Container
     */
    protected $app;

    /**
     * Create a new console kernel instance.
     *
     * @param \Speedwork\Container\Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($request, $response)
    {
        $this->app['response'] = $response;
        $this->app['request']  = $request;

        $request->enableHttpMethodParameterOverride();
        $this->bootstrap();
        $content = $this->sendRequest($request);
        if ($content instanceof Response) {
            return $content;
        }

        $response->setContent($content);

        return $response;
    }

    protected function sendRequest($request)
    {
        return $this->getApplication()->get('template')->render();
    }

    /**
     * {@inheritdoc}
     */
    public function terminate($request, $response)
    {
        $this->app->terminate();
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap()
    {
        if (!$this->app->isBooted()) {
            $this->app->boot();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getApplication()
    {
        return $this->app;
    }
}
