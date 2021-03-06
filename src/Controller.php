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

use Speedwork\Core\Traits\HttpTrait;
use Speedwork\Core\Traits\ResolverTrait;

/**
 * @author sankar <sankar.suda@gmail>
 */
class Controller extends Di
{
    use ResolverTrait;
    use HttpTrait;

    /**
     * Model object.
     *
     * @var \Speedwork\Core/Model
     */
    protected $model;

    public function setModel(Model $model)
    {
        $this->model = $model;
    }
}
