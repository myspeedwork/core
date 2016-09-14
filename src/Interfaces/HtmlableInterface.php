<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Interfaces;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
interface HtmlableInterface
{
    /**
     * Get content as a string of HTML.
     *
     * @return string
     */
    public function toHtml();
}
