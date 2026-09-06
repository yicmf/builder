<?php

// +----------------------------------------------------------------------
// | builder
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2026 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

namespace yicmf\builder\Table;

use think\facade\Db;
use think\Exception;

/**
 * 表格搜索构建器
 * 2026-09-06 拆分重构：自 Table 迁出的 search* 系列搜索配置方法与搜索状态持有者
 * @package yicmf\builder\Table
 */
class SearchBuilder
{
    /** @var array 搜索配置清单（原 Table::_search） */
    public $search = [];
    /** @var string 搜索提交地址（原 Table::_searchPostUrl） */
    public $searchPostUrl = '';
    /** @var string 筛选下拉选择提交地址（原 Table::_selectPostUrl） */
    public $selectPostUrl = '';
    /** @var string 回收站彻底删除地址（原 Table::_setClearUrl） */
    public $setClearUrl = '';
    /** @var callable|null 搜索用户时的自定义回调，为 null 时保持原 Db::name('user') 查询逻辑 */
    public $userSearch = null;
    /**
     * @var callable|null 筛选 condition 模式下的字段值解析回调（原 Table::_getFieldValue 依赖 ColumnBuilder 的 keyList map）
     * 2026-09-06 拆分重构：_getMode 的 'condition' 分支原调用 Table::_getFieldValue（其依赖 ColumnBuilder 持有的
     * keyList map 做显示值到真实值的反查）。SearchBuilder 与列状态解耦，改为可注入回调；
     * 未注入（null）时直接返回原始 value，等价于无 map 配置时的行为。如需还原 map 反查，
     * 可在构造时注入 function($field, $value){ ... } 形式的回调。
     */
    public $fieldValueResolver = null;
    /** @var object|null 当前请求（由 Table 门面在调用 searchWhere 前注入） */
    public $request = null;

    /**
     * @param callable|null $userSearch 搜索用户时的自定义回调，接收关键字返回用户 ID 数组
     */
    public function __construct(?callable $userSearch = null)
    {
        $this->userSearch = $userSearch;
    }

    /**
     * @return array 搜索配置清单
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * 追加一条搜索配置（供 Table::tag / Table::searchTabs 等复用写入通道）
     * @param array $item
     * @return $this
     */
    public function pushSearch(array $item)
    {
        $this->search[] = $item;
        return $this;
    }

    /**
     * @return string 搜索提交地址
     */
    public function getSearchPostUrl()
    {
        return $this->searchPostUrl;
    }

    /**
     * 写入搜索提交地址（原 setSearchPostUrl 中无 request 依赖的写入段）
     * @param string $url
     * @return $this
     */
    public function setSearchPostUrlValue($url)
    {
        $this->searchPostUrl = $url;
        return $this;
    }

    /**
     * @return string 筛选下拉选择提交地址
     */
    public function getSelectPostUrl()
    {
        return $this->selectPostUrl;
    }

    /**
     * @return string 回收站彻底删除地址
     */
    public function getSetClearUrl()
    {
        return $this->setClearUrl;
    }

    /**
     * 设置回收站根据ids彻底删除的URL
     * @param string $url
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setClearUrl($url)
    {
        $this->setClearUrl = $url;
        return $this;
    }

    /**
     * 筛选下拉选择url
     * @param string $url
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setSelectPostUrl($url)
    {
        $this->selectPostUrl = url($url);
        return $this;
    }

    /**
     * 搜索text文本信息.
     * @param string $title
     * @param string $field
     * @param string $placeholder
     * @param string $default
     * @param array $attr
     * @return $this
     */
    public function searchText($field, $title, $placeholder = '', $default = '', $attr = [])
    {
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'type' => 'text',
            'condition' => '=',
            'placeholder' => $placeholder,
            'value' => $default,
            'attr' => $attr,
        ];
        return $this;
    }

    /**
     * 模糊搜索text文本信息.
     * @param string $title
     * @param string $field
     * @param string $placeholder
     * @param string $default
     * @param array $attr
     * @return $this
     */
    public function searchTextLike($field, $title, $placeholder = '支持模糊搜索', $default = '', $attr = [])
    {
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'type' => 'text',
            'condition' => 'like',
            'value' => $default,
            'placeholder' => $placeholder,
            'attr' => $attr,
        ];
        return $this;
    }

    /**
     * 模糊搜索text文本信息.
     * @param string $title
     * @param string $field
     * @param string $placeholder
     * @param string $default
     * @param array $attr
     * @return $this
     */
    public function searchTextIn($field, $title, $placeholder = '多个值用英文逗号","隔开', $default = '', $attr = [])
    {
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'type' => 'text',
            'condition' => 'in',
            'value' => $default,
            'placeholder' => $placeholder,
            'attr' => $attr,
        ];
        return $this;
    }

    /**
     * 搜索用户
     * @param string $title
     * @param string $field
     * @param string $default
     * @param string $placeholder
     * @param array $attr
     * @return $this
     */
    public function searchUser($title, $placeholder = '支持邮箱、手机、账号、ID', $default = '', $field = 'user_id', $attr = [])
    {
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'type' => 'text',
            'condition' => 'search_user',
            'value' => $default,
            'placeholder' => $placeholder,
            'attr' => $attr,
        ];
        return $this;
    }


    /**
     * 日期选择器
     * @param string $field
     * @param string $title
     * @param $placeholder
     * @param $default
     * @param $width
     * @param $min
     * @param $max
     * @param $type
     * @param $range
     * @return $this
     */
    public function searchDate($field, $title, $placeholder = null, $default = null, $width = 300, $min = '', $max = '', $type = 'date', $range = false)
    {
        $formats = [
            'year' => 'yyyy',
            'date' => 'yyyy-MM-dd',
            'datetime' => 'yyyy-MM-dd HH:mm:ss',
        ];
        $format = [
            'date' => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
        ];

        if (is_string($default)) {
            if (!strpos($default, ' - ')) {
                if (strtotime($default) < time()) {
                    $default = time_format($default, $format[$type]) . ' - ' . time_format('now', $format[$type]);
                } else {
                    $default = time_format('now', $format[$type]) . ' - ' . time_format($default, $format[$type]);
                }
            }
        }
        $options = [
            'elem' => '#j_table_builder_' . (strpos($field, '|') ? md5($field) : $field),
            'type' => $type,
            'range' => $range,
            'format' => $formats[$type],
            'mark' => [],
            'min' => $min,
            'max' => $max,
            'value' => $default,
        ];
        foreach ($options as $key => $item) {
            if (!$item) {
                unset($options[$key]);
            }
        }
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'type' => 'datepicker',
            'value' => $default,
            'condition' => 'between',
            'placeholder' => $placeholder,
            'width' => $width,
            'options' => $options,
        ];
        return $this;

    }

    /**
     * 日期时间选择器
     * @param string $field
     * @param string $title
     * @param null $placeholder
     * @param null $default
     * @param int $width
     * @param string $min
     * @param string $max
     * @return $this
     */
    public function searchDateTimeRange($field, $title, $placeholder = null, $default = null, $width = 300, $min = '', $max = '')
    {
        return $this->searchDate($field, $title, $placeholder, $default, $width, $min, $max, 'datetime', true);
    }

    /**
     * 日期选择器
     * @param string $field
     * @param string $title
     * @param null $placeholder
     * @param null $default
     * @param int $width
     * @param string $min
     * @param string $max
     * @return $this
     */
    public function searchDateRange($field, $title, $placeholder = null, $default = null, $width = 180, $min = '', $max = '')
    {
        return $this->searchDate($field, $title, $placeholder, $default, $width, $min, $max, 'date', true);
    }


    /**
     * 是否选择搜索
     * @param        $field
     * @param        $title
     * @param int $default
     * @param string $des
     * @param array $attr
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function searchBool($field, $title, $des = '', $default = '', $attr = [])
    {
        $options = [
            [
                'id' => 0,
                'value' => '否',
            ],
            [
                'id' => 1,
                'value' => '是',
            ],
        ];

        return $this->searchSelect($field, $title, $options, $des, $default, $attr);
    }

    /**
     * 选择搜索
     * @param        $field
     * @param        $title
     * @param array $options
     * @param string $placeholder
     * @param string $default
     * @param array $attr
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function searchSelect($field, $title, $options = [], $placeholder = '', $default = '', $attr = [])
    {
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'value' => $default,
            'default' => $default,
            'type' => 'select',
            'placeholder' => $placeholder,
            'attr' => $attr,
            'condition' => '=',
            'options' => $options,
        ];
        return $this;
    }


    /**
     * 筛选搜索功能
     * @param string $title 标题
     * @param string $field 键名
     * @param string $type
     * @param string|null $placeholder
     * @param string|null $default
     * @param array $attr
     * @param array|null $options
     * @return $this
     */
    public function search($title = '搜索', $field = 'key', $type = 'text', $placeholder = '', $default = '', $attr = [], $options = null)
    {
        $this->search[] = [
            'title' => $title,
            'field' => $field,
            'value' => $default,
            'type' => $type,
            'condition' => '=',
            'placeholder' => $placeholder,
            'attr' => $attr,
            'options' => $options,
        ];
        return $this;
    }

    /**
     * 获取当前请求的排序列表 SQL 片段
     * 优先取请求中的 order_field/order，其次取设置的默认排序，最终回退为 id DESC
     * 2026-09-06 拆分重构：自 Table::_searchOrder 迁出，request 与默认排序改为方法参数传入
     * @param object $request 当前请求（think\Request 兼容桩）
     * @param string|null $order 默认排序
     * @return string
     */
    public function searchOrder($request, $order)
    {
        if ($request->has('order_field')) {
            return $request->param('order_field') . ' ' . $request->param('order');
        } elseif (!$order) {
            return 'id DESC';
        } else {
            return $order;
        }
    }

    /**
     * 根据请求中的搜索参数组装查询条件
     * 遍历请求的 field/a 参数并匹配已设置的搜索项，生成模型 where 条件数组
     * 2026-09-06 拆分重构：自 Table::_searchWhere 迁出，数据表字段改由参数 $dbFields 传入
     * （原 _model/_field 白名单计算段保留在 Table::resolveSearchDbFields 中），request 由门面注入
     * @param array $dbFields 可用于搜索的数据表字段白名单
     * @return array
     */
    public function searchWhere($dbFields)
    {
        $fields = $this->request->param('field/a');
//        dump($fields);
//        dump($this->search);
        // 2026-09-06 拆分重构：数据表字段白名单改由 Table::resolveSearchDbFields 计算后经参数传入
        // 原计算段（_model 为字符串/对象时调 getTableFields，否则合并 _field 与 extraFields）注释保留：
        //
        // $model = $this->_model;
        // if (!is_null($model)) {
        //     if (is_string($model)) {
        //         $db_fields = $model::getTableFields();
        //     } else {
        //         $db_fields = $model->getTableFields();
        //     }
        // } else {
        //     $db_fields = array_merge($this->_field, $this->columnBuilder()->getExtraFields());
        // }
        $db_fields = $dbFields;
        $where = [];
        $_search_field = [];
        if (is_array($fields)) {
            foreach ($this->search as $search) {
                if (in_array($search['field'], $db_fields) && isset($fields[$search['field']]) && $fields[$search['field']] != '') {
                    $_search_field[] = $search['field'];
                    if ('like' === $search['condition']) {
                        $where[] = [$search['field'], 'like', '%' . $fields[$search['field']] . '%'];
                    } elseif ('between' === $search['condition']) {
                        if ('datepicker' === $search['type']) {
                            $temp = explode(' - ', $fields[$search['field']]);
                            if ($temp[0] && $temp[1]) {
                                $where[] = [$search['field'], 'between time', $temp];
                            }
                        }
                    } elseif ('search_user' == $search['condition']) {
                        // 2026-09-06 拆分重构：search_user 查询解耦，可通过 userSearch 回调注入自定义实现；回调为空时保持原逻辑
                        if ($this->userSearch) {
                            $ids = call_user_func($this->userSearch, $fields[$search['field']]);
                        } else {
                            $ids = Db::name('user')
                                ->where('status', '>', -2)
                                ->where('id|username|email|nickname', 'like', '%' . $fields[$search['field']] . '%')
                                ->column('id');
                        }
                        $where[] = [$search['field'], 'in', $ids];
                    } elseif ('in' == $search['condition']) {
                        $where[] = [$search['field'], 'in', $fields[$search['field']]];
                    } else {
                        $where[] = [$search['field'], '=', $fields[$search['field']]];
                    }
                }
            }
        }
        $urlFields = $this->request->except(explode(',', 'v,page,limit,user,m,field,video,store'));
        if (is_array($urlFields)) {
            foreach ($urlFields as $field => $field_value) {
                if (!in_array($field, $db_fields) || in_array($field, $_search_field)) {
                    continue;
                }
//					$out = false;
//					foreach ($this->search as $search) {
//						if ($search['field'] == $field) {
//							$out = true;
//							continue;
//						}
//					}
//					if ($out) {
//						continue;
//					}
                $where[] = [$field, '=', $field_value];
            }
        }
        //

        $filterSos = $this->request->param('filterSos/s')?json_decode(htmlspecialchars_decode($this->request->param('filterSos/s')), true):[];


        //筛选数据支持
        if (is_array($filterSos)) {
            foreach ($filterSos as $index => $filterSo) {
                if ('in' == $filterSo['mode']) {
                    $where[] = $this->_getMode($filterSo);
                } elseif ('group' == $filterSo['mode']) {
                    throw new Exception('暂不支持');
//						foreach ($filterSo['children'] as $child) {
//							$where[] = $this->_getMode($child);
//						}
                } else {
                }
            }
        }
        return $where;
    }

    /**
     * 解析 filterSos condition 模式下的 where 条件
     * 2026-09-06 拆分重构：自 Table::_getMode 原样迁入；'condition' 分支原调用
     * Table::_getFieldValue（依赖 ColumnBuilder 的 keyList map 反查），解耦为
     * fieldValueResolver 回调注入，未注入时直接返回原始 value（等价于无 map 时的行为）。
     * @param array $filter
     * @return array
     */
    private function _getMode($filter)
    {
        if ('in' == $filter['mode']) {
            $data = [$filter['field'], 'in', $filter['values']];
//				$data = [$filter['field'], 'in', $this->_getFieldValue($filter['field'], $filter['values'])];
        } elseif ('condition' == $filter['mode']) {
            if ('eq' == $filter['type']) {
                // 2026-09-06 拆分重构：原实现为 $data = [$filter['field'], '=', $this->_getFieldValue($filter['field'], $filter['value'])];
                // _getFieldValue 依赖 ColumnBuilder 的 keyList map，此处解耦为 fieldValueResolver 回调（null 时等价于无 map 直返）
                $data = [$filter['field'], '=', $this->resolveFieldValue($filter['field'], $filter['value'])];
            }
        }
        return $data;
    }

    /**
     * 字段值解析（原 Table::_getFieldValue 的解耦形式）
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    private function resolveFieldValue($field, $value)
    {
        if (is_null($this->fieldValueResolver)) {
            return $value;
        }
        return call_user_func($this->fieldValueResolver, $field, $value);
    }
}
