<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link         http://code.pialog.org for the Pi Engine source repository
 * @copyright    Copyright (c) Pi Engine http://pialog.org
 * @license      http://pialog.org/license.txt New BSD License
 */

namespace Module\Article\Controller\Front;

use Pi\Mvc\Controller\ActionController;
use Module\Article\Service;
use Module\Article\Model\Article;
use Zend\Db\Sql\Expression;
use Pi\Paginator\Paginator;
use Pi;

/**
 * List controller
 * 
 * @author Zongshu Lin <lin40553024@163.com>
 */
class ListController extends ActionController
{
    /**
     * Listing all articles for users to review 
     */
    public function allAction()
    {
        $page     = $this->params('p', 1);
        $sort     = $this->params('sort', 'new');

        $where  = array(
            'status'           => Article::FIELD_STATUS_PUBLISHED,
            'active'           => 1,
            'time_publish < ?' => time(),
        );
        
        $category = $this->params('category', 0);
        if (!empty($category) && 'all' != $category) {
            $modelCategory = $this->getModel('category');
            if (!is_numeric($category)) {
                $category = $modelCategory->slugToId($category);
            }
            $children = $modelCategory->getDescendantIds($category);
            $where['category'] = $children;
        }
        
        //@todo Get limit from module config
        $limit  = (int) $this->config('page_limit_all');
        $limit  = $limit ?: 40;
        $offset = $limit * ($page - 1);

        $model  = $this->getModel('article');
        $select = $model->select()->where($where);d($sort);
        if ('hot' == $sort) {
            $modelStats = $this->getModel('stats');
            $select->join(
                array('st' => $modelStats->getTable()),
                sprintf('%s.id = st.article', $model->getTable()),
                array()
            );
            $order = 'st.visits DESC';
        } else {
            $order = 'time_publish DESC';
        }
        $select->order($order)->offset($offset)->limit($limit);
        
        $module = $this->getModule();
        $route  = Pi::api('api', $module)->getRouteName();
        $resultset = $model->selectWith($select);
        $items     = array();
        $categoryIds = $authorIds = array();
        foreach ($resultset as $row) {
            $items[$row->id] = $row->toArray();
            $publishTime     = date('Ymd', $row->time_publish);
            $items[$row->id]['url'] = $this->url(
                $route, 
                array(
                    'id'   => $row->id, 
                    'time' => $publishTime
                )
            );
            $authorIds[]   = $row->author;
            $categoryIds[] = $row->category;
        }
        
        // Get author
        $authors = array();
        if (!empty($authorIds)) {
            $rowAuthor = $this->getModel('author')
                ->select(array('id' => $authorIds));
            foreach ($rowAuthor as $row) {
                $authors[$row->id] = $row->toArray();
            }
        }
        
        // Total count
        $select = $model->select()
            ->where($where)
            ->columns(array('total' => new Expression('count(id)')));
        $articleCountResultset = $model->selectWith($select);
        $totalCount = intval($articleCountResultset->current()->total);

        // Paginator
        $paginator = Paginator::factory($totalCount);
        $paginator->setItemCountPerPage($limit)
            ->setCurrentPageNumber($page)
            ->setUrlOptions(array(
                'page_param' => 'p',
                'router'     => $this->getEvent()->getRouter(),
                'route'      => $route,
                'params'     => array(
                    'module'        => $this->getModule(),
                    'controller'    => 'list',
                    'action'        => 'all',
                    'list'          => 'all',
                ),
            ));
        
        $module = $this->getModule();
        $config = Pi::service('module')->config('', $module);
        
        // Get category nav
        $rowset = $this->getModel('category')->enumerate(null, null);
        $rowset = array_shift($rowset);
        $navs   = $this->canonizeCategory($rowset['child'], $route);
        $allNav['all'] = array(
            'label'      => __('All'),
            'route'      => $route,
            'controller' => 'list',
            'params'     => array(
                'category'   => 'all',
            ),
        );
        $navs = $allNav + $navs;
        
        // Get all categories
        $categories = array(
            'all' => array(
                'id'    => 0,
                'title' => __('All articles'),
                'image' => '',
                'url'   => Pi::service('url')->assemble(
                    Pi::api('api', $module)->getRouteName($module),
                    array(
                        'controller' => 'list',
                        'action'     => 'all',
                        'list'       => 'all',
                    )
                ),
            ),
        );
        $rowset = $this->getModel('category')->enumerate(null, null, true);
        foreach ($rowset as $row) {
            if ('root' == $row['name']) {
                continue;
            }
            $url = Pi::service('url')->assemble('', array(
                'controller' => 'category',
                'action'     => 'list',
                'category'   => $row['id'],
            ));
            $categories[$row['id']] = array(
                'id'    => $row['id'],
                'title' => $row['title'],
                'image' => $row['image'],
                'url'   => $url,
            );
        }
        
        $urlHot = $this->url($route, array('category' => $category, 'sort' => 'hot'));
        $urlNew = $this->url($route, array('category' => $category));

        $this->view()->assign(array(
            'title'      => __('All Articles'),
            'articles'   => $items,
            'paginator'  => $paginator,
            'elements'   => $config['list_item'],
            'authors'    => $authors,
            'categories' => $categories,
            'length'     => $config['list_summary_length'],
            'navs'       => $this->config('enable_list_nav') ? $navs : '',
            'category'   => $category,
            'url'        => array(
                'hot'       => $urlHot,
                'new'       => $urlNew,
            ),
        ));
    }
    
    /**
     * Canonize category structure
     * 
     * @params array  $categories
     * @params string $route
     */
    protected function canonizeCategory(&$categories, $route)
    {
        foreach ($categories as &$row) {
            $row['label']      = $row['title'];
            $row['controller'] = 'category';
            $row['action']     = 'list';
            $row['params']     = array('category' => $row['id']);
            $row['route']      = $route;
            if (isset($row['child'])) {
                $row['pages'] = $row['child'];
                unset($row['child']);
                $this->canonizeCategory($row['pages'], $route);
            }
        }
        
        return $categories;
    }
}
