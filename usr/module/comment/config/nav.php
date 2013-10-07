<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

return array(
    'front' => array(
        'list'      => array(
            'route'         => 'comment',
            'label'         => _t('All comments'),
            'controller'    => 'list',
            'action'        => 'index',
        ),
        'my-post'   => array(
            'route'         => 'comment',
            'label'         => _t('My comments'),
            'controller'    => 'list',
            'action'        => 'user',
            'params'        => array(
                'my'    => 1,
            ),
        ),
        'my-article'    => array(
            'route'         => 'comment',
            'label'         => _t('My articles'),
            'controller'    => 'list',
            'action'        => 'article',
            'params'        => array(
                'my'    => 1,
            ),
        ),
    ),
    'admin'   => array(
        'portal'     => array(
            'route'         => 'admin',
            'label'         => _t('Portal'),
            'controller'    => 'index',
            'action'        => 'index',
        ),
        'category'     => array(
            'route'         => 'admin',
            'label'         => _t('Categories'),
            'controller'    => 'category',
            'action'        => 'index',
        ),
        'list'     => array(
            'route'         => 'admin',
            'label'         => _t('Posts'),
            'controller'    => 'list',
            'action'        => 'index',

            'pages'         => array(
                'active'     => array(
                    'route'         => 'admin',
                    'label'         => _t('Active posts'),
                    'controller'    => 'list',
                    'action'        => 'index',
                    'params'        => array(
                        'active'    => 1,
                    ),
                ),
                'inactive'     => array(
                    'route'         => 'admin',
                    'label'         => _t('Inactive posts'),
                    'controller'    => 'list',
                    'action'        => 'index',
                    'params'        => array(
                        'active'    => 0,
                    ),
                ),
            ),
        ),
        'module'     => array(
            'route'         => 'admin',
            'label'         => _t('By module'),
            'controller'    => 'list',
            'action'        => 'module',
        ),
        'user'     => array(
            'route'         => 'admin',
            'label'         => _t('By user'),
            'controller'    => 'list',
            'action'        => 'user',
        ),
    ),
);
