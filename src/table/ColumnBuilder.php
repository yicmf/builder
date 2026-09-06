<?php

// +----------------------------------------------------------------------
// | builder
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2026 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

namespace yicmf\builder\Table;

use yicmf\tools\ChinesePinyin;

/**
 * 表格列定义构建器
 * 2026-09-06 拆分重构：自 Table 迁出的 key* 系列列定义方法与列状态持有者
 * @package yicmf\builder\Table
 */
class ColumnBuilder
{
    /** @var array 列定义清单 */
    public $keyList = [];
    /** @var array 页面模板片段 */
    public $templets = [];
    /** @var array 关联预载入 */
    public $with = [];
    /** @var array 由关联字段推导出的附加查询字段 */
    public $extraFields = [];
    /** @var array 左侧序列（radio/checkbox） */
    public $leftLeader = [];
    /** @var array withCount 统计字段 */
    public $count = [];
    /** @var string 默认主键字段 */
    public $defaultPk = 'id';

    /**
     * @param string $defaultPk 默认主键字段
     */
    public function __construct(string $defaultPk = 'id')
    {
        $this->defaultPk = $defaultPk;
    }

    /**
     * @return array 列定义清单
     */
    public function getKeyList()
    {
        return $this->keyList;
    }

    /**
     * @return array 列定义清单（引用，供外部原地修改）
     */
    public function &getKeyListRef()
    {
        return $this->keyList;
    }

    /**
     * @return array 页面模板片段
     */
    public function getTemplets()
    {
        return $this->templets;
    }

    /**
     * @return array 关联预载入
     */
    public function getWith()
    {
        return $this->with;
    }

    /**
     * @return array 附加查询字段
     */
    public function getExtraFields()
    {
        return $this->extraFields;
    }

    /**
     * @return array withCount 统计字段
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * @return array 左侧序列
     */
    public function getLeftLeader()
    {
        return $this->leftLeader;
    }

    /**
     * 批量添加字段信息.
     * @param array $fields
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function setKeys($fields = [])
    {

        $this->keyList = array_merge($this->keyList, $fields);
        return $this;
    }

    /**
     * 需要展示的键值
     * @param       $field
     * @param       $title
     * @param bool $sort
     * @param string $width
     * @param string $type
     * @param string $style
     * @param string $templet
     * @param array $map
     * @param string $edit
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function key($field, $title, $sort = false, $width = '', $type = 'normal', $style = '', $templet = '', $map = [])
    {

        if (false === strpos($field, '{$') && strpos($field, '.')) {
            $templet = 'k'.uniqid();
            if (preg_match('/(.*)\[:(.*)\]/', $field, $matches)) {
                $field = $matches[1];
                $foreignKey = $matches[2];
            } else {
                $foreignKey = '';
            }
            $with = explode('.', $field);

            if (!isset($this->with[$with[0]])) {
                $this->with[$with[0]] = [$with[1]];
            } else {
                $this->with[$with[0]][] = $with[1];
            }
            if ($foreignKey) {
                $this->extraFields[] = $foreignKey;
            } else {
                $this->extraFields[] = $with[0] . '_id';
            }
            $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
 {{#  if(d.{$with[0]}){ }}
   {{d.{$field}}}
    {{#  }else{ }}
    -
  {{#  } }}
</script>
EOF;
            $templet = '#' . $templet;
        }

        if (preg_match('/(.*)\[(.*)\]/', $title, $matches)) {
            $title = $matches[2];
            $hide = true;
        } else {
            $hide = false;
        }
        if (!($templet instanceof \Closure) && false === strpos($field, '.')) {
            $this->extraFields = array_merge($this->extraFields, is_array($field) ? $field : explode(',', $field));
        } elseif (false !== strpos($field, '{$data')) {
            $this->extraFields[] = substr(explode('|', $field)[0], 7);;
        }
        if (!$sort) {
            $sort = false;
            $filter = false;
        } elseif (true === $sort || 'desc' == $sort || 'asc' == $sort) {
            $sort = true;
            $filter = false;
        } else {
            if (false !== strpos($sort, 'sort') && false !== strpos($sort, 'filter')) {
                $sort = true;
                $filter = true;
            } elseif (false === strpos($sort, 'sort') && false !== strpos($sort, 'filter')) {
                $sort = false;
                $filter = true;
            } else {
                $sort = true;
                $filter = false;
            }
        }
        if ($field == 'id') {
            $fixed = 'left';
        } else {
            $fixed = '';
        }
        if ($type == 'children') {
            $key = [
                'type' => $type,
                'field' => $field,
                'title' => $title,
                'collapse' => 1,
                'childWidth' => 'full',
                'style' => $style,
                'templet' => $templet,
            ];
        } else {

            $key = [
                'field' => $field,
                'type' => $type,
                'title' => $title,
                'sort' => $sort,
                'hide' => $hide,
                'filter' => $filter,
                //                'tips' => $tips,
                'style' => $style,
                'fixed' => $fixed,
                'templet' => $templet,
                'map' => $map,
//                'totalRow' => '{{= parseInt(d.TOTAL_NUMS) }} 次',//totalRow: '合计：'
                //                'even' => true,
            ];
            if (!$templet)
            {
                unset($key['templet']);
            }
        }
        $reKey = [];
        !empty($width) && $key['width'] = $width;
        $this->keyList[] = $key;
        return $this;
    }

    /**
     * 是否
     * @param string $field
     * @param string $title
     * @param $sort
     * @param $width
     * @param $style
     * @return $this
     */
    public function keyBool($field, $title, $sort = false, $width = '', $style = '')
    {

        return $this->keySwitch($field, $title, '是|否', $sort, $width, $style);
    }

    /**
     * 开关
     * @param string $field
     * @param string $title
     * @param $map
     * @param $sort
     * @param $width
     * @param $style
     * @return $this
     */
    public function keySwitch($field, $title, $map = ['启用', '禁用'], $sort = 'desc', $width = '', $style = '')
    {

        if (is_array($map)) {
            $map_text = implode('|', $map);
        } else {
            $map_text = $map;
            $map = explode('|', $map);
        }
        $map_result[0] = $map[1];
        $map_result[1] = $map[0];
        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
    <input type="checkbox" disabled  lay-skin="switch" lay-text="$map_text" {{ d.{$field} == 1 ? 'checked' : '' }}>
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet, $map_result);
    }

    /**
     * 第一序列显示
     * @param $type
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function keyLeftLeader($type)
    {

        if (false == $type) {
            $this->leftLeader = [];
        } else {
            $this->leftLeader = ['type' => $type, 'fixed' => 'left'];
        }
        return $this;
    }

    /**
     * 显示纯文本
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyText($field, $title, $sort = false, $width = '', $style = '')
    {

        return $this->key($field, $title, $sort, $width, 'normal', $style);
    }

    /**
     * 显示纯文本
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyEditerText($field, $title, $sort = false, $width = '', $style = '')
    {

        $templet = 'k'.uniqid();


        $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
    
    <div>{{- d.$field }}</div>
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style);
    }

    /**
     * 显示作者
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyAuthor($field, $title, $sort = false, $width = '', $style = '')
    {

        return $this->key($field, text($title), $sort, $width, 'normal', $style, '');
    }

    /**
     * 隐藏显示
     * @param string|array $field 键名
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyHidden($field)
    {

        return $this->key($field, '', false, '', 'hidden', '', '');
    }

    /**
     * 追加字段
     * @param string|array $field 键名
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function append($field)
    {

        return $this->key($field, '', false, '', 'hidden', '', '');
    }

    /**
     * 显示金额 美元
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDollar($field, $title, $sort = false, $width = '', $style = '')
    {

        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
   <i class="layui-icon layui-icon-dollar"></i> {{d.$field}}
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);
    }

    /**
     * 显示钻石
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDiamond($field, $title, $sort = false, $width = '', $style = '')
    {

        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
   <i class="layui-icon layui-icon-diamond"></i> {{d.$field}}
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);
    }

    /**
     * 显示金额RMB
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyRmb($field, $title, $sort = false, $width = '', $style = '')
    {

        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
   <i class="layui-icon layui-icon-rmb"></i> {{d.$field}}
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);
    }

    /**
     * 显示模板
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyTemplate($field, $templet, $title, $sort = false, $width = '', $style = '')
    {

        $templet_name = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
   $templet
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet_name);
    }

    /**
     * 显示统计数量
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyCount($field, $title, $sort = true, $width = '', $style = '')
    {

        $this->count[] = $field;
        return $this->key($field . '_count', $title, $sort, $width, 'normal', $style);
    }

    /**
     * 显示字段
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyField($field, $title, $sort = false, $width = '', $style = '', $templet = '')
    {

        return $this->key($field, $title, $sort, $width, 'normal', $style, $templet);
    }

    /**
     * 显示颜色.
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param null $width
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyColor($field, $title, $sort = false, $width = '')
    {

        return $this->key($field, text($title), $sort, $width, 'normal');
    }

    /**
     * 创建时间
     * @param string $title
     * @param bool $sort
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyCreateTime($title = '创建时间', $sort = false, $style = '')
    {

        return $this->keyTime('create_time', text($title), 'yyyy-MM-dd HH:mm:ss', $sort, $style);
    }

    /**
     * 更新时间
     * @param string $title
     * @param bool $sort
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyUpdateTime($title = '更新时间', $sort = false, $style = '')
    {

        return $this->keyTime('update_time', text($title), 'yyyy-MM-dd HH:mm:ss', $sort, $style);
    }

    /**
     * 时间
     * @param string $title
     * @param bool $sort
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyTime($field, $title, $format = 'yyyy-MM-dd HH:mm:ss', $sort = false, $style = '')
    {

        $templet_name = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
   {{#  
   if(d.$field && '0000-00-00 00:00:00' != d.$field){
  var date = new Date(d.$field);
  var time = date.Format("$format");
  }else{
  var time = '-';
  }
}}
<span title="{{d.{$field}}}">{{time}}</span>  
</script>
EOF;
        return $this->key($field, $title, $sort, strlen($format) * 8 + 10, 'normal', $style, '#' . $templet_name);
        //            $opt['format'] = $format;
    }

    /**
     * 邮件地址
     * @param       $field
     * @param       $title
     * @param bool $sort
     * @param null $width
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyEmail($field, $title, $sort = false, $width = '')
    {

        return $this->key($field, text($title), $sort, $width, 'normal');
    }

    /**
     * 显示html
     * @param string $field
     * @param string $title
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyHtml($field, $title)
    {

        return $this->key($field, $title, 'html', '', 'normal');
    }

    /**
     * @param string $field
     * @param string $title
     * @param $map
     * @param $sort
     * @param $width
     * @param $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyMap($field, $title, $map, $sort = false, $width = '', $style = '')
    {

        if (empty($width)) {
            $max = strlen($title);
            foreach ($map as $v) {
                if ($max < strlen($v)) {
                    $max = strlen($v);
                }
            }
            $width = $max * 5 + 40;
        }
        $templet_name = 'k'.uniqid();
        $map_en = json_encode($map, JSON_UNESCAPED_UNICODE);
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
   {{#  var map = $map_en }}
   {{map[d.{$field}]}}
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet_name, $map);
    }

    /**
     * 显示ID
     * @param string $title
     * @param string $sort
     * @param int $width
     * @param string $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyId($title = 'ID', $sort = false, $width = 80, $style = '')
    {

        return $this->keyText($this->defaultPk, $title, $sort, $width, $style);
    }

    /**
     * 关联直读图片链接
     * @param string $field
     * @param string $title
     * @param string|null $style
     * @return $this
     */
    public function keyImage($field, $title, $style = '')
    {

        $templet_name = 'k'.uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title=""
 layer-src="{{ d.{$field}?d.{$field}:'{$common}' }}" src="{{ d.{$field}?d.{$field}:'{$common}' }}"></div>
</script>
EOF;
        return $this->key($field, $title, false, 50 + 35, $style, 'normal', '#' . $templet_name);
    }

    /**
     * 关联直读多图片链接
     * @param string $field
     * @param string $title
     * @param string|null $style
     * @return $this
     */
    public function keyImages($field, $title, $style = '')
    {

        $templet_name = 'k'.uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-$field-{{d.id}}"  style="display: inline-block">
  {{#  layui.each(d.{$field}, function(index, item){ }}
<img style="width: 50px;cursor:pointer" title="" layer-src="{{item}}" src="{{item}}">
 {{#  }); }}
</div>
</script>
EOF;
        return $this->key($field, $title, false, '300', $style, 'normal', '#' . $templet_name);
    }

    /**
     * 关联模型单个图片
     * @param string $field
     * @param string $title
     * @param string|null $style
     * @return $this
     */
    public function keyImageModel($field, $title, $style = '')
    {

        if (strpos($field, '|')) {
            $temp = explode('|', $field);
            $field = $temp[1];
            $this->with[$temp[0]] = ['id', 'url'];
        } elseif (strpos($field, '_id')) {
            $temp = explode('_', $field);
            $this->with[$temp[0]] = ['id', 'url'];
        } else {
            $temp = $field;
        }
        $templet_name = 'k'.uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        if (is_array($temp)) {
            $with_field = $temp[0];
        } else {
            $with_field = $temp;
        }
//			$this->templets[] = <<<EOF
//<script type="text/html" id="$templet_name">
//<div class="layer-photos"  style="display: inline-block" id="layer-photos-$with_field-{{d.id}}"><img style="display: inline-block; width: 50px;cursor:pointer" title=""
// layer-src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}" src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}"></div>
//</script>
//EOF;
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title=""
 layer-src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}" src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}"></div>
</script>
EOF;

//			$this->templets[] = <<<EOF
//<script type="text/html" id="$templet_name">
//<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title=""
// layer-src="{{ d.{$field}?d.{$field}:'{$common}' }}" src="{{ d.{$field}?d.{$field}:'{$common}' }}"></div>
//</script>
//EOF;
        //            $this->templets[] = <<<EOF
        //<script type="text/html" id="$templet_name">
        // <img style="display: inline-block; width: 25px; height: 25px;" src= {{ d.{$temp}?d.{$field}:'{$common}/images/default_image.gif' }}>
        //</script>
        //EOF;
        return $this->key($field, $title, false, 50 + mb_strlen($title, 'utf-8') * 14, $style, 'normal', '#' . $templet_name);
    }

    /**
     * 关联模型多个图片
     * @param string $field
     * @param string $title
     * @param string|null $style
     * @return $this
     */
    public function keyImagesModel($field, $title, $style = '')
    {

        $templet_name = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos"  style="display: inline-block" id="layer-photos-$field-{{d.id}}">
 {{#  layui.each(d.{$field}, function(index, item){ }}
<img style="display: inline-block; width: 50px;cursor:pointer" title="点击查看2大图"
 layer-src="{{ item.url }}" src="{{ item.url }}">
   {{#  }); }}
   </div>
</script>
EOF;
        return $this->key($field, $title, false, 300, $style, 'normal', '#' . $templet_name);
    }

    /**
     * 显示关联用户
     * @param string $field
     * @param string $title
     * @param string|null $url
     * @param int $width
     * @param string|null $style
     * @return $this
     */
    public function keyUser($field, $title, $url = '/ucenter/admin/User/view', $width = 150, $style = '')
    {

        if (strpos($field, '|')) {
            $temp = explode('|', $field);
            $with_field = $temp[0];
            $field = $temp[1];
        } else {
            $temp = explode('_', $field);
            unset($temp[count($temp) - 1]);
            $with_field = implode('_', $temp);
        }
        if (isset($this->with[$with_field]))
        {
            $this->with[$with_field] = array_merge($this->with[$with_field], ['id', 'avatar', 'nickname']);
        }else{
            $this->with[$with_field] = ['id', 'avatar', 'nickname'];
        }
        $templet_name = 'k'.uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/avatar_default.png';
        $url = url($url) . '?id={{d.' . $with_field . '.id}}';
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
  {{#  if(d.{$with_field}){ }}
    <a style="cursor:pointer " lay-href="$url" >
  <img style="display: inline-block; width: 25px; height: 25px;border-radius: 50%;" src= {{ d.{$with_field}.avatar?d.{$with_field}.avatar:'{$common}' }}>  {{ d.{$with_field}?d.{$with_field}.nickname:'无用户' }}
  </a>
  {{#  }else{ }}    
       <div style="cursor:pointer ">
-
  </div>
  {{#  } }} 
</script>
EOF;
        return $this->key($field, $title, false, $width, 'normal', $style, '#' . $templet_name);
    }

    /**
     * 显示IP
     * @param string $field
     * @param string $title
     * @param $sort
     * @param string|null $type
     * @return $this
     */
    public function keyIp($field = 'ip', $title = 'IP地址', $sort = false, $type = '')
    {

        $templet_name = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
   <i class="layui-icon layui-icon-link"></i> <a href="https://www.ip.cn/?ip={{d.$field}}" target="_blank"> {{d.$field}}</a> 
</script>
EOF;
        return $this->key($field, $title, $sort, 160, 'normal', $type, '#' . $templet_name);
    }

    /**
     * 快捷title
     * @param string $title
     * @param string $sort
     * @param int|null $width
     * @return $this
     */
    public function keyTitle($title = '标题', $sort = false, $width = '')
    {

        return $this->keyText('title', $title, $sort, $width);
    }

    /**
     * 闭包函数
     * @param string $title
     * @param \Closure $closure
     * @param int|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyClosure($title, $closure, $width = '', $style = '')
    {

        $pinyin = new ChinesePinyin();
        return $this->key($pinyin->transformWithoutTone($title, '_'), text($title), false, $width, $closure, $style);
    }

    /**
     * 模板显示
     * @param string $title
     * @param string $templet
     * @param int|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyTemplateChild($title, $templet, $width = 80, $style = '')
    {

        $templet_name = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet_name">
   $templet
</script>
EOF;
        $this->keyList[] = [
            'field' => 'skus',
            'title' => $title,
            'type' => 'child',
            'width' => $width,
            'style' => $style,
            'collapse' => 1,
            'children' => '#' . $templet_name,
            'childWidth' => 'full',
        ];
        return $this;
    }

    /**
     * 可操作链接
     * @param string $field
     * @param string $title
     * @param string $target
     * @param int|null $width
     * @param string|null $style
     * @param $url string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
     * @return $this
     */
    public function keyLink($field, $title, $url, $target = '_self', $width = '', $style = '')
    {

        // 修整添加多个空字段时显示不正常的
        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
<script type="text/html" id="$templet">
   <i class="layui-icon layui-icon-link"></i> <a href="$url" target="$target"> {{d.$field}}</a> 
</script>
EOF;
        return $this->key($field, $title, false, $width, 'normal', $style, '#' . $templet);
    }

    /**
     * 新的tab窗口
     * @param string $field
     * @param string $title
     * @param $url string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
     * @param int|null $width
     * @return $this
     */
    public function keyTab($field, $title, $url, $width = '')
    {

        if (false !== strpos($url, '{$')) {
            // 补充
            $url = str_replace('{$', '{{d.', $url);
            $url = str_replace('}', '}}', $url);
        }
        // 修整添加多个空字段时显示不正常的
        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
 <script type="text/html" id="$templet">
          <a style="cursor:pointer " lay-href="$url" ><i class="layui-icon layui-icon-layouts"></i> {{d.$field}}</a>
        </script>
EOF;
        return $this->key($field, $title, false, $width, 'tab', '', '#' . $templet);
    }

    /**
     * 进度
     * @param string $field
     * @param string $title
     * @param string|boolean $sort
     * @param int|null $width
     * @return $this
     */
    public function keyProgress($field, $title, $sort = false, $width = '')
    {

        // 修整添加多个空字段时显示不正常的
        $templet = 'k'.uniqid();
        $this->templets[] = <<<EOF
  <script type="text/html" id="$templet">
        <div class="layui-progress layuiadmin-order-progress" lay-filter="progress-{{ d.id }}" lay-showPercent="true">
          <div class="layui-progress-bar layui-bg-blue" style="width: {{ d.$field }}%;"></div>
        </div>
      </script>

EOF;
        return $this->key($field, $title, false, $width, 'normal', '', '#' . $templet);
    }

    /**
     * 状态
     * @param array|null $map
     * @param string|boolean $sort
     * @param string $style
     * @return $this
     */
    public function keyStatus($map = null, $sort = false, $style = '')
    {

        $templet_name = 'k'.uniqid();
        $map = !is_null($map) ? $map : [
            -2 => '已删除',
            -1 => '禁用',
            1 => '启用',
            0 => '未审核',
            2 => '推荐',
        ];
        return $this->keyMap('status', '状态', $map, $sort, '', $style);
    }
}
