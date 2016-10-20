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

use Exception;
use Speedwork\Container\Container;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Kernel implements KernelInterface, HttpKernelInterface
{
    /**
     * The application implementation.
     *
     * @var \Speedwork\Container\Container
     */
    protected $app;

    /**
     * The bootstrap classes for the application.
     *
     * @var array
     */
    protected $bootstrappers = [];

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
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $this->app['request'] = $request;

        $request->enableHttpMethodParameterOverride();
        $this->bootstrap();
        try {
            $response = $this->handleRaw($request);
        } catch (\Exception $e) {
            if ($e instanceof ConflictingHeadersException) {
                $e = new BadRequestHttpException('The request headers contain conflicting information regarding the origin of this request.', $e);
            }
            if (false === $catch) {
                $this->finishRequest($request, $type);

                throw $e;
            }

            return $this->handleException($e, $request, $type);
        }

        if (!$response instanceof Response) {
            return new Response($response);
        }

        return $response;
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param Request $request A Request instance
     * @param int     $type    The type of the request (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     *
     * @return Response A Response instance
     */
    protected function handleRaw(Request $request)
    {
        // request
        $event = new GetResponseEvent($this, $request, $type);
        $this->app['events']->dispatch(KernelEvents::REQUEST, $event);

        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $request, $type);
        }

        $response = $this->renderRequest($request);

        if (is_array($response)) {
            $response = new JsonResponse($response);
        } elseif ($response instanceof RedirectResponse) {
            $response = $response->getResponse();
        } elseif (!$response instanceof Response) {
            $response = new Response($response);
        }

        return $this->filterResponse($response, $request, $type);
    }

    /**
     * Render the request.
     *
     * @param Request $request
     *
     * @return string|array
     */
    protected function renderRequest(Request $request)
    {
        return $this->getApplication()->get('template')->render();
    }

    /**
     * Publishes the finish request event, then pop the request from the stack.
     *
     * Note that the order of the operations is important here, otherwise
     * operations such as {@link RequestStack::getParentRequest()} can lead to
     * weird results.
     *
     * @param Request $request
     * @param int     $type
     */
    protected function finishRequest(Request $request, $type)
    {
        $this->app['events']->dispatch(KernelEvents::FINISH_REQUEST, new FinishRequestEvent($this, $request, $type));
    }

    /**
     * Filters a response object.
     *
     * @param Response $response A Response instance
     * @param Request  $request  An error message in case the response is not a Response object
     * @param int      $type     The type of the request (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     *
     * @throws \RuntimeException if the passed object is not a Response instance
     *
     * @return Response The filtered Response instance
     */
    protected function filterResponse(Response $response, Request $request, $type)
    {
        $headers = $request->getResponseHeader();
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value[0], $value[1]);
        }

        $cookies = $request->getResponseCookie();
        foreach ($cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $event = new FilterResponseEvent($this, $request, $type, $response);

        $this->app['events']->dispatch(KernelEvents::RESPONSE, $event);

        $this->finishRequest($request, $type);

        return $event->getResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function terminate($request, $response)
    {
        $this->app->terminate($request, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap()
    {
        if (!$this->app->isBootStrapped()) {
            $this->app->bootstrap($this->bootStrappers());
        }

        if (!$this->app->isBooted()) {
            $this->app->boot();
        }
    }

    /**
     * Handles an exception by trying to convert it to a Response.
     *
     * @param \Exception $e       An \Exception instance
     * @param Request    $request A Request instance
     * @param int        $type    The type of the request
     *
     * @throws \Exception
     *
     * @return Response A Response instance
     */
    protected function handleException(Exception $e, $request, $type)
    {
        $event = new GetResponseForExceptionEvent($this, $request, $type, $e);
        $this->app['events']->dispatch(KernelEvents::EXCEPTION, $event);

        // a listener might have replaced the exception
        $e = $event->getException();

        if (!$event->hasResponse()) {
            $this->finishRequest($request, $type);

            throw $e;
        }

        $response = $event->getResponse();

        // the developer asked for a specific status code
        if ($response->headers->has('X-Status-Code')) {
            $response->setStatusCode($response->headers->get('X-Status-Code'));

            $response->headers->remove('X-Status-Code');
        } elseif (!$response->isClientError() && !$response->isServerError() && !$response->isRedirect()) {
            // ensure that we actually have an error response
            if ($e instanceof HttpExceptionInterface) {
                // keep the HTTP status code and headers
                $response->setStatusCode($e->getStatusCode());
                $response->headers->add($e->getHeaders());
            } else {
                $response->setStatusCode(500);
            }
        }

        try {
            return $this->filterResponse($response, $request, $type);
        } catch (\Exception $e) {
            return $response;
        }
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    /**
     * {@inheritdoc}
     */
    public function getApplication()
    {
        return $this->app;
    }
}
