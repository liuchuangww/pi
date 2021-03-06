<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Module\Tag\Api;

use Zend\Db\Sql\Expression;
use Pi\Application\AbstractApi;
use Pi;

/**
 * Tag APIs
 *
 * @author Liu Chuang <liuchuangww@gmail.com>
 */
class Tag extends AbstractApi
{
    /**
     * Add tags of an item
     *
     * @param  string       $module Module name
     * @param  string       $item   Item identifier
     * @param  string       $type   Item type, default as ''
     * @param  array|string $tags   Tags to add
     * @param  int|null     $time   Time adding the tags
     * @return bool
     */
    public function add($module, $item, $type, $tags, $time = null)
    {
        // Change params type
        $module = strval($module);
        $item = intval($item);
        $time = $time ? intval($time) : time();
        $tags = array_unique((array)$tags);
        foreach ($tags as $index => $tag) {
            // Filter space
            $tag = trim($tag);
            if ('' === $tag) {
                continue;
            }
            // new tag in table or not
            $tagModel = Pi::model('tag', $this->module);
            $select = $tagModel->select()->where(array('term' => $tag));
            $result = $tagModel->selectWith($select)->current();
            // tag not in the tag table, insert, or update.
            if (false === $result) {
                $data = array('term' => $tag, 'count' => 1);
                $row = $tagModel->createRow($data);
                $row->save();
                $tagId = intval($row->id);
            } else {
                $tagId = $result->id;
                $tagModel->update(array('count' => new Expression('count + 1')), array('id' => $tagId));
            }

            // Insert data to link table.
            $data = array('tag' => $tagId, 'module' => $module, 'item' => $item, 'time' => $time, 'order' => $index);
            if ($type != null) {
                $data['type'] = $type;
            }
            $row = Pi::model('link', $this->module)->createRow($data);
            $row->save();

            // Search tag information in stats table.
            $statsModel = Pi::model('stats', $this->module);
            $where = array('tag' => $tagId, 'module' => $module);
            if ($type != null) {
                $where['type'] = $type;
            }
            $select = $statsModel->select()->where($where);
            $statsResult = $statsModel->selectWith($select)->current();

            if (false === $statsResult) {
                $data = array('tag' => $tagId, 'module' => $module, 'count' => 1);
                if ($type != null) {
                    $where['type'] = $type;
                }
                $row = Pi::model('stats', $this->module)->createRow($data);
                $row->save();
            } else {
                $statsId = $statsResult->id;
                $statsModel->update(array('count' => new Expression('count + 1')), array('id' => intval($statsId)));
            }
        }
    }

    /**
     * Update tag list of an item
     *
     * @param  string       $module Module name
     * @param  string       $item   Item identifier
     * @param  string       $type   Item type
     * @param  array|string $tags   Tags to add
     * @param  int|null     $time   Time adding new tags
     * @return bool
     */
    public function update($module, $item, $type, $tags, $time)
    {
        // Change params type
        $module = strval($module);
        $item   = intval($item);
        $time   = intval($time);
        $tags   = array_unique($tags);
        $update = 0;

        // database model
        $tagModel   = Pi::model('tag', $this->module);
        $linkModel  = Pi::model('link', $this->module);
        $statsModel = Pi::model('stats', $this->module);

        // Get item relaeated tag and order
        $oldTags = static::get($module, $item, $type);
        if (count($tags) == count($oldTags)) {
            foreach ($tags as $key => $value) {
                if ($oldTags[$key] != $value) {
                    $update = 1;
                }
            }
        } else {
            $update = 1;
        }

        if ($update == 0) {
            return ;
        }

        foreach ($tags as $key => $tag) {
            // tag is exist,order not modify ,ignore
            $tag = trim($tag);
            if ('' === $tag) {
                continue;
            }
            // tag is exist, order diff
            $tagId = static::in_tag($tag);

            if ( ! empty($tagId) && in_array($tag, $oldTags)) {
                // Update link table
                $linkModel->update(array('order' => $key), array('tag' => $tagId));
                continue;
            }


            // update tag table
            if (!empty($tagId)) {
                $tagModel->update(array('count' => new Expression('count + 1')), array('id' => $tagId));
            } else {
                $data = array('term' => $tag, 'count' => 1);
                $row = $tagModel->createRow($data);
                $row->save();
                $tagId = $row->id;
            }

            // update link table
            $data = array('tag' => $tagId, 'item' => $item, 'order' => $key, 'module' => $module, 'time' => $time);
            if ($type != null) {
                $data['type'] = $type;
            }
            $row = $linkModel->createRow($data);
            $row->save();

            // update stats table
            $statsId = static::in_stats($tagId);
            if (!empty($statsId)) {
                $statsModel->update(array('count' => new Expression('count + 1')), array('id' => intval($statsId)));
            } else {
                $data = array('tag' => $tagId, 'module' => $module, 'count' => 1);
                if ($type != null) {
                    $where['type'] = $type;
                }
                $row = $statsModel->createRow($data);
                $row->save();
            }
        }

        // Delete old tag
        $deleteTag = array_diff($oldTags, $tags);
        if (empty($deleteTag)) {
            return ;
        }
        $select = $tagModel->select()->where(array('term' => $deleteTag));
        $rowset = $tagModel->selectWith($select);
        $deleteTagId = array();
        foreach ($rowset as $row) {
            $deleteTagId[] = $row->id;
        }
        foreach ($deleteTagId as $tag) {

            $where = array('item' => $item, 'tag' => $tag, 'module' => $module,);
            if (!empty($type)) {
                $where['type'] = $type;
            }
            $select = $linkModel->select()->where($where);
            $result = $linkModel->selectWith($select)->toArray();

            // Delete data from link table.
            $linkModel->delete($where);

            // Delete data from stats table.
            foreach ($result as $row) {
                $where = array('tag' => $row['tag'], 'module' => $module);
                if (!empty($type)) {
                    $where['type'] = $type;
                }
                $select = $statsModel->select()->where($where);
                $statsCount = $statsModel->selectWith($select)->current()->count;

                if (1 == $statsCount) {
                    $statsModel->delete($where);
                } elseif (1 < $statsCount) {
                    $statsModel->update(array('count' => new Expression('count - 1')), $where);
                }
            }

            // Delete data from tag table.
            foreach ($result as $row) {
                $tagModel->update(array('count' =>  new Expression('count - 1')), array('id' => $row['tag']));
            }
        }
    }

    /**
     * Delete tags of an item
     *
     * @param  string $module Module name
     * @param  string $item   Item identifier
     * @param  string $type   Item type, default as ''
     * @return bool
     */
    public function delete($module, $item, $type = '')
    {
        // Change params type
        $module = strval($module);
        $items    = is_scalar($item) ? array($item) : $item;
        $type = strval($type);

        foreach($items as $item) {
            // Search relevent obj's tag.
            $linkModel = Pi::model('link', $this->module);
            $where = array('item' => $item, 'module' => $module);
            if (!empty($type)) {
                $where['type'] = $type;
            }
            $select = $linkModel->select()->where($where);
            $result = $linkModel->selectWith($select)->toArray();

            // Delete data from link table.
            $linkModel->delete($where);

            // Delete data from stats table.
            foreach ($result as $row) {
                $statsModle = Pi::model('stats', $this->module);
                $where = array('tag' => $row['tag'], 'module' => $module);
                if (!empty($type)) {
                    $where['type'] = $type;
                }
                $select = $statsModle->select()->where($where);
                $statsCount = $statsModle->selectWith($select)->current()->count;

                if (1 == $statsCount) {
                    $statsModle->delete($where);
                } elseif (1 < $statsCount) {
                    $statsModle->update(array('count' => new Expression('count - 1')), $where);
                }
            }

            // Delete data from tag table.
            foreach ($result as $row) {
                $tagModel = Pi::model('tag', $this->module);
                $tagModel->update(array('count' =>  new Expression('count - 1')), array('id' => $row['tag']));
            }
        }
    }

    /**
     * Get tags of an item
     *
     * @param  string     $module Module name
     * @param  string     $item   Item identifier
     * @param  string     $type   Item type
     * @return array|bool
     */
    public function get($module, $item, $type = null)
    {
        $tagArray = array();
        $tagid = array();
        $linkModel = Pi::model('link', $this->module);
        if (null == $type) {
            $where = array('item' => $item, 'module' => $module);
        } else {
            $where = array('item' => $item, 'module' => $module, 'type' => $type);
        }
        $select = $linkModel->select()->where($where)->order('order ASC');

        $result = $linkModel->selectWith($select)->toArray();
        foreach ($result as $row) {
            $tagid[] = $row['tag'];
            $tagName[$row['tag']] = '';
        }
        if (empty($tagid)) {
            return array();
        }
        $tagModel = Pi::model('tag', $this->module);
        if(empty($tagid)) {
            return array();
        }
        $select = $tagModel->select()->where(array('id' => $tagid));
        $rowset = $tagModel->selectWith($select);

        foreach ($rowset as $row) {
            $tagName[$row->id] = $row->term;
        }
        foreach ($tagName as $name) {
            $tagArray[] = $name;
        }

        return $tagArray;
    }

    /**
     * Get item list of a tag
     *
     * @param  string       $module Module name
     * @param  string|array $tag    Tag
     * @param  string|null  $type   Item type, null for all types
     * @param  int          $limit  Limit
     * @param  int          $offset Offset
     * @return array|bool
     */
    public function getList($module, $tag, $type = null, $limit = null, $offset = 0)
    {
        // Change data type
        $offset = intval($offset);
        $tag = array_unique($tag);
        $tagIds = array();

        // Get tag array id.
        $modelTag = Pi::model('tag', $this->module);
        $select = $modelTag->select()->where(array('term' => $tag))->columns(array('id'));
        $data = $modelTag->selectWith($select)->toArray();

        foreach ($data as $row) {
            $tagIds[] = $row['id'];
        }
        $where = array();
        if (!empty($tagIds)) {
            $where = array('tag' => $tagIds, 'module' => $module);
        } else {
            return array();
        }
        if (null !== $type) {
            $where['type'] = $type;
        }
        $modelLink = Pi::model('link', $this->module);
        $select = $modelLink->select()->where($where)->order('time DESC')->columns(array('item' => new Expression('distinct item')));
        if (null !== $limit) {
            $limit = intval($limit);
            $select->offset($offset)->limit($limit);
        }
        $re = $modelLink->selectWith($select)->toArray();
        $result = array();
        foreach($re as $id) {
            $result[] = $id['item'];
        }

        return $result;
    }

    /**
     * Get count items of tags
     *
     * @param  string       $module Module name
     * @param  string|array $tag    Tag
     * @param  string|null  $type   Item type, null for all types
     * @return int|bool
     */
    public function getCount($module, $tag, $type = null)
    {
        $tagIds = array();
        $modelTag = Pi::model('tag', $this->module);
        $tag = array_unique($tag);
        $select = $modelTag->select()->where(array('term' => $tag))->columns(array('id'));
        $rowset = $modelTag->selectWith($select);

        if (0 === $rowset->count()) {
            return 0;
        }

        foreach ($rowset as $row) {
            $tagIds[] = $row->id;
        }

        if (empty($tagIds)) {
            return 0;
        }
        $modelLink = Pi::model('link', $this->module);
        $where = array('tag' => $tagIds, 'module' => $module);
        if (null !== $type) {
            $where['type'] = $type;
        }
        $select = $modelLink->select()->where($where)->columns(array('items' => new Expression('distinct item')));
        $count = $modelLink->selectWith($select)->count();

        return $count;
    }

    /**
     * Get matched tags for quick match
     *
     * @param  string     $term   Term
     * @param  int        $limit  Limit
     * @param  string     $module Module name, null for all modules
     * @param  string     $type   Item type, null for all types
     * @return array|bool
     */
    public function match($term, $limit, $module = null, $type = null)
    {
        $limit  = intval($limit);
        $offset = 0;
        $result = array();
        $tagIds = array();

        if ((null === $module) || (null === $type)) {
            // Get website tag
            $tagModel = Pi::model('tag', $this->module);
            $select = $tagModel->select();
            $select->where->like('term', "{$term}%");
            $select->order('term ASC')->offset($offset)->limit($limit);
            $resultset = $tagModel->selectWith($select)->toArray();
            foreach ($resultset as $row) {
                $result[] = $row['term'];
            }

        } elseif ((null !== $module) && (null !== $type)) {
            $modelStats = Pi::model('stats', $this->module);
            $modelTag = Pi::model('tag', $this->module);

            // Search tag table
            $select = $modelTag->select();
            $select->where->like('term', "{$term}%");
            $rowset = $modelTag->selectWith($select)->toArray();
            foreach ($rowset as $row) {
                $tagIds[] = $row['id'];
            }

            if (empty($tagIds)) {
                return array();
            }
            // Search stats table
            $select = $modelStats->select()->where(array('tag' => $tagIds, 'module' => $module, 'type' => $type))->offset($offset)->limit($limit);
            $rowset = $modelStats->selectWith($select)->toArray();

            $tagIds = array();
            foreach ($rowset as $row) {
                $tagIds[] = $row['tag'];
            }

            // Search tag table for tagName
            $select = $modelTag->select()->where(array('id' => $tagIds))->columns(array('term'));
            $rowset = $modelTag->selectWith($select)->toArray();
            foreach ($rowset as $row) {
                $result[] = $row['term'];
            }
        }

        return $result;
    }

    /**
     * tag is ni tag database
     *
     * @param string $tag
     */
    public function in_tag($tag)
    {
        $tagModel = Pi::model('tag', $this->module);
        $select = $tagModel->select()->where(array('term' => $tag));
        $rowset = $tagModel->selectWith($select)->current();
        if($rowset) {
            return $rowset->id;
        }
    }

    public function in_stats($tagId)
    {
        $statsModel = Pi::model('stats', $this->module);
        $select = $statsModel->select()->where(array('tag' => $tagId));
        $rowset = $statsModel->selectWith($select)->current();
        if ($rowset) {
            return $rowset->id;
        }
    }

    /**
     * Search relate article according to tag array.
     *
     * @param  array  $tags   Tag array
     * @param  string $module Module name
     * @param  string $type   Item type
     * @param  int    $limit  Return item id counts
     * @param  null|string    $item
     *
     * @return array  $result     Item id array
     */
    public function relate($tags, $module, $type, $limit = null, $item = null)
    {
        $offset = 0;
        $tags = is_scalar($tags) ? array($tags) : $tags;
        $tags = array_unique($tags);

        $modelTag = Pi::model('tag', $this->module);
        $modelLink = Pi::model('link', $this->module);

        // Switch tagName to tagId
        $select = $modelTag->select()->where(array('term' => $tags));
        $rowset = $modelTag->selectWith($select)->toArray();
        foreach ($rowset as $row) {
            $tagIds[] = $row['id'];
        }
        if (null !== $item) {
            $item = (int) $item;
            $where = array('tag' => $tagIds, 'item != ?' => $item);
        } else {
            $where = array('tag' => $tagIds);
        }
        $select = $modelLink->select();
        $select->where($where)
            ->columns(array('item' => new Expression('distinct item')))
            ->order('time DESC');
        if (null !== $limit) {
            $limit = intval($limit);
            $select->offset($offset)->limit($limit);
        }
        $rowset = $modelLink->selectWith($select)->toArray();

        // Change $rowset to Digital index array
        $result = array_map(function($val) {
            return isset($val['item']) ? $val['item'] : null;
        }, $rowset);

        return $result;
    }

    /**
     * Fetch top tag and count
     *
     * @param string $module Module name
     * @param string   $type   Item type
     * @param int   $limit  Return tag count
     *
     * @return array
     */
    public function top($module, $type, $limit = null)
    {
        $offset = 0;
        $modelTag = Pi::model('tag', $this->module);
        $modelStats = Pi::model('stats', $this->module);
        $where = array('module' => $module);
        if (!empty($type)) {
            $where['type'] = $type;
        }
        $select = $modelStats->select()->where($where)->order('count DESC');
        if ($limit) {
            $limit = intval($limit);
            $select->offset($offset)->limit($limit);
        }
        $rowset = $modelStats->selectWith($select)->toArray();
        foreach ($rowset as $row) {
            $tagIds[] = $row['tag'];
        }
        $select = $modelTag->select()->where(array('id' => $tagIds))->order('count DESC');
        $result = $modelTag->selectWith($select)->toArray();

        return $result;
    }

    /**
     * Fetch multiple item related tags
     *
     * @param  string $module  module name not null
     * @param  array  $items   items array
     * @param  string $type    items type  default null
     *
     * @return array  result   items relate tags
     */
    public function multiple($module, $items, $type = null)
    {
        $items      = is_scalar($items) ? (array) $items : $items;
        $result     = array();

        $modeTag    = Pi::model('tag', $this->module);
        $modeLink   = Pi::model('link', $this->module);

        $where      = array('item' => $items, 'module' => $module);
        if ($type) {
            $where['type'] = $type;
        }

        // Get item related tag ids
        $select = $modeLink->select()
            ->where($where)
            ->order('order ASC')
            ->columns(array('tag', 'item'));
        $rows = $modeLink->selectWith($select)->toArray();
        $tagIds = array();
        foreach ($rows as $row) {
            $result[$row['item']][$row['tag']] = '';
            $tagIds[] = $row['tag'];
        }
        if (empty($tagIds)) {
            return array();
        }
        $tagIds = array_unique($tagIds);
        $select = $modeTag->select()
            ->where(array('id' => $tagIds))
            ->columns(array('id', 'term'));
        $rowset = $modeTag->selectWith($select)->toArray();
        foreach ($rowset as $row) {
            $terms[$row['id']] = $row['term'];
        }

        foreach($result as $index => $row) {
            foreach ($row as $key => $value) {
                $result[$index][$key] = $terms[$key];
            }
        }

        return $result;
    }

}
