<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Module\System;

use Pi;
use Pi\Application\Bootstrap\Resource\AdminMode;

/**
 * Module admin menu handler
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Menu
{
    /**
     * Get admin mode list
     *
     * @param null|string $mode
     *
     * @return array
     */
    public static function modes($mode = null)
    {
        $modes = array(
            array(
                'name'  => AdminMode::MODE_ACCESS,
                'label' => __('Operation', 'usr'),
                'icon'  => 'fa fa-wrench',
                //'link'  => '',
            ),
            array(
                'name'  => AdminMode::MODE_ADMIN,
                'label' => __('Setting', 'usr'),
                'icon'  => 'fa fa-cogs',
                //'link'  => '',
            ),
            array(
                'name'  => AdminMode::MODE_DEPLOYMENT,
                'label' => __('Deployment', 'usr'),
                'icon'  => 'fa fa-cloud-upload',
                'link'  => '',
            ),
        );
        foreach ($modes as $key => &$config) {
            $config['active'] = ($mode == $config['name']) ? 1 : 0;
            if (isset($config['link'])) {
                continue;
            }
            $config['link'] = Pi::service('url')->assemble('admin', array(
                'module'        => 'system',
                'controller'    => 'dashboard',
                'action'        => 'mode',
                'mode'          => $config['name'],
            ));
        }

        return $modes;
    }

    /**
     * Load side main menu for operations
     *
     * @param string $module
     *
     * @return array
     */
    public static function mainOperation($module)
    {
        $mode   = AdminMode::MODE_ACCESS;

        $modules = Pi::registry('modulelist')->read();
        $modulesAllowed = Pi::service('permission')->moduleList($mode);
        $navConfig = array();

        foreach ($modules as $name => $item) {
            if (in_array($name, $modulesAllowed)) {
                $link = Pi::service('url')->assemble('admin', array(
                    'module'        => 'system',
                    'controller'    => 'menu',
                    'action'        => 'sub',
                    'name'          => $name,
                ));

                $config = array(
                    'name'      => $name,
                    'label'     => $item['title'],
                    'href'      => $link,
                    'active'    => $name == $module ? 1 : 0,
                    'icon'      => '',
                );
            }
            $navConfig[] = $config;
        }

        return $navConfig;
    }

    /**
     * Get side main menu for management
     *
     * @param string $module
     * @param string $component
     *
     * @return array
     */
    public static function mainComponent($module, $component)
    {
        $mode   = AdminMode::MODE_ADMIN;
        $modules = Pi::registry('modulelist')->read();
        $modulesAllowed = Pi::service('permission')->moduleList($mode);
        $navConfig = array();
        foreach ($modules as $name => $item) {
            if (in_array($name, $modulesAllowed)) {
                $link = Pi::service('url')->assemble('admin', array(
                    'module'        => 'system',
                    'controller'    => $component,
                    'name'          => $name,
                ));

                $config = array(
                    'name'      => $name,
                    'label'     => $item['title'],
                    'href'      => $link,
                    'active'    => $name == $module ? 1 : 0,
                    'icon'      => '',
                );
            }
            $navConfig[] = $config;
        }

        return $navConfig;
    }

    /**
     * Load module component sub menu
     *
     * @param string $module
     * @param string $class UL element class
     *
     * @return string
     */
    public static function subComponent($module, $class = '')
    {
        $navConfig = Pi::registry('navigation')->read('system-component');
        foreach ($navConfig as $key => &$nav) {
            $nav['params']['name'] = $module;
        }
        $helper     = Pi::service('view')->getHelper('navigation');
        $navigation = $helper($navConfig);
        $class      = $class ?: 'nav';
        $content    = $navigation->menu()->setUlClass($class)->render();

        return $content;
    }

    /**
     * Load module admin sub menu
     *
     * @param string $module
     * @param string $class UL element class
     *
     * @return string
     */
    public static function subOperation($module, $class = '')
    {
        $helper = Pi::service('view')->getHelper('navigation');
        $navigation = $helper(
            $module . '-admin',
            array('section' => 'admin')
        );
        $class = $class ?: 'nav';
        $content = $navigation->menu()->setUlClass($class)->render();

        return $content;
    }
}