<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Traits;

trait ModuleResolverTrait
{
    public function loadModuleController($name, $options = [])
    {
        list($option, $view) = explode('.', $name);

        $option    = $this->sanitize($option);
        $signature = 'mod'.$option;

        if (!$this->has($signature)) {
            $namespace = $this->getNameSpace($option, 'module');
            $class     = $namespace.'Module';

            if (!class_exists($class)) {
                throw new \Exception('Module '.$class.' not found');
            }

            $instance = new $class();
            $instance->setContainer($this->getContainer());

            $this->set($signature, $instance);
        } else {
            $instance = $this->get($signature);
        }

        if ($options['position']) {
            $instance->position = $options['position'];
        }
        //check any beforeRender method
        $beforeRender = 'beforeRender';
        if (method_exists($instance, $beforeRender)) {
            $instance->$beforeRender();
        }

        if ($view) {
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
    public function module($name, $options = [], $iscustom = true)
    {
        if (empty($name)) {
            return;
        }

        list($option, $view) = explode('.', $name);

        //load index method if module is custom
        if ($option == 'custom') {
            if ($iscustom === true) {
                $data = $this->database->find(
                    '#__core_modules', 'first', [
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
            $name = rtrim($name, '.'.$view);
        }

        $response = $this->loadModuleController($name, $options);

        if (!is_array($response)) {
            $response = [];
        }

        $view_file = $this->findView($name, 'module');

        return $this->get('view.engine')->create($view_file, $response)->render();
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
        ]);

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
}
