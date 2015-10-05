<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
abstract class Widget extends Di
{
    protected $options        = [];
    protected $defaultOptions = [];
    protected $scripts        = [];
    protected $styles         = [];

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

    private function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function setOptions($options)
    {
        $this->options = array_replace_recursive($this->options, $options);

        return $this;
    }

    public function defaultOptions($options)
    {
        $this->defaultOptions = array_replace_recursive($this->defaultOptions, $options);

        return $this;
    }

    protected function getOptions()
    {
        if (!is_array($this->options['options'])) {
            $this->options['options'] = [];
        }

        return array_replace_recursive($this->defaultOptions, $this->options['options']);
    }

    protected function getDecodedOptions()
    {
        return $this->decode($this->getOptions());
    }

    protected function setRun($name, $selector = null)
    {
        //Add Scripts
        foreach ($this->scripts as $script) {
            $this->get('template')->script($script, 'bower');
        }

        //Add Styles
        foreach ($this->styles as $style) {
            $this->get('template')->styleSheet($style, 'bower');
        }

        $selectors = [];

        $defaultSelector = $this->options['selector'];
        if (empty($defaultSelector)) {
            $selectors[] = '.'.str_replace('.', '-', $name);
        } else {
            $selectors[] = $defaultSelector;
        }
        $selectors[] = '[role='.$name.']';

        if (!empty($selector)) {
            if (is_array($selector)) {
                $selectors = array_merge($selectors, $selector);
            } else {
                $selectors[] = $selector;
            }
        }

        $js = 'jQuery("'.implode(',', $selectors).'").livequery(function(){';
        $js .= 'var $this = $(this);';
        $js .= '$this.'.$name.'('.$this->getDecodedOptions().');';
        $js .= '});';

        $this->get('template')->addScriptDeclaration($js);
    }

    public function beforeRun()
    {
    }

    abstract public function run();

    public function afterRun()
    {
    }
}
