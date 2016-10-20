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

use Speedwork\Core\Traits\Macroable;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Router
{
    use Macroable;

    /**
     * Rewrite engines array.
     *
     * @var array
     */
    private static $engines = [];

    /**
     * Generate url link from url.
     *
     * @param string $url     full url without domain
     * @param bool   $ssl     is sll url
     * @param bool   $rewrite enable rewrite if avalable
     *
     * @return string return the complete with without domain
     */
    public static function link($link, $ssl = false, $rewrite = true)
    {
        if (!preg_match('/:\/\//', $link)
            && substr($link, 0, 2) != '//'
        ) {
            $url = _URL;
        }

        if ($link == 'index.php'
            || $link == '/index.php'
            || $link == 'index.html'
            || $link == '/'
        ) {
            return $url;
        }

        $link = static::fixLink($link);

        if ($rewrite) {
            foreach (static::$engines as $re) {
                $url = $re->rewrite($link, $url);
            }
        }

        return self::fix($url, $ssl);
    }

    /**
     * Route the incoming request.
     *
     * @return array
     */
    public static function route()
    {
        $link = [];
        foreach (static::$engines as $re) {
            $link = $re->route($link);
        }

        return static::processUrl($link);
    }

    /**
     * Route the link based on error code and url.
     *
     * @param array $link Url and error ocode
     *
     * @return array Parsed url options
     */
    protected static function processUrl($link = [])
    {
        $type = $link['type'];
        $url  = $link['url'];

        if (empty($url)) {
            return [];
        }

        if ($type == '301') {
            //Permanent (301)
            header('HTTP/1.1 301 Moved Permanently');
            header('Location:'.$url);

            return true;
        }

        if ($type == '302') {
            header('Location: '.$url);

            return true;
        }

        $values = parse_url($url, PHP_URL_QUERY);
        parse_str($values, $values);

        return $values;
    }

    /**
     * Fix the http and https prefixes of url.
     *
     * @param string $url
     * @param bool   $ssl
     *
     * @return string
     */
    public static function fix($url, $ssl = false)
    {
        if (!preg_match('/^(https?):\/\//', $url)) {
            if (substr($url, 0, 2) != '//') {
                $url = 'http://'.$url;
            } else {
                $url = 'http:'.$url;
            }
        }

        if ($ssl) {
            $url = str_replace('http://', 'https://', $url);
        }

        return $url;
    }

    /**
     * Convert url speedwork friendly url.
     *
     * @param string $url
     *
     * @return string
     */
    public static function fixLink($url)
    {
        $url = trim($url);
        $url = preg_replace('/\s{2,}/', ' ', $url);
        // Replace spaces
        $url = preg_replace('/\s/u', '%20', $url);
        $url = str_replace('&amp;', '&', $url);

        //replace com_
        $url = str_replace('com_', '', $url);

        if (!preg_match('/(https?):\/\//', $url) && substr($url, 0, 2) != '//') {
            if (substr($url, 0, 5) != 'index' && substr($url, 0, 6) != '/index') {
                $split = explode('?', $url, 2);
                if (empty($split[1])) {
                    $split = explode('&', $url, 2);
                }
                $details = explode('/', $split[0]);
                $url     = 'index.php?option='.$details[0];
                if ($details[1]) {
                    $url = $url.'&view='.$details[1];
                }
                if ($split[1]) {
                    $url = $url.'&'.$split[1];
                }
            }
        }

        return $url;
    }

    /**
     * Add rewrite methods to process url.
     *
     * @param null $rewrite
     */
    public static function addRewrite($rewrite)
    {
        static::$engines[] = $rewrite;
    }
}
