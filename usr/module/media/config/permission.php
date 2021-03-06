<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link         http://code.pialog.org for the Pi Engine source repository
 * @copyright    Copyright (c) Pi Engine http://pialog.org
 * @license      http://pialog.org/license.txt New BSD License
 */

/**
 * Permission config
 * 
 * @author Zongshu Lin <lin40553024@163.com>
 */
return array(
    'admin'           => array(
        'application' => array(
            'title'       => _t('Application management'),
        ),
        'list'        => array(
            'title'       => _t('List management'),
        ),
        'stats'       => array(
            'title'       => _t('Statistics management'),
        ),
        
        'custom'      => 'Module\Media\Permission',
    ),
);
