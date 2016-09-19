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

    /**
     * modules and components load from framework.
     *
     * @var array
     */
    protected $system = [
        'components' => [],
        'modules'    => [],
    ];

    public function setSystem($system = [])
    {
        if ($system && is_array($system)) {
            if ($system['components'] && is_array($system['components'])) {
                $this->system['components'] = array_merge($this->system['components'], $system['components']);
            }

            if ($system['modules'] && is_array($system['modules'])) {
                $this->system['modules'] = array_merge($this->system['modules'], $system['modules']);
            }
        }

        return $this;
    }

    protected function sanitize($option)
    {
        return ucfirst(strtolower($option));
    }

    protected function getPath($option, $type = 'component')
    {
        $option = strtolower($option);
        $system = $this->system[$type.'s'][$option];

        if ($system) {
            if (is_array($system)) {
                return $system;
            }

            return [
                'path'      => _SYS,
                'namespace' => null,
            ];
        }

        return [
            'path'      => SYSTEM,
            'namespace' => null,
        ];
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
            $class_name = 'Controller';

            $url       = $this->getPath($option);
            $path      = $url['path'];
            $namespace = $url['namespace'] ?: 'System\\Components\\';
            $url       = $url['url'];

            if (!empty($path)) {
                $file = $path.'Components'.DS.$option.DS.$class_name.'.php';

                if (!file_exists($file)) {
                    throw new \Exception('Controller '.$option.' not found', 1);
                }

                include_once $file;
            }

            $class = $namespace.$option.'\\'.$class_name;

            if (!class_exists($class)) {
                throw new \Exception('Controller '.$option.' not found', 1);
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
            $class_name = 'Model';

            $url       = $this->getPath($option);
            $path      = $url['path'];
            $namespace = $url['namespace'] ?: 'System\\Components\\';

            if (!empty($path)) {
                $file = $path.'Components'.DS.$option.DS.$class_name.'.php';

                if (!file_exists($file)) {
                    throw new \Exception('Model '.$option.' not found', 1);
                }

                include_once $file;
            }

            $class = $namespace.$option.'\\'.$class_name;

            if (!class_exists($class)) {
                throw new \Exception('Model '.$option.' not found', 1);
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

        $url  = $this->getPath($option, $type);
        $path = $url['path'];

        $view = explode('.', trim($view));

        $extensions = config('view.extensions');
        $extensions = $extensions ?: ['tpl'];

        $views   = [];
        $views[] = THEME.$folder.DS.$option.DS.((!empty($view[0])) ? implode(DS, $view) : 'index');
        $views[] = $path.$folder.DS.$option.DS.'views'.DS.((!empty($view[0])) ? implode(DS, $view) : 'index');

        foreach ($views as $file) {
            foreach ($extensions as $extension) {
                if (file_exists($file.'.'.$extension)) {
                    return $file.'.'.$extension;
                }
            }
        }
    }

    public function loadView($name, $type = 'component')
    {
        $view_file = $this->findView($name, $type);
        if (empty($view_file)) {
            return;
        }

        return $this->get('view.engine')->create($view_file)->render();
    }

    public function requestLayout($name, $type = 'component')
    {
        $view_file = $this->findView($name, $type);
        if (empty($view_file)) {
            return $this->findView('errors');
        }

        return $view_file;
    }

    public function requestApi($option)
    {
        $option    = $this->sanitize($option);
        $signature = 'api.'.$option;

        if (!$this->has($signature)) {
            $class_name = 'Api';

            $url       = $this->getPath($option);
            $path      = $url['path'];
            $namespace = $url['namespace'] ?: 'System\\Components\\';

            if (!empty($path)) {
                $file = $path.'Components'.DS.$option.DS.$class_name.'.php';

                if (!file_exists($file)) {
                    return ['A400' => 'Api Not Implemented'];
                }

                include_once $file;
            }

            $class = $namespace.$option.'\\'.$class_name;

            if (!class_exists($class)) {
                return ['A400' => 'Api Not Implemented'];
            }

            try {
                $instance = new $class();
            } catch (\Exception $e) {
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

        $view_file = $this->findView($name, 'component');

        return $this->get('view.engine')->create($view_file, $response)->render();
    }

    /**
     * Used to include helper.
     *
     * @param string     $helper
     * @param (optional) $component
     **/
    public function helper($helperName)
    {
        if (!is_string($helperName)
            && $helperName instanceof Closure
        ) {
            return $helperName($this->getContainer());
        }

        $signature = 'helper.'.strtolower($helperName);

        if ($this->has($signature)) {
            return $this->get($signature);
        }

        $helperName = explode('.', $helperName, 2);
        $component  = $helperName[1];

        $component = explode(':', $component);
        $group     = $component[1];
        $component = $component[0];

        $helper = $helperName[0];

        $paths       = [];
        $helperClass = ucfirst($helper);

        if ($component) {
            $component = $this->sanitize($component);
            $url       = $this->getPath($component);
            $dir       = $url['path'];
            $namespace = $url['namespace'] ?: 'System\\Components\\';

            $paths[] = [
                'file'  => $dir.'Components'.DS.$component.DS.'Helpers'.DS.(($group) ? $group.DS : '').$helperClass.'.php',
                'class' => $namespace.ucfirst($component).'\\Helpers\\'.(($group) ? $group.'\\' : '').$helperClass,
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
            list($widget, $component) = explode(':', $name);
            list($widget, $view)      = explode('.', $widget);

            $widget    = ucfirst($widget);
            $view      = ucfirst($view);
            $component = ucfirst($component);

            $class = ($view) ? $view : $widget;

            if ($component) {
                $component = $this->sanitize($component);
                $url       = $this->getPath($component);
                $dir       = $url['path'];
                $namespace = $url['namespace'] ?: 'System\\Components\\';

                $paths[] = [
                    'file'  => $dir.'Components'.DS.$component.DS.'Widgets'.DS.$widget.DS.$class.'.php',
                    'class' => $namespace.$component.'\\Widgets\\'.$widget.'\\'.$class,
                ];
            } else {
                $paths[] = [
                    'file'  => SYSTEM.'Widgets'.DS.$widget.DS.$class.'.php',
                    'class' => 'System\\Widgets\\'.$widget.'\\'.$class,
                ];

                $paths[] = [
                    'class' => 'System\\Widgets\\'.$widget.'\\'.$class,
                ];

                $paths[] = [
                    'file'  => SYSTEM.'Widgets'.DS.$class.'.php',
                    'class' => 'System\\Widgets\\'.$class,
                ];

                $paths[] = [
                    'class' => 'System\\Widgets\\'.$class,
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
            throw new \Exception("Widget '".$name."' not found");
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

    public function loadAppController($controller, $method = '')
    {
        if (!$controller) {
            return false;
        }

        //include Controller
        $file = APP.ucfirst($controller).'Controller.php';

        if (!file_exists($file)) {
            return false;
        }

        $component = 'App\\'.$controller.'Controller';
        //check whether this is already loaded
        if (!class_exists($component)) {
            include_once $file;
        }

        $controller = new $component();
        $controller->setContainer($this->getContainer());

        $beforeRender = 'beforeRender';
        if (method_exists($controller, 'beforeRender')) {
            $controller->$beforeRender();
        }

        if ($method) {
            $controller->{$method}();
        } else {
            $controller->index();
        }

        return $controller;
    }
}
