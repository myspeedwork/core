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

use Exception;
use Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Application extends Controller
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

    public function sanitize($option, $type = 'component')
    {
        $option = strtolower($option);

        if ($type == 'module') {
            return (!empty($option) && strpos($option, 'mod_') === false) ? 'mod_'.$option : $option;
        }

        return (!empty($option) && strpos($option, 'com_') === false && strpos($option, 'mod_') === false) ? 'com_'.$option : $option;
    }

    public function url($option, $type = 'component')
    {
        $url = $this->getPath($option, $type);

        return $url['url'];
    }

    public function getPath($option, $type = 'component')
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

    public function loadController($component, $view = '', $options = [], $instance = false)
    {
        if (!$component) {
            return false;
        }

        static $instances;
        if (!isset($instances)) {
            $instances = [];
        }

        $component = $this->sanitize($component);
        $signature = 'Com'.$component;

        $url  = $this->getPath($component);
        $path = $url['path'];
        $url  = $url['url'];

        if (empty($instances[$signature])) {
            $component_name = ucfirst(str_replace('com_', '', $component));
            $class_name     = $component_name.'Controller';

            //include Controller
            $file = $path.'components'.DS.$component.DS.$class_name.'.php';

            if (!file_exists($file)) {
                throw new \Exception('Controller name '.$component.' not found', 1);
            }

            $this->loadModel($component);

            $class_name = 'Components\\'.$component_name.'\\'.$class_name;
            //check whether this is already loaded
            if (!class_exists($class_name)) {
                require_once $file;
            }

            $instances[$signature] = new $class_name();
        }

        if ($instance === 2) {
            return $instances[$signature];
        }

        $controller   = $instances[$signature];

        $method = ($view) ? $view : 'index';
        $method = strtolower($method);

        Template::$path = $url.'components/'.$component.'/assets/';

        $beforeRender = 'beforeRender';

        if (method_exists($controller, $beforeRender)) {
            $controller->$beforeRender();
        }

        if ($instance === true) {
            return $controller;
        }

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

    public function loadModel($component, $model = '')
    {
        static $instances;
        if (!isset($instances)) {
            $instances = [];
        }

        $component = $this->sanitize($component);

        $signature = 'Mod'.$component.$model;

        $url  = $this->getPath($component);
        $path = $url['path'];

        if (empty($instances[$signature])) {
            $component_name = ucfirst(str_replace('com_', '', $component));
            $class_name     = $component_name.'Model';

            $model_file = $path.'components'.DS.$component.DS.$class_name.'.php';

            if (!file_exists($model_file)) {
                throw new \Exception('Model '.$component.' not found', 1);
            }

            $class_name = 'Components\\'.$component_name.'\\'.$class_name;

            if (!class_exists($class_name)) {
                include $model_file;
            }

            $component = str_replace('com_', '', $component);

            $this->$component = new $class_name();

            $instances[$signature] = $this->$component;
            Registry::set($component, $this->$component);
        }

        $beforeRender = 'beforeRender';
        if (method_exists($instances[$signature], 'beforeRender')) {
            $instances[$signature]->$beforeRender();
        }

        return $instances[$signature];
    }

    public function loadView($component, $view = '', $type = 'component')
    {
        $type        = (empty($type)) ? 'component' : $type;
        $component   = $this->sanitize($component, $type);
        $folder      = ($type == 'component') ? 'components' : 'modules';

        $url     = $this->getPath($component, $type);
        $path    = $url['path'];

        $view     = strtolower(trim($view));
        $views    = [];
        $views[]  = _TMP_PATH.$folder.DS.$component.DS.(($view) ? $view : 'index').'.tpl';
        $views[]  = $path.$folder.DS.$component.DS.'views'.DS.(($view) ? $view : 'index').'.tpl';

        $view_file = '';
        foreach ($views as $file) {
            if (file_exists($file)) {
                $view_file =  $file;
                break;
            }
        }

        if (empty($view_file)) {
            return;
        }

        return Registry::get('tengine')->fetch($view_file);
    }

    public function requestApi($component)
    {
        static $instances;
        if (!isset($instances)) {
            $instances = [];
        }

        $component = $this->sanitize($component);

        $signature = 'Api'.$component;

        $url  = $this->getPath($component);
        $path = $url['path'];

        if (empty($instances[$signature])) {
            $component_name = ucfirst(str_replace('com_', '', $component));
            $class_name     = $component_name.'Api';

            $model_file = $path.'components'.DS.$component.DS.$class_name.'.php';

            if (!file_exists($model_file)) {
                return ['A400' => 'Api Not Implemented'];
            }

            $class_name = 'Components\\'.$component_name.'\\'.$class_name;

            if (!class_exists($class_name)) {
                include $model_file;
            }

            $component = str_replace('com_', '', $component);

            try {
                $component = new $class_name();
            } catch (\Exception $e) {
                return ['A400A' => 'Api Not Implemented'];
            }

            $instances[$signature] = $component;
        }

        $beforeRender = 'beforeRender';
        if (method_exists($instances[$signature], 'beforeRender')) {
            $instances[$signature]->$beforeRender();
        }

        return $instances[$signature];
    }

    public function requestController($component, $options = [])
    {
        return $this->loadController($component, '', $options, 2);
    }

    public function requestModel($component, $model = '')
    {
        return $this->loadModel($component, $model);
    }

    public function requestLayout($component, $view = '', $type = 'component')
    {
        $type      = (empty($type)) ? 'component' : $type;
        $component = $this->sanitize($component, $type);
        $type      = ($type == 'component') ? 'components' : 'modules';

        $url  = $this->getPath($component);
        $path = $url['path'];

        $view    = strtolower(trim($view));
        $views   = [];
        $views[] = _TMP_PATH.$type.DS.$component.DS.(($view) ? $view : 'index').'.tpl';
        $views[] = $path.$type.DS.$component.DS.'views'.DS.(($view) ? $view : 'index').'.tpl';

        $url     = $this->getPath('errors');
        $path    = $url['path'];
        $views[] = $path.'components'.DS.'com_errors'.DS.'views'.DS.'error.tpl';

        foreach ($views as $file) {
            if (file_exists($file)) {
                return $file;
                break;
            }
        }
    }

    public function requestAction($option, $view = null, &$options = [])
    {
        echo $this->component($option, $view, $options);
    }

    public function component($option, $view = null, $options = [])
    {
        $response = $this->loadController($option, $view, $options);
        if (is_array($response)) {
            foreach ($response as $key => $value) {
                $this->assign($key, $value);
            }
        }

        return $this->loadView($option, $view);
    }

    public function loadModuleController($module, $view = '', &$options = [])
    {
        static $instances;
        if (!isset($instances)) {
            $instances = [];
        }

        $module       = $this->sanitize($module, 'module');
        $signature    = 'Mod'.$module;

        if (empty($instances[$signature])) {
            $url  = $this->getPath($module, 'module');
            $path = $url['path'];
            $url  = $url['url'];

            $module_name = ucfirst(str_replace('mod_', '', $module));
            $class_name  = $module_name.'Module';

            //include Controller
            $file = $path.'modules'.DS.$module.DS.$class_name.'.php';

            if (!file_exists($file)) {
                throw new Exception($module.' not found');
            }

            $class_name = 'Modules\\'.$module_name.'\\'.$class_name;

            $instances[$signature]['path'] = $url;

            require_once $file;

            $instances[$signature]['object'] = new $class_name();
        }

        Template::$path = $instances[$signature]['path'].'modules/'.$module.'/assets/';

        $action = &$instances[$signature]['object'];

        if ($options['position']) {
            $action->position = $options['position'];
        }
        //check any beforeRender method
        $beforeRender = 'beforeRender';
        if (method_exists($action, $beforeRender)) {
            $action->$beforeRender();
        }

        if ($view && (method_exists($action, $view) || method_exists($action, '__call'))) {
            $response = $action->$view($options);
        } else {
            $response = $action->index($options);
        }

        return $response;
    }

    public function addModule($position, $module, $view = '', &$options = [], $iscustom = false)
    {
        $this->_modules[$position][] = [
            'order'    => 1,
            'module'   => $module,
            'view'     => $view,
            'options'  => $options,
            'iscustom' => $iscustom,
        ];
    }
    /**
     * Used to include module.
     *
     * @param string            $module_name
     * @param string (optional) $view        (load file if any view type files)
     **/
    public function module($module, $view = '', &$options = [], $iscustom = true)
    {
        if (empty($module)) {
            return;
        }

        if ($options['title'] == true) {
            echo '<div class="ui-module-menu ac-ajax-module '.$module.' '.$view.'">';
        }

        //load index method if module is custom
        if ($module == 'mod_custom') {
            if ($iscustom === true) {
                $data = $this->database->find('#__core_modules', 'first', [
                    'fields'     => ['config'],
                    'conditions' => [
                        'module'   => $module,
                        'mod_view' => $view,
                        ],
                    ]
                );

                if ($data['config']) {
                    $options  = json_decode($data['config'], true);
                    $options  = $options['custom'];
                }
            }
            $view = '';
        }

        $response = $this->loadModuleController($module, $view, $options);

        if (is_array($response)) {
            foreach ($response as $key => $value) {
                $this->assign($key, $value);
            }
        }

        echo $this->loadView($module, $view, 'module');

        if ($options['title'] == true) {
            echo '</div>';
        }
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
        $position = @implode("','", $position);

        return $this->modules($position, true);
    }

    /**
     * Include modules in template based on position.
     *
     * @param string $position
     **/
    public function modules($position, $count = false)
    {
        $themeid   = Registry::get('themeid');
        $option    = Registry::get('option');
        $view      = Registry::get('view');
        $logged_in = Registry::get('is_user_logged_in');

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
            if (empty($view)) {
                $conditions[] = ['c.com_view' => ''];
            } else {
                $conditions[] = ['c.com_view' => $view];
            }
            $joins[] = [
                'table'      => '#__core_components',
                'alias'      => 'c',
                'type'       => 'INNER',
                'conditions' => ['c.component_id = tm.component_id','c.component' => Registry::get('option')],
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

        $rows  = count($res);

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
                $opt      = json_decode($data['config'], true);
                $options  = $opt['custom'];
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
        static $instances;

        if (!isset($instances)) {
            $instances = [];
        }

        $signature    = 'Helper'.strtolower($helperName);

        if ($instances[$signature]) {
            return $instances[$signature];
        }

        $helperName    = explode('.', $helperName);
        $component     = $helperName[1];

        $component        = explode(':', $component);
        $group            = $component[1];
        $component        = $component[0];

        $helper   = $helperName[0];

        $paths       = [];
        $helperClass = ucfirst($helper).'Helper';

        if ($component) {
            $component    = $this->sanitize($component);
            $url          = $this->getPath($component);
            $dir          = $url['path'];

            $paths[] = [
                'file'  => $dir.'components'.DS.$component.DS.'helpers'.DS.(($group) ? $group.DS : '').$helperClass.'.php',
                'class' => 'Components\\'.ucfirst(str_replace('com_', '', $component)).'\\Helpers\\'.$helperClass,
                ];
        } else {
            $paths[] = [
                'file'  => APP.'system'.DS.'helpers'.DS.$helperClass.'.php',
                'class' => 'System\Helpers\\'.$helperClass,
                ];

            $paths[] = [
                'file'  => SYS.'system'.DS.'helpers'.DS.$helperClass.'.php',
                'class' => 'System\\Helpers\\'.$helperClass,
                ];
        }

        foreach ($paths as $path) {
            if (file_exists($path['file'])) {
                $helperClass = $path['class'];

                require_once $path['file'];

                $beforeRun   = 'beforeRun';
                $instance    = new $helperClass();

                if (method_exists($instance, $beforeRun)) {
                    $instance->$beforeRun();
                }

                $instances[$signature] = &$instance;
                break;
            }
        }

        return $instances[$signature];
    }

    /**
     * Include widget.
     *
     * @param string     $widget
     * @param (optional) $component
     **/
    public function widget($name, $options = [], $includeOnly = false)
    {
        static $instances;

        if (!isset($instances)) {
            $instances = [];
        }

        $signature  = 'Widget'.strtolower($name);

        if (!$instances[$signature]) {
            $widgetName   = explode(':', $name);
            $component    = $widgetName[1];

            $widget1      = explode('.', $widgetName[0]);
            $widget       = $widget1[0];
            $view         = $widget1[1];

            $widgetClass   = ($view) ? ucfirst($view).'Widget' : ucfirst($widget).'Widget';

            if ($component) {
                $component    = $this->sanitize($component);
                $url          = $this->getPath($component);
                $dir          = $url['path'];
                $url          = $url['url'];

                $paths[] = [
                    'file'  => $dir.'components'.DS.$component.DS.'widgets'.DS.$widget.DS.$widgetClass.'.php',
                    'class' => 'Components\\'.ucfirst(str_replace('com_', '', $component)).'\\Widgets\\'.$widgetClass,
                    'url'   => $url.'components/'.$component.'/widgets/'.$widget.'/assets/',
                ];

                $paths[] = [
                    'file'  => $dir.'components'.DS.$component.DS.'widgets'.DS.$widgetClass.'.php',
                    'class' => 'Components\\'.ucfirst(str_replace('com_', '', $component)).'\\Widgets\\'.$widgetClass,
                    'url'   => $url.'components/'.$component.'/widgets/assets/',
                ];
            } else {
                $paths[] = [
                    'file'  => APP.'system'.DS.'widgets'.DS.$widget.DS.$widgetClass.'.php',
                    'class' => 'System\Widgets\\'.$widgetClass,
                    'url'   => _APP_URL.'system/widgets/'.$widget.'/assets/',
                ];

                $paths[] = [
                    'file'  => SYS.'system'.DS.'widgets'.DS.$widget.DS.$widgetClass.'.php',
                    'class' => 'System\\Widgets\\'.$widgetClass,
                    'url'   => _SYSURL.'system/widgets/'.$widget.'/assets/',
                ];

                $paths[] = [
                    'file'  => APP.'system'.DS.'widgets'.DS.$widgetClass.'.php',
                    'class' => 'System\Widgets\\'.$widgetClass,
                    'url'   => _APP_URL.'system/widgets/assets/',
                ];

                $paths[] = [
                    'file'  => SYS.'system'.DS.'widgets'.DS.$widgetClass.'.php',
                    'class' => 'System\\Widgets\\'.$widgetClass,
                    'url'   => _SYSURL.'system/widgets/assets/',
                ];
            }

            foreach ($paths as $path) {
                if (file_exists($path['file'])) {
                    $widgetClass = $path['class'];

                    require_once $path['file'];

                    $instances[$signature]['object'] = new $widgetClass();
                    $instances[$signature]['url']    = $path['url'];

                    break;
                }
            }
        }

        $instance       = $instances[$signature]['object'];
        Template::$path = $instances[$signature]['url'];

        $beforeRun   = 'beforeRun';
        $afterRun    = 'afterRun';

        if (!is_array($options['options'])) {
            $options['options'] = [];
        }

        if (empty($options['selector'])) {
            $options['selector'] = '.'.str_replace('.', '-', $name);
        }

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

        $controller = new $component();

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
            foreach ($date_range as $k => $v) {
                $date = explode('-', $v);
                $from = trim($date[0]);
                $to   = trim($date[1]);
                if ($from && $to) {
                    $conditions[] = ['DATE('.$alias.$k.') BETWEEN ? AND ? ' => [
                        Utility::strtotime($from, true),
                        Utility::strtotime($to, true),
                        ],
                    ];
                }

                if ($from && empty($to)) {
                    $conditions[] = ['DATE('.$alias.$k.')' => Utility::strtotime($from, true)];
                }
            }
        }

        $time_range = $data['time_range'];
        if (is_array($time_range)) {
            foreach ($time_range as $k => $v) {
                $date = explode('-', $v);
                $from = trim($date[0]);
                $to   = trim($date[1]);
                if ($from && $to) {
                    $conditions[] = ['DATE(FROM_UNIXTIME('.$alias.$k.')) BETWEEN ? AND ? ' => [
                        Utility::strtotime($from, true),
                        Utility::strtotime($to, true),
                        ],
                    ];
                }

                if ($from && empty($to)) {
                    $conditions[] = ['DATE(FROM_UNIXTIME('.$alias.$k.'))' => Utility::strtotime($from, true)];
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
