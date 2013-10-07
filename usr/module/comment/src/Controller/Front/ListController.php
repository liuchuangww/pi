<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Module\Comment\Controller\Front;

use Pi;
use Pi\Mvc\Controller\ActionController;
use Pi\Paginator\Paginator;

/**
 * Comment list controller
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class ListController extends ActionController
{
    /**
     * All comment posts
     */
    public function indexAction()
    {
        /*
        $active = _get('active');
        //vd($active);
        if (null !== $active) {
            $active = (int) $active;
        }
        */
        $active = 1;
        $page   = _get('page', 'int') ?: 1;
        $limit  = $this->config('list_limit') ?: 10;
        $offset = ($page - 1) * $limit;

        $posts = Pi::api('comment')->getList(
            array('active' => $active),
            $limit,
            $offset
        );
        $renderOptions = array(
            'operation' => $this->config('display_operation'),
        );
        $posts = Pi::api('comment')->renderList($posts, $renderOptions);
        $count = Pi::api('comment')->getCount(array('active' => $active));

        //$params = (null === $active) ? array() : array('active' => $active);
        $paginator = Paginator::factory($count, array(
            'page'          => $page,
            /*
            'url_options'   => array(
                'params'    => $params,
            ),
            */
        ));
        if (null === $active) {
            $title = __('All comment posts');
        } elseif (!$active) {
            $title = __('All inactive comment posts');
        } else {
            $title = __('All active comment posts');
        }
        $this->view()->assign('comment', array(
            'title'     => $title,
            'count'     => $count,
            'posts'     => $posts,
            'paginator' => $paginator,
        ));

        $this->view()->setTemplate('comment-list');
    }

    /**
     * List of comment posts of a root
     *
     * @return string
     */
    public function rootAction()
    {
        $root   = _get('root', 'int') ?: 1;
        $page   = _get('page', 'int') ?: 1;
        $limit  = $this->config('list_limit') ?: 10;
        $offset = ($page - 1) * $limit;
        $posts  = Pi::api('comment')->getList($root, $limit, $offset);
        $renderOptions = array(
            'operation' => $this->config('display_operation'),
        );
        $posts = Pi::api('comment')->renderList($posts, $renderOptions);
        $count = Pi::api('comment')->getCount($root);

        $target = Pi::api('comment')->getTarget($root);

        $paginator = Paginator::factory($count, array(
            'page'  => $page,
            'url_options'           => array(
                'params'        => array(
                    'root'      => $root,
                ),
            ),
        ));
        $title = sprintf(__('Comment posts of %s'), $target['title']);
        $this->view()->assign('comment', array(
            'title'     => $title,
            'root'      => $root,
            'target'    => $target,
            'count'     => $count,
            'posts'     => $posts,
            'paginator' => $paginator,
        ));

        $this->view()->setTemplate('comment-root');
    }

    /**
     * Active comment posts of a user
     */
    public function userAction()
    {
        $my = _get('my', 'int');
        if ($my) {
            $uid    = Pi::user()->getIdentity();
            if (!$uid) {
                $redirect = $this->getRequest()->getRequestUri();
                $url = Pi::service('user')->getUrl('login', $redirect);
                $this->jump($url, __('Please login.'));
            }
            $active = _get('active');
            if (null !== $active) {
                $active = (int) $active;
            }
            $opOptions = array(
                'admin' => false,
            );
        } else {
            $uid        = _get('uid', 'int') ?: Pi::user()->getIdentity();
            $active     = 1;
            $opOptions  = $this->config('display_operation');
        }
        $page   = _get('page', 'int') ?: 1;
        $limit  = $this->config('list_limit') ?: 10;
        $offset = ($page - 1) * $limit;

        $where = array('uid' => $uid, 'active' => $active);
        $posts = Pi::api('comment')->getList(
            $where,
            $limit,
            $offset
        );
        $renderOptions = array(
            'user'      => false,
            'operation' => $opOptions,
        );
        $posts = Pi::api('comment')->renderList($posts, $renderOptions);
        $count = Pi::api('comment')->getCount($where);

        if ($my) {
            $params = array('my' => 1);
        } else {
            $params = $where;
        }
        $paginator = Paginator::factory($count, array(
            'page'          => $page,
            'url_options'   => array(
                'params'    => $params,
            ),
        ));
        if ($my) {
            $title          = __('My comments');
            $user           = null;
            $template       = 'comment-my-post';

            $navTabs = array(
                array(
                    'active'    => null === $active,
                    'label'     => __('All Posts'),
                    'href'      => $this->url('', array(
                        'action'    => 'user',
                        'my'        => 1,
                    ))
                ),
                array(
                    'active'    => 1 == $active,
                    'label'     => __('Active Posts'),
                    'href'      => $this->url('', array(
                        'action'    => 'user',
                        'my'        => 1,
                        'active'    => 1,
                    ))
                ),
                array(
                    'active'    => 0 === $active,
                    'label'     => __('Inactive Posts'),
                    'href'      => $this->url('', array(
                        'action'    => 'user',
                        'my'        => 1,
                        'active'    => 0,
                    ))
                ),
            );
            $this->view()->assign(array(
                'tabs'      => $navTabs,
            ));

        } else {
            $user           = Pi::service('user')->get($uid, array('name'));
            $user['avatar'] = Pi::service('avatar')->get($uid);
            $user['url']    = Pi::service('user')->getUrl('profile', $uid);
            $title          = sprintf(
                __('Comment posts of user %s'),
                $user['name']
            );
            $template       = 'comment-user';
        }
        $this->view()->assign('comment', array(
            'title'     => $title,
            'count'     => $count,
            'posts'     => $posts,
            'paginator' => $paginator,
            'user'      => $user,
        ));

        $this->view()->setTemplate($template);
    }

    /**
     * Active comment posts of a module, or with its category
     */
    public function moduleAction()
    {
        //$active = _get('active', 'int') ?: 1;
        $active = 1;
        $module = _get('name') ?: 'comment';
        $category = _get('category') ?: '';
        $page   = _get('page', 'int') ?: 1;
        $limit = $this->config('list_limit') ?: 10;
        $offset = ($page - 1) * $limit;
        $where = array('module' => $module, 'active' => $active);

        $moduleData = Pi::registry('module')->read($module);
        $moduleData = array(
            'name'  => $module,
            'title' => $moduleData['title'],
        );
        $categoryData = array();
        if ($category) {
            $categoryData = Pi::registry('category', 'comment')->read(
                $module,
                $category
            );
            $where['category'] = $category;
        }
        $posts = Pi::api('comment')->getList(
            $where,
            $limit,
            $offset
        );
        $renderOptions = array(
            'operation' => $this->config('display_operation'),
        );
        $posts = Pi::api('comment')->renderList($posts, $renderOptions);
        $count = Pi::api('comment')->getCount($where);

        //$params = array('name' => $module, 'active' => $active);
        $params = array('name' => $module);
        if ($category) {
            $params['category'] = $category;
        }
        $paginator = Paginator::factory($count, array(
            'page'  => $page,
            'url_options'           => array(
                'params'        => $params,
            ),
        ));
        if ($categoryData) {
            $title = sprintf(
                __('Comment posts of Module %s with Category %s'),
                $moduleData['title'],
                $categoryData['title']
            );
        } else {
            $title = sprintf(
                __('Comment posts of Module %s'),
                $moduleData['title']
            );
        }
        $this->view()->assign('comment', array(
            'title'     => $title,
            'count'     => $count,
            'posts'     => $posts,
            'paginator' => $paginator,
            'module'    => $moduleData,
            'category'  => $categoryData,
        ));

        $this->view()->setTemplate('comment-module');
    }
}
