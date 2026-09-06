<?php

// +----------------------------------------------------------------------
// | builder
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2026 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

namespace yicmf\builder\table;

/**
 * 表格查询配置构建器
 * 2026-09-06 拆分重构：自 Table 迁出的查询状态（model/where/order/field/hiddenField/totalRow/data/pagination/quickUpdate）
 * 与对应 setter，读取方通过 getter 访问；快捷编辑模板写入通过 Table 门面回调完成，保持原语义
 * @package yicmf\builder\table
 */
class QueryResolver
{
    /** @var string|\think\Model|\Closure|null 模型（原 Table::_model） */
    public $model = null;
    /** @var array 累积的 where 条件包（原 Table::_where） */
    public $where = [];
    /** @var mixed 默认排序（原 Table::_order） */
    public $order = null;
    /** @var array 查询字段（原 Table::_field） */
    public $field = ['id', 'status'];
    /** @var array 隐藏字段（原 Table::_hidden_field） */
    public $hiddenField = [];
    /** @var array 合计行配置（原 Table::_total_row） */
    public $totalRow = [];
    /** @var bool 是否分页（原 Table::_pagination） */
    public $pagination = true;
    /** @var mixed 直接指定的数据（原 Table::_data） */
    public $data = [];
    /** @var array 快捷编辑配置（原 Table::_quick_update） */
    public $quickUpdate = [];

    /** @var object Table 门面引用（快捷编辑模板写入） */
    private $table;

    /**
     * @param object $table Table 门面实例
     */
    public function __construct($table = null)
    {
        $this->table = $table;
    }

    /**
     * @return string|\think\Model|\Closure|null
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return array
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return array
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return array
     */
    public function getHiddenField()
    {
        return $this->hiddenField;
    }

    /**
     * @return array
     */
    public function getTotalRow()
    {
        return $this->totalRow;
    }

    /**
     * @return bool
     */
    public function isPaginated()
    {
        return $this->pagination;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getQuickUpdate()
    {
        return $this->quickUpdate;
    }

    /**
     * 模型
     * @param string $model
     * @param boolean $pagination 分页
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function model($model, $pagination = true)
    {
        $this->model = $model;
        $this->pagination = $pagination;
        return $this;
    }

    /**
     * 筛选条件
     * where('status=1') 字符串原生条件 / where(function($q){...}) 闭包子查询
     * 多次调用会累积为 AND 条件（与模型链式 where 一致）
     * @param mixed ...$args 透传给模型 where 的参数（1/2/3 个，或数组/字符串/闭包）
     * @return $this
     * [Buddy 2026-08-28] 调整：改为条件包数组累积，兼容模型一致的 1/2/3 参数及闭包/字符串，支持链式
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function where(...$args)
    {
        $this->where[] = $args;
        return $this;
    }

    /**
     * 模型排序
     * @param $order
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function order($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * 模型指定字段
     * @param string $field
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function field($field)
    {
        $this->field = array_merge($this->field, is_array($field) ? $field : [$field]);
        return $this;
    }

    /**
     * 模型指定字段
     * @param string $field
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function hiddenField($field)
    {
        $this->hiddenField = array_merge($this->hiddenField, is_array($field) ? $field : [$field]);
        return $this;
    }

    /**
     * 设置需要快捷编辑（行内编辑）的字段
     * 支持文本、下拉（select）、开关（switch）等类型，select/switch 会自动生成对应模板
     * @param array|string $fields 字段名列表，可为数组或逗号分隔的字符串，也可为字段名=>配置的数组
     * @param mixed $update 快捷编辑提交的附加参数（是否可编辑等）
     * @return $this
     */
    public function quickUpdate($fields, $update = null)
    {
        $fields = is_array($fields) ? $fields : explode(',', $fields);
        foreach ($fields as $index => $item) {
            if (is_numeric($index)) {
                $this->quickUpdate[$item] = ['option' => ['type' => 'text'], 'qucik_edit' => $update];
            } else {
                if ($item['type'] == 'select') {
                    $templet = 'k' . uniqid();
                    $op = json_encode($item['option']);
                    $this->table->getColumnBuilder()->templets[] = <<<EOF
<script type="text/html" id="$templet">
  {{#  var cityList = $op; }}
  <select name="$index" lay-filter="select-demo" lay-append-to="body"  lay-ignore>
    <option value="">请选择</option>
    {{#  layui.each(cityList, function(i, v){ }}
    <option value="{{= v }}" {{= v === d.city ? 'selected' : '' }}>{{= v }}</option>
    {{#  }); }}
  </select> 
</script>
EOF;
                    $templet = '#' . $templet;
                    $item['templet'] = $templet;
                } elseif ('switch' == $item['type']) {
                    $templet = 'k' . uniqid();
                    $op = json_encode($item['option']);
                    $this->table->getColumnBuilder()->templets[] = <<<EOF
<script type="text/html" id="$templet">
  <!-- 这里的 checked 的状态值判断仅作为演示 -->
  <input type="checkbox" data-name="$index" name="$index" value="{{= d.$index }}" title="ON|OFF"  lay-skin="switch" lay-filter="demo-templet-status" {{= d.$index == 1 ? "checked" : "" }}>
</script>
EOF;
                    $templet = '#' . $templet;
                    $item['templet'] = $templet;
                }
                $this->quickUpdate[$index] = ['option' => $item, 'qucik_edit' => $update];
            }
        }
        return $this;
    }

    /**
     * 设置合计行字段
     * @param string $field
     * @param string $templet
     * @return $this
     */
    public function totalRowField($field, $templet)
    {
        $this->totalRow[] = ['field' => $field, 'templet' => $templet];
        return $this;
    }

    /**
     * 当前的数据信息.
     * @param array|object $data 分页信息
     * @param bool $pagination 是否启用分页
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function data($data, $pagination = true)
    {
        $this->data = $data;
        $this->pagination = $pagination;
        return $this;
    }
}
