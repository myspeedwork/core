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

use Speedwork\Container\Container;
use Speedwork\Core\Traits\RequestTrait;
use Speedwork\Core\Traits\ResolverTrait;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Helper extends Di
{
    use ResolverTrait;
    use RequestTrait;

    public function __construct(Container $container = null)
    {
        if ($container) {
            $this->setContainer($container);
        }

        $this->setRequestParams();
    }
}
