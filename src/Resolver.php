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

use Closure;
use Exception;
use Speedwork\Core\Traits\ModuleResolverTrait;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Resolver extends Di
{
    use ModuleResolverTrait;

    /**
     * used to inject modules dynamically.
     *
     * @var array
     */
    public $_modules = [];

    protected function sanitize($option)
    {
        return ucfirst(strtolower($option));
    }

    protected function getPath($option, $type = 'component')
    {
        $type   = $type ?: 'component';
        $key    = 'app.apps.'.$type.'s.'.strtolower($option);
        $system = $this->config($key);

        if ($system) {
            return $system;
        }

        $folder = ucfirst($type).'s';
        $option = $this->sanitize($option);

        return [
            'views'     => SYSTEM.$folder.DS.$option,
            'namespace' => $this->app->getNameSpace().$folder.'\\'.$option.'\\',
        ];
    }

    protected function getViewPath($option, $type = 'component')
    {
        $path  = $this->getPath($option, $type);
        $views = (is_array($path['views'])) ? $path['views'] : [$path['views']];

        return $views;
    }

    protected function getNameSpace($option, $type = 'component')
    {
        $path      = $this->getPath($option, $type);
        $namespace = (is_array($path)) ? $path['namespace'] : $path;
        $namespace = rtrim($namespace, '\\').'\\';

        return $namespace;
    }

    public function loadController($name, $options = [], $instance = false)
    {
        if (empty($name)) {
            return false;
        }

        list($option, $view) = explode('.', $name);

        $option    = $this->sanitize($option);
        $signature = 'controller.'.$option;

        if (!$this->has($signature)) {
            $namespace = $this->getNameSpace($option);
            $class     = $namespace.'Controller';

            if (!class_exists($class)) {
                throw new Exception('Controller '.$class.' not found', 1);
            }

            $controller = new $class();
            $controller->setContainer($this->getContainer());
            $controller->{'model'} = $this->loadModel($option);

            $this->set($signature, $controller);
        } else {
            $controller = $this->get($signature);
        }

        if ($instance === 2) {
            return $controller;
        }

        $beforeRender = 'beforeRender';

        if (method_exists($controller, $beforeRender)) {
            $controller->$beforeRender();
        }

        if ($instance === true) {
            return $controller;
        }

        $method       = ($view) ? $view : 'index';
        $beforeMethod = 'before'.ucfirst($method);
        $afterMethod  = 'after'.ucfirst($method);

        if (method_exists($controller, $beforeMethod)) {
            $controller->$beforeMethod($options);
        }

        if ($method) {
            $response = $controller->$method($options);
        }

        if (method_exists($controller, $afterMethod)) {
            $controller->$afterMethod($options);
        }

        return $response;
    }

    protected function loadModel($option)
    {
        $option    = $this->sanitize($option);
        $signature = 'model.'.$option;

        if (!$this->has($signature)) {
            $namespace = $this->getNameSpace($option);
            $class     = $namespace.'Model';

            if (!class_exists($class)) {
                throw new Exception('Model '.$class.' not found', 1);
            }

            $model = new $class();
            $model->setContainer($this->getContainer());

            $this->set($signature, $model);
        } else {
            $model = $this->get($signature);
        }

        $beforeRender = 'beforeRender';

        if (method_exists($model, 'beforeRender')) {
            $model->$beforeRender();
        }

        return $model;
    }

    protected function findView($name, $type = 'component')
    {
        list($option, $view) = explode('.', $name, 2);

        $option = $this->sanitize($option);
        $type   = $type ?: 'component';
        $folder = ($type == 'component') ? 'Components' : 'Modules';

        $paths = $this->getViewPath($option, $type);
        $view  = explode('.', trim($view));

        $extensions = $this->config('view.extensions');
        $extensions = $extensions ?: ['tpl'];

        $views   = [];
        $views[] = THEME.strtolower($folder).DS.strtolower($option).DS.((!empty($view[0])) ? implode(DS, $view) : 'index');

        foreach ($paths as $path) {
            $views[] = $path.DS.'views'.DS.((!empty($view[0])) ? implode(DS, $view) : 'index');
        }

        foreach ($views as $file) {
            foreach ($extensions as $extension) {
                if (is_file($file.'.'.$extension)
                    && file_exists($file.'.'.$extension)
                ) {
                    return $file.'.'.$extension;
                }
            }
        }
    }

    public function loadView($name, $data = [], $type = 'component')
    {
        $view = $this->findView($name, $type);
        if (empty($view)) {
            return;
        }

        return $this->get('view.engine')->create($view, $data)->render();
    }

    public function requestLayout($name, $type = 'component')
    {
        $view = $this->findView($name, $type);
        if (empty($view)) {
            return $this->findView('errors');
        }

        return $view;
    }

    public function requestApi($option)
    {
        $option    = $this->sanitize($option);
        $signature = 'api.'.$option;

        if (!$this->has($signature)) {
            $namespace = $this->getNameSpace($option);
            $class     = $namespace.'Api';

            if (!class_exists($class)) {
                return ['A400' => 'Api Not Implemented'];
            }

            try {
                $instance = new $class();
            } catch (Exception $e) {
                return ['A400A' => 'Api Not Implemented'];
            }

            $instance->setContainer($this->getContainer());

            $this->set($signature, $instance);
        } else {
            $instance = $this->get($signature);
        }

        $beforeRender = 'beforeRender';
        if (method_exists($instance, 'beforeRender')) {
            $instance->$beforeRender();
        }

        return $instance;
    }

    public function requestController($name, $options = [])
    {
        return $this->loadController($name, $options, 2);
    }

    public function requestModel($name)
    {
        return $this->loadModel($name);
    }

    public function requestAction($name, $options = [])
    {
        return $this->component($name, $options);
    }

    public function component($name, $options = [])
    {
        $response = $this->loadController($name, $options);
        if (!is_array($response)) {
            $response = [];
        }

        $view = $this->findView($name, 'component');

        return $this->get('view.engine')->create($view, $response)->render();
    }

    /**
     * Used to include helper.
     *
     * @param string     $helper
     * @param (optional) $component
     **/
    public function helper($name)
    {
        if (!is_string($name)
            && $name instanceof Closure
        ) {
            return $name($this->getContainer());
        }

        $signature = 'helper.'.strtolower($name);

        if ($this->has($signature)) {
            return $this->get($signature);
        }

        list($helper, $component) = explode('.', $name);
        list($component, $group)  = explode(':', $component);

        $paths       = [];
        $helperClass = ucfirst($helper);

        if ($component) {
            $component = $this->sanitize($component);
            $namespace = $this->getNameSpace($component);

            $paths[] = [
                'class' => $namespace.'Helpers\\'.(($group) ? $group.'\\' : '').$helperClass,
            ];
        } else {
            $paths[] = [
                'class' => 'System\Helpers\\'.$helperClass,
            ];

            $paths[] = [
                'class' => 'Speedwork\\Helpers\\'.$helperClass,
            ];
        }

        foreach ($paths as $path) {
            $exists = false;
            if ($path['file'] && file_exists($path['file'])) {
                $exists = true;
                include_once $path['file'];
            } elseif (class_exists($path['class'])) {
                $exists = true;
            }

            if ($exists) {
                $helperClass = $path['class'];
                $beforeRun   = 'beforeRun';
                $instance    = new $helperClass($this->getContainer());

                if (method_exists($instance, $beforeRun)) {
                    $instance->$beforeRun();
                }

                $this->set($signature, $instance);

                return $instance;
            }
        }

        throw new Exception($helper.' helper not found');
    }

    /**
     * Include widget.
     *
     * @param string     $widget
     * @param (optional) $component
     **/
    public function widget($name, $options = [], $includeOnly = false)
    {
        $signature = 'widget.'.strtolower($name);

        if (!$this->has($signature)) {
            list($widget, $view, $component) = explode('.', $name);

            $class = ($view) ? $view : $widget;
            $class = ucfirst($class);

            if ($component) {
                $namespace = $this->getNameSpace($component);

                $paths[] = [
                    'class' => $namespace.'Widgets\\'.ucfirst($widget).'\\'.$class,
                ];
            } else {
                $namespace = $this->getNameSpace($widget, 'widget');
                $paths[]   = [
                    'class' => $namespace.$class,
                ];

                $paths[] = [
                    'class' => $namespace.$class,
                ];
            }

            foreach ($paths as $path) {
                $exists = false;
                if ($path['file'] && file_exists($path['file'])) {
                    $exists = true;
                    include_once $path['file'];
                } elseif (!$path['file'] && class_exists($path['class'])) {
                    $exists = true;
                }

                if ($exists) {
                    $class = $path['class'];

                    $instance = new $class();
                    $instance->setContainer($this->getContainer());

                    $this->set($signature, $instance);

                    break;
                }
            }
        } else {
            $instance = $this->get($signature);
        }

        if (!$instance) {
            throw new Exception("Widget '".$name."' not found");
        }

        $beforeRun = 'beforeRun';
        $afterRun  = 'afterRun';

        if (empty($options['selector'])) {
            $oldOptions          = $options;
            $options             = [];
            $options['options']  = $oldOptions;
            $options['selector'] = '.'.strtolower(str_replace('.', '-', $name));
        }

        $instance = $this->get($signature);
        $instance->resetOptions();
        $instance->setOptions($options);
        $instance->$beforeRun();

        if ($includeOnly) {
            return $instance;
        }

        $instance->run();
        $instance->$afterRun();

        return $instance;
    }
}
