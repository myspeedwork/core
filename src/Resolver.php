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

use Exception;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Resolver extends Di
{
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
        'component' => [],
        'module'    => [],
    ];

    public function setSystem($system = [])
    {
        if ($system && is_array($system)) {
            if ($system['component'] && is_array($system['component'])) {
                $this->system['component'] = array_merge($this->system['component'], $system['component']);
            }

            if ($system['module'] && is_array($system['module'])) {
                $this->system['module'] = array_merge($this->system['module'], $system['module']);
            }
        }
    }

    protected function sanitize($option, $type = 'component')
    {
        $option = strtolower($option);

        if ($type == 'module') {
            return str_replace('mod_', '', $option);
        }

        return str_replace('com_', '', $option);
    }

    protected function setPath($path)
    {
        if ($this->has('template')) {
            $this->get('template')->setPath($path);
        }
    }

    public function url($option, $type = 'component')
    {
        $url = $this->getPath($option, $type);

        return $url['url'];
    }

    protected function getPath($option, $type = 'component')
    {
        $option = str_replace(['com_', 'mod_'], '', strtolower($option));
        $path   = _APP;
        $url    = __APP_URL;
        if ($this->system[$type][$option]) {
            $path = _SYS;
            $url  = __SYSURL;
        }

        return [
            'url'  => $url,
            'path' => $path,
        ];
    }

    public function loadController($option, $view = '', $options = [], $instance = false)
    {
        if (empty($option)) {
            return false;
        }

        $option    = $this->sanitize($option);
        $signature = 'controller'.$option;

        if (!$this->has($signature)) {
            $name       = ucfirst($option);
            $class_name = 'Controller';

            $url  = $this->getPath($option);
            $path = $url['path'];
            $url  = $url['url'];

            //include Controller
            $file = $path.'components'.DS.$option.DS.$class_name.'.php';

            if (!file_exists($file)) {
                throw new \Exception('Controller '.$option.' not found', 1);
            }

            $class = 'Components\\'.$name.'\\'.$class_name;

            require_once $file;

            $controller = new $class();
            $controller->setContainer($this->di);
            $controller->{'model'} = $this->loadModel($option);

            $assets = $url.'components/'.$option.'/assets/';
            $this->set($signature, $controller);
            $this->set($signature.'.assets', $assets);
        } else {
            $controller = $this->get($signature);
            $assets     = $this->get($signature.'.assets');
        }

        if ($instance === 2) {
            return $controller;
        }

        $this->setPath($assets);

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

        if ((method_exists($controller, $method) || method_exists($controller, '__call'))) {
            $response = $controller->$method($options);
        }

        if (method_exists($controller, $afterMethod)) {
            $controller->$afterMethod($options);
        }

        return $response;
    }

    public function loadModel($option)
    {
        $option = $this->sanitize($option);

        $signature = 'model'.$option;

        if (!$this->has($signature)) {
            $url  = $this->getPath($option);
            $path = $url['path'];

            $name       = ucfirst($option);
            $class_name = 'Model';

            $file = $path.'components'.DS.$option.DS.$class_name.'.php';

            if (!file_exists($file)) {
                throw new \Exception('Model '.$option.' not found');
            }

            $class = 'Components\\'.$name.'\\'.$class_name;

            require_once $file;

            $model = new $class();
            $model->setContainer($this->di);

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

    protected function findView($option, $type = 'component')
    {
        $option = explode('.', $option, 2);
        $view   = $option[1];
        $option = $option[0];

        $type   = (empty($type)) ? 'component' : $type;
        $option = $this->sanitize($option, $type);
        $folder = ($type == 'component') ? 'components' : 'modules';

        $url  = $this->getPath($option, $type);
        $path = $url['path'];

        $view = explode('.', strtolower(trim($view)));

        $extensions = ['.tpl'];

        $views   = [];
        $views[] = _TMP_PATH.$folder.DS.$option.DS.((!empty($view[0])) ? implode(DS, $view) : 'index');
        $views[] = $path.$folder.DS.$option.DS.'views'.DS.((!empty($view[0])) ? implode(DS, $view) : 'index');

        foreach ($views as $file) {
            foreach ($extensions as $ext) {
                if (file_exists($file.$ext)) {
                    return $file.$ext;
                }
            }
        }

        return;
    }

    public function loadView($component, $view = '', $type = 'component')
    {
        $view_file = $this->findView($component.'.'.$view, $type);
        if (empty($view_file)) {
            return;
        }

        return $this->get('engine')->create($view_file)->render();
    }

    public function requestLayout($component, $view = '', $type = 'component')
    {
        $view_file = $this->findView($component.'.'.$view, $type);
        if (empty($view_file)) {
            return $this->findView('errors');
        }

        return $view_file;
    }

    public function requestApi($component)
    {
        $component = $this->sanitize($component);
        $signature = 'api'.$component;

        if (!$this->has($signature)) {
            $url  = $this->getPath($component);
            $path = $url['path'];

            $name       = ucfirst($component);
            $class_name = 'Api';

            $model_file = $path.'components'.DS.$component.DS.$class_name.'.php';

            if (!file_exists($model_file)) {
                return ['A400' => 'Api Not Implemented'];
            }

            $class = 'Components\\'.$name.'\\'.$class_name;

            if (!class_exists($class)) {
                include $model_file;
            }

            try {
                $instance = new $class();
            } catch (\Exception $e) {
                return ['A400A' => 'Api Not Implemented'];
            }

            $instance->setContainer($this->di);

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

    public function requestController($component, $options = [])
    {
        return $this->loadController($component, '', $options, 2);
    }

    public function requestModel($component)
    {
        return $this->loadModel($component);
    }

    public function requestAction($option, $view = null, $options = [])
    {
        echo $this->component($option, $view, $options);
    }

    public function component($option, $view = null, $options = [])
    {
        $response = $this->loadController($option, $view, $options);
        if (!is_array($response)) {
            $response = [];
        }

        $view_file = $this->findView($option.'.'.$view, 'component');

        return $this->get('engine')->create($view_file, $response)->render();
    }

    public function loadModuleController($module, $view = '', $options = [])
    {
        $module    = $this->sanitize($module, 'module');
        $signature = 'mod'.$module;

        if (!$this->has($signature)) {
            $url  = $this->getPath($module, 'module');
            $path = $url['path'];
            $url  = $url['url'];

            $name       = ucfirst($module);
            $class_name = 'Module';

            //include Controller
            $file = $path.'modules'.DS.$module.DS.$class_name.'.php';

            if (!file_exists($file)) {
                throw new Exception($module.' not found');
            }

            $class = 'Modules\\'.$name.'\\'.$class_name;

            require_once $file;

            $instance = new $class();
            $instance->setContainer($this->di);

            $assets = $url.'modules/'.$module.'/assets/';
            $this->set($signature, $instance);
            $this->set($signature.'.assets', $assets);
        } else {
            $assets   = $this->get($signature.'.assets');
            $instance = $this->get($signature);
        }

        $this->setPath($assets);

        if ($options['position']) {
            $instance->position = $options['position'];
        }
        //check any beforeRender method
        $beforeRender = 'beforeRender';
        if (method_exists($instance, $beforeRender)) {
            $instance->$beforeRender();
        }

        if ($view && (method_exists($instance, $view) || method_exists($instance, '__call'))) {
            $response = $instance->$view($options);
        } else {
            $response = $instance->index($options);
        }

        return $response;
    }

    /**
     * Used to include module.
     *
     * @param string            $module_name
     * @param string (optional) $view        (load file if any view type files)
     **/
    public function module($option, $view = '', $options = [], $iscustom = true)
    {
        if (empty($option)) {
            return;
        }

        //load index method if module is custom
        if ($option == 'custom') {
            if ($iscustom === true) {
                $data = $this->database->find('#__core_modules', 'first', [
                    'fields'     => ['config'],
                    'conditions' => [
                        'module'   => $option,
                        'mod_view' => $view,
                        ],
                    ]
                );

                if ($data['config']) {
                    $options = json_decode($data['config'], true);
                    $options = $options['custom'];
                }
            }
            $view = '';
        }

        $response = $this->loadModuleController($option, $view, $options);

        if (!is_array($response)) {
            $response = [];
        }

        $view_file = $this->findView($option.'.'.$view, 'module');

        echo $this->get('engine')->create($view_file, $response)->render();
    }

    /**
     * Count number of modules in perticular position of the template.
     *
     * @param string $position
     *
     * @return int
     **/
    public function countModules($position)
    {
        if (!$position) {
            return false;
        }

        $position = explode(',', $position);

        return $this->modules($position, true);
    }

    /**
     * Include modules in template based on position.
     *
     * @param string $position
     **/
    public function modules($position, $count = false)
    {
        $themeid   = $this->get('themeid');
        $option    = $this->get('option');
        $view      = $this->get('view');
        $logged_in = $this->get('is_user_logged_in');

        if (!$position) {
            return false;
        }

        $conditions = [];
        $joins      = [];

        $joins[] = [
            'table'      => '#__core_modules',
            'alias'      => 'm',
            'type'       => 'INNER',
            'conditions' => [
                'tm.module_id = m.module_id',
                'm.status' => 1,
                ],
            ];

        if (!empty($option)) {
            $conditions[] = ['c.com_view' => $view];
            $joins[]      = [
                'table'      => '#__core_components',
                'alias'      => 'c',
                'type'       => 'INNER',
                'conditions' => [
                    'c.component_id = tm.component_id',
                    'c.component' => $option,
                ],
            ];
        }
        $conditions[] = ['tm.status' => 1];
        if ($themeid) {
            $conditions[] = ['tm.template_id' => $themeid];
        }
        if (empty($option)) {
            $conditions[] = ['tm.component_id' => 1];
        }
        $conditions[] = ['tm.position' => $position];
        if (!$logged_in) {
            $conditions[] = ['m.access <> 1'];
        } else {
            $conditions[] = ['m.access <> 2'];
        }

        $res = $this->database->find('#__core_template_modules', ($count) ? 'count' : 'all', [
            'joins'      => $joins,
            'alias'      => 'tm',
            'conditions' => $conditions,
            'fields'     => ['m.*'],
            'order'      => ['tm.ordering'],
            ]
        );

        if ($count) {
            return $res;
        }

        $rows = count($res);

        $modules = $this->_modules[$position];

        if (is_array($modules)) {
            foreach ($modules as $module) {
                $opt             = $module['options'];
                $opt['position'] = $position;
                $this->module($module['module'], $module['view'], $opt, $module['iscustom']);
            }
        }

        if ($rows == 0) {
            return false;
        }

        foreach ($res as $data) {
            if ($data['showTitle'] == 1) {
                echo '<div class="ui-module-heading">'.$data['title'].'</div>';
            }
            $options = [];
            if ($data['config']) {
                $opt     = json_decode($data['config'], true);
                $options = $opt['custom'];
            }
            unset($opt);
            $options['position'] = $position;

            $this->module($data['module'], $data['mod_view'], $options, false);
        }
    }

    /**
     * Used to include helper.
     *
     * @param string     $helper
     * @param (optional) $component
     **/
    public function helper($helperName)
    {
        $signature = 'helper'.strtolower($helperName);

        if ($this->has($signature)) {
            return $this->get($signature);
        }

        $helperName = explode('.', $helperName);
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

            $paths[] = [
                'file'  => $dir.'components'.DS.$component.DS.'helpers'.DS.(($group) ? $group.DS : '').$helperClass.'.php',
                'class' => 'Components\\'.ucfirst($component).'\\Helpers\\'.$helperClass,
            ];
        } else {
            $paths[] = [
                'file'  => APP.'system'.DS.'helpers'.DS.$helperClass.'.php',
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
                require_once $path['file'];
            } elseif (class_exists($path['class'])) {
                $exists = true;
            }

            if ($exists) {
                $helperClass = $path['class'];
                $beforeRun   = 'beforeRun';
                $instance    = new $helperClass($this->di);
                //$instance->setContainer();

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
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

        $signature = 'widget'.strtolower($name);

        if (!$this->has($signature)) {
            $widgetName = explode(':', $name);
            $component  = $widgetName[1];

            $widget1 = explode('.', $widgetName[0]);
            $widget  = strtolower($widget1[0]);
            $view    = $widget1[1];

            $widgetClass = ($view) ? ucfirst($view) : ucfirst($widget1[0]);

            if ($component) {
                $component = $this->sanitize($component);
                $url       = $this->getPath($component);
                $dir       = $url['path'];
                $url       = $url['url'];

                $paths[] = [
                    'file'  => $dir.'components'.DS.$component.DS.'widgets'.DS.$widget.DS.$widgetClass.'.php',
                    'class' => 'Components\\'.ucfirst($component).'\\Widgets\\'.$widgetClass,
                    'url'   => $url.'components/'.$component.'/widgets/'.$widget.'/assets/',
                ];
            } else {
                $class = 'System\Widgets\\'.$widgetClass;

                $paths[] = [
                    'file'  => APP.'system'.DS.'widgets'.DS.$widget.DS.$widgetClass.'.php',
                    'class' => $class,
                    'url'   => _APP_URL.'system/widgets/'.$widget.'/assets/',
                ];

                $paths[] = [
                    'file'  => SYS.'system'.DS.'widgets'.DS.$widget.DS.$widgetClass.'.php',
                    'class' => $class,
                    'url'   => _SYSURL.'system/widgets/'.$widget.'/assets/',
                ];

                $paths[] = [
                    'file'  => APP.'system'.DS.'widgets'.DS.$widgetClass.'.php',
                    'class' => $class,
                    'url'   => _APP_URL.'system/widgets/assets/',
                ];

                $paths[] = [
                    'file'  => SYS.'system'.DS.'widgets'.DS.$widgetClass.'.php',
                    'class' => $class,
                    'url'   => _SYSURL.'system/widgets/assets/',
                ];
            }

            foreach ($paths as $path) {
                if (file_exists($path['file'])) {
                    $widgetClass = $path['class'];

                    require_once $path['file'];

                    $assets   = $path['url'];
                    $instance = new $widgetClass();
                    $instance->setContainer($this->di);

                    $this->set($signature, $instance);
                    $this->set($signature.'.url', $assets);

                    break;
                }
            }
        } else {
            $instance = $this->get($signature);
            $assets   = $this->get($signature.'.url');
        }

        if (!$instance) {
            throw new \Exception("Widget '".$name."' not found", 1);
        }

        $this->setPath($assets);

        $beforeRun = 'beforeRun';
        $afterRun  = 'afterRun';

        if (empty($options['selector'])) {
            $oldOptions          = $options;
            $options             = [];
            $options['options']  = $oldOptions;
            $options['selector'] = '.'.strtolower(str_replace('.', '-', $name));
        }

        $instance = $this->get($signature);
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
            require_once $file;
        }

        $controller = new $component($this->di);

        $beforeRender = 'beforeRender';

        if (method_exists($controller, 'beforeRender')) {
            $controller->$beforeRender();
        }

        if ($method && method_exists($controller, $method)) {
            $controller->{$method}();
        } else {
            $controller->index();
        }

        return $controller;
    }

    public function conditions(&$data = [], $alias = null)
    {
        $conditions = [];

        if ($alias) {
            $alias = $alias.'.';
        }

        $filter = $data['dfilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($data[$k] != '') {
                    $conditions[] = [$alias.$v.' like ' => '%'.$data[$k].'%'];
                }
            }
        }

        $filter = $data['filter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$k => $v];
                }
            }
        }

        $filter = $data['lfilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$k.' like ' => '%'.$v.'%'];
                }
            }
        }

        $filter = $data['rfilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$k.' like ' => $v.'%'];
                }
            }
        }

        $date_range = $data['date_range'];
        if (is_array($date_range)) {
            foreach ($date_range as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && $to) {
                    $conditions[] = ['DATE('.$alias.$k.') BETWEEN ? AND ? ' => [
                        $this->toTime($from, true),
                        $this->toTime($to, true),
                        ],
                    ];
                }

                if ($from && empty($to)) {
                    $conditions[] = ['DATE('.$alias.$k.')' => $this->toTime($from, true)];
                }
            }
        }

        $time_range = $data['time_range'];
        if (is_array($time_range)) {
            foreach ($time_range as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && $to) {
                    $conditions[] = ['DATE(FROM_UNIXTIME('.$alias.$k.')) BETWEEN ? AND ? ' => [
                        $this->toTime($from, true),
                        $this->toTime($to, true),
                        ],
                    ];
                }

                if ($from && empty($to)) {
                    $conditions[] = ['DATE(FROM_UNIXTIME('.$alias.$k.'))' => $this->toTime($from, true)];
                }
            }
        }

        $date_range = $data['unix_range'];
        if (is_array($date_range)) {
            foreach ($date_range as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && empty($to)) {
                    $to = $from;
                }

                if (!is_numeric($from)) {
                    $from = $this->toTime($from.' 00:00:00');
                }

                if (!is_numeric($to)) {
                    $to = $this->toTime($to.' 23:59:59');
                }

                $conditions[] = [($alias.$k).' BETWEEN ? AND ? ' => [$from, $to]];
            }
        }

        $ranges = $data['range'];
        if (is_array($ranges)) {
            foreach ($ranges as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && $to) {
                    $conditions[] = [($alias.$k).' BETWEEN ? AND ? ' => [$from, $to]];
                }

                if ($from && empty($to)) {
                    $conditions[] = [($alias.$k) => $from];
                }
            }
        }

        return $conditions;
    }

    public function ordering(&$data = [], $ordering = [])
    {
        if ($data['sort']) {
            $ordering = [$data['sort'].' '.$data['order']];
        }

        return $ordering;
    }
}
