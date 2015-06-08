<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Widget extends Controller
{
    protected $options        = [];
    protected $defaultOptions = [];

    protected function decode($array = [])
    {
        if (count($array) > 0) {
            $options = [];
            foreach ($array as $k => $v) {
                $value = $v;
                if (is_array($v)) {
                    if ($this->isAssoc($v)) {
                        $options[] = $k.':'.$this->decode($v);
                    } else {
                        $options[] = $k.':'.json_encode($v);
                    }
                } else {
                    if (is_numeric($v) || strtolower($v) == 'true' || strtolower($v) == 'false') {
                        $value = $v;
                    } elseif (strpos($v, 'js:') !== false) {
                        $value = ltrim($v, 'js:');
                        $value = str_replace('js:', '', $v);
                    } else {
                        $value = '"'.$v.'"';
                    }

                    $options[] = $k.':'.$value;
                }
            }

            return '{'.implode(',', $options).'}';
        }
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

    protected function getOptions()
    {
        if (!is_array($this->options['options'])) {
            $this->options['options'] = [];
        }

        return array_merge($this->defaultOptions, $this->options['options']);
    }

    protected function getDecodedOptions()
    {
        return $this->decode($this->getOptions());
    }

    private function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function beforeRun()
    {
    }
    public function run()
    {
    }

    public function afterRun()
    {
    }
}
