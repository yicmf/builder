<?php

// +----------------------------------------------------------------------
// | builder
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2022 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

namespace yicmf\builder;

use app\ucenter\event\AuthGroup as AuthGroupEvent;
use Overtrue\Pinyin\Pinyin;
use think\exception\HttpException;
use think\Model;
use think\facade\Db;
use think\db\Where;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Hook;
use think\Exception;
use app\file\model\Picture as PictureModel;
use app\file\model\Attachment as AttachmentModel;

class Table extends Builder
{
    private $_title;
    private $_namespace;

    private $_suggest;
    private $_statistics;

    private $_warning;

    private $_keyList = [];

    private $_buttonList = [];
    // 是否分页
    private $_pagination = true;

    private $_data = [];
    private $_quick_update = [];

    private $_searchPostUrl;

    private $_selectPostUrl;

    private $_setClearUrl;

    private $_search = [];

    private $_search_more = [];

    private $_select = [];

    private $_group = [];
    private $_left_leader = [];
    private $_templets = [];

    private $_hidden = [];

    private $_callback = '';
    private $_do_action = [];

    private $_callback_field = '';
    /**
     * 当前行样式
     * @var unknown
     */
    private $_row_style;
    private $_row_class;

    // 默认配置值
    // 默认获取主键的字段
    protected $_default_pk = 'id';
    protected $_auto_refresh = 0;
    // 默认获取状态的字段
    protected $_default_status = 'status';
    protected $_toolbar = ['filter', 'print'];// ['filter', 'exports', 'print'];
    protected $_filter = [
        //['column','data','condition','editCondition','excel']
        'items' => ['column', 'data'],
        'bottom' => false,
        'clearFilter' => true
    ];
    protected $_tabs = [
        'tabs' => [],
        'default' => 0,
        'field' => '',
    ];
    // 左侧分类
    protected $_left_tag = [];
    /**
     * 操作表宽度
     * @var int
     */
    protected $_action_width;
    protected $_excel = [];

    /**
     * @var string
     */
    protected $_model;
    protected $_cssl;
    protected $_jsl;
    protected $_with = [];
    protected $_where;
    protected $_order;
    protected $_field = ['id', 'status'];
    protected $_count = [];
    protected $_sum = [];
    protected $_avg = [];
    protected $_max = [];
    protected $_min = [];
    protected $_user;

    protected function initialize()
    {
        if ($this->request->param('callback', '')) {
            $this->_callback = $this->request->param('callback');
            $this->_callback_field = trim($this->request->param('field'));
        }
        // 复选框
        $this->_namespace = $this->module . '_' . str_replace('.', '_', $this->request->controller())
            . '_' . $this->request->action() . '_'
            . md5(json_encode($this->request->except(['v', 'user'])));
        $this->_user = false;
        //                .implode('_',$this->request->except('v'));
    }

    /**
     * 配置当前用户，设置为false则不需要权限控制
     * @param $user
     * @return $this
     */
    public function user($user)
    {
        $this->_user = $user;
        return $this;
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
        $this->_model = $model;
        $this->_pagination = $pagination;
        return $this;
    }

    /**
     * 左侧分类
     */
    public function tag($tag, $title = '标签', $value = '', $field = 'tag_id', $width = 2)
    {

        $this->_search[] = [
            'field' => $field,
            'type' => 'hidden',
            'condition' => 'in',
            'value' => $value,
        ];
        $this->_left_tag['title'] = $title;
        $this->_left_tag['data'] = $tag;
        $this->_left_tag['width'] = $width;
        $this->_left_tag['field'] = $field;
        $this->_left_tag['value'] = '';
        return $this;
    }

    /**
     * tabs
     * @param array $lists
     * @param boolean $pagination 分页
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function searchTabs($field, $lists, $default = '')
    {
        if (empty($lists)) {
            throw new Exception('数据缺失');
        }
        if (empty($default)) {
            $default = $lists[0]['id'];
        }
        $this->_search[] = [
            'field' => $field,
            'type' => 'tabs',
            'condition' => '=',
            'value' => $default,
        ];
        $this->_tabs = [
            'field' => $field,
            'tabs' => $lists,
            'default' => $default
        ];
        return $this;
    }

    /**
     * 引入css
     * @param string $css
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function css($css)
    {
        $this->_css = $css;
        return $this;
    }

    /**
     * 引入js
     * @param string $js
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function js($js)
    {
        $this->_js = $js;
        return $this;
    }

    /**
     * 筛选条件
     * @param array $filter
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function filter($filter)
    {
        $this->_filter = array_merge($this->_filter, $filter);
        return $this;
    }


    /**
     * 导出表格
     * @param string|\Closure $columns //展示字段
     * @param string $filename //支持后缀：xlsx/xls<br>
     * @param array $head
     * @param array $font
     * @param array $border
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function excel($columns = '', $filename = '', $head = [], $font = [], $border = [])
    {
        if ($columns instanceof \Closure) {
            $this->_excel = [
                'filename' => $filename,
                'columns' => $columns,
                'is_custom' => 1,
                'head' => $head,
                'url' => $this->request->url() . '&page=excel',
            ];
        } else {

            /**
             * 'family' => 'Calibri', // 字体
             * 'size' => 12,// 字号
             * 'color' => '000000', // 字体颜色
             * 'bgColor' => 'FFFFFF', // 背景颜色
             * 'cellType' => 'String' // 单元格格式 `b` 布尔值, `n` 数字, `e` 错误, `s` 字符, `d` 日期
             */
            $head = empty($head) ? [
                'family' => 'Calibri',
                'size' => 12,
                'color' => '000000',
                'bgColor' => 'FFFFFF',
                'cellType' => 'String'
            ] : $head;
            $font = empty($font) ? [
                'family' => 'Calibri',
                'size' => 15,
                'color' => '000000',
                'bgColor' => 'FFFFFF',
                'cellType' => 'String'
            ] : $font;
            $border = empty($border) ? [
                'top' => '{ style: \'thin\', color: \'FF5722\' }',
                'bottom' => '{ style: \'thin\', color: \'FF5722\' }',
                'left' => '{ style: \'thin\', color: \'FF5722\' }',
                'right' => '{ style: \'thin\', color: \'FF5722\' }'
            ] : $border;
            $this->_excel = [
                'filename' => $filename,
                'head' => $head,
                'is_custom' => 0,
                'font' => $font,
                'border' => $border,
                'columns' => $columns,
            ];
        }
        $this->keyLeftLeader('checkbox');
        $this->_toolbar[] = ['title' => '导出表格', 'layEvent' => 'LAYTABLE_EXCEL', 'icon' => 'layui-icon-export'];
        return $this;
    }

    /**
     * 模型的where条件
     * @param $where
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function where($where)
    {
        $this->_where = $where;
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
        $this->_order = $order;
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
        $this->_field = array_merge($this->_field, is_array($field) ? $field : [$field]);
        return $this;
    }

    /**
     * 配置默认主键
     * @param $pk
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setDefaultPk($pk)
    {
        $this->_default_pk = $pk;
        return $this;
    }

    /**
     * 页面自动刷新
     * @param int $time
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setAutoRefresh($time = 5000)
    {
        $this->_auto_refresh = $time;
        return $this;
    }

    /**
     * 配置默认status.
     * @param string $status
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setDefaultStatus($status)
    {
        $this->_default_status = $status;
    }

    /**
     * 设置页面标题
     * @param string $title
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function title($title)
    {
        $this->_title = $title;
        return $this;
    }

    /**
     * 设置页面隐藏数据
     * @param string $field
     * @param string $value
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function hidden($field, $value)
    {
        $this->_hidden[] = [
            'name' => $field,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * suggest 页面标题边上的提示信息
     * @param string $suggest
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function suggest($suggest)
    {
        $this->_suggest = $suggest;
        return $this;
    }

    /**
     * 统计信息
     * @param string $title
     * @param string $count
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function statistics($title, $count)
    {
        $this->_statistics[] = [
            'title' => $title,
            'count' => $count
        ];
        return $this;
    }

    /**
     * warning 页面标题边上的错误信息
     * @param string $warning
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function warning($warning)
    {
        $this->_warning = $warning;
        return $this;
    }

    /**
     * 设置回收站根据ids彻底删除的URL
     * @param string $url
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setClearUrl($url)
    {
        $this->_setClearUrl = $url;
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
        $this->_selectPostUrl = url($url);
        return $this;
    }

    /**
     * 设置搜索提交表单的URL 更新筛选搜索功能
     * @param string $url 提交的getURL
     * @param array $param GET参数
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setSearchPostUrl($url, $param = [])
    {
        $get = $this->request->get();
        $param = empty($param) ? $get : array_merge($param, $get);
        $this->_searchPostUrl = url($url, $param);
        return $this;
    }

    /**
     * 加入一个按钮
     * @param string $title
     * @param array $attr
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function button($title, $attr)
    {
        if (isset($attr['url']) && strpos($attr['url'], '/Admin')) {
            $attr['url'] = str_replace('/Admin', '/admin', $attr['url']);
        }
        if (false === $this->authCheck($attr['url'])) {
            return $this;
        }
        $this->_buttonList[] = [
            'title' => $title,
            'attr' => $attr,
        ];
        return $this;
    }

    /**
     * 加入新增按钮.
     * @param string $url
     * @param string $title
     * @param string $width
     * @param string $height
     * @param array $attr
     * @return Table
     */
    public function buttonUpdate($url = 'update', $title = '新增', $width = '', $height = '', $attr = [])
    {
        $default['url'] = $url;
        $default['class'] = 'layui-bg-green';
        $default['icon'] = 'plus';
        $default['width'] = $width ?: $this->dialog_width_default;
        $default['height'] = $height ?: $this->dialog_height_default;
        $default['data-title'] = $title != '新增' ? $title : $this->request->controller() . '新增';
        $default['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-add-' . $this->request->time());
        return $this->buttonDialog($title, array_merge($default, $attr));
    }

    /**
     * 打开全屏操作
     * @param        $url
     * @param string $title
     * @param string $icon
     * @param array $attr
     * @return Table
     */
    public function buttonFull($url, $title = '新增', $icon = 'plus', $attr = [])
    {
        $default['url'] = $url;
        $default['class'] = 'layui-bg-green';
        if (is_string($icon)) {
            $default['icon'] = $icon;
        }
        $default['width'] = '100%';
        $default['height'] = '100%';
        $default['data-title'] = $title != '新增' ? $title : $this->request->controller() . '新增';
        $default['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-add-' . $this->request->time());
        return $this->buttonDialog($title, array_merge($default, $attr));
    }

    /**
     * 自定义按钮.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     */
    public function buttonCustom($url, $title, $attr = [])
    {
        $attr['url'] = $url;
        $attr['class'] = isset($attr['class']) ? $attr['class'] : 'layui-bg-green';
        $attr['width'] = isset($attr['width']) ? $attr['width'] : $this->dialog_width_default;
        $attr['height'] = isset($attr['height']) ? $attr['height'] : $this->dialog_height_default;
        $attr['toggle'] = $this->toggle;
        $attr['event'] = 'edit';
        $attr['title'] = $title ?: $this->request->controller();
        return $this->button($title, $attr);
    }

    /**
     * button的ajax操作.
     * @param string $title
     * @param array $attr
     * @param string $toggle
     * @return Table
     */
    public function buttonDialog($title, $attr, $toggle = 'navtab')
    {
        if (false === strpos($attr['url'], '/')) {
            // 补充
            $attr['url'] = $this->module . '/' . $this->request->controller() . '/' . $attr['url'];
        }
        $attr['height'] = is_numeric($attr['height']) ? ($attr['height'] . 'px') : $attr['height'];
        $attr['width'] = is_numeric($attr['width']) ? ($attr['width'] . 'px') : $attr['width'];
        //            if (false === strpos($attr['url'], '?')) {
        //                // 补充
        //                $attr['url'] = $attr['url'] . '?auto_builder={$auto_builder}';
        //            } else {
        //                $attr['url'] = $attr['url'] . '&auto_builder={$auto_builder}';
        //            }
        return $this->button($title, array_merge($attr, [
            'toggle' => $this->toggle,
            'event' => 'popup',
        ]));
    }

    /**
     * button的ajax操作.
     * @param string $title
     * @param array $attr
     * @param string $toggle
     * @return Table
     */
    public function buttonAjax($url, $title, $toggle = 'doajax', $attr = [])
    {
        $attr['url'] = url($url);
        if (false === strpos($attr['url'], '?')) {
            // 补充
            $attr['url'] = $attr['url'] . '?auto_builder={$auto_builder}';
        } else {
            $attr['url'] = $attr['url'] . '&auto_builder={$auto_builder}';
        }

        $attr['class'] = isset($attr['class']) ? $attr['class'] : 'btn-default';
        if (!isset($attr['icon'])) {
            $attr['icon'] = 'refresh';
        }
        $attr['toggle'] = $toggle;
        $attr['event'] = 'ajax';
        return $this->button($title, $attr);
    }


    /**
     * 批量选定禁用按钮，必须有选定情况.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     */
    public function buttonDisable($url, $title = '禁用', $attr = [])
    {
        $attr['class'] = 'btn-red';
        $attr['message'] = '确定要' . $title . '么？';
        $attr['icon'] = 'minus-circle';
        $attr['type'] = 'button';
        return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 批量选定启用按钮，必须有选定情况.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     */
    public function buttonEnable($url, $title = '启用', $attr = [])
    {
        $attr['class'] = 'layui-bg-green';
        $attr['message'] = '确定要' . $title . '么？';
        $attr['icon'] = 'check-circle-o';
        $attr['type'] = 'button';
        return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 批量选定删除到回收站.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     */
    public function buttonDelete($url, $title = '删除选中', $attr = [])
    {
        $attr['class'] = 'btn-blue';
        $attr['message'] = '确定要' . $title . '么？';
        $attr['icon'] = 'trash-o';
        $attr['data-idname'] = 'id';
        $attr['data-group'] = 'ids';
        $attr['type'] = 'button';
        return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 无条件ajax请求
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     */
    public function buttonDeleteAll($url, $title = '删除所有', $attr = [])
    {
        $attr['class'] = 'btn-blue';
        $attr['message'] = '确定要' . $title . '么？';
        $attr['icon'] = 'trash-o';
        return $this->buttonAjax($url, $title, 'doajax', $attr);
    }

    /**
     * 权限检查
     * @param string $url
     * @return bool
     */
    public function authCheck($url)
    {
        if ($this->_user) {
            $url = explode('?', $url)[0];
            if (strpos($url, '.html')) {
                $url = str_replace('.html', '', $url);
            }
            if (0 === strpos($url, '/')) {
                $url = substr($url, 1);
            }
            return AuthGroupEvent::checkRule($url, $this->_user);
        } else {
            return true;
        }
    }

    /**
     * 无条件ajax请求
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function buttonRefresh($url, $title = '刷新', $attr = [])
    {
        !isset($attr['class']) && $attr['class'] = 'btn-blue';
        //         $attr['icon'] = 'trash-o';
        return $this->buttonAjax($url, $title, 'doajax', $attr);
    }

    /**
     * 根据指定条件还原禁用.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return Table
     */
    public function buttonRestore($url, $title = '还原', $attr = [])
    {
        $attr['class'] = 'btn-blue';
        $attr['message'] = '确定要' . $title . '么？';
        $attr['icon'] = 'undo';
        $attr['type'] = 'button';
        return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 彻底删除回收站.
     * @param null|string $url
     * @return Table
     */
    public function buttonClear($url = null)
    {
        if (!$url) {
            $url = $this->_setClearUrl;
        }
        $attr['class'] = 'ajax-post tox-confirm';
        $attr['data-confirm'] = '您确实要彻底删除吗？（彻底删除后不可恢复）';
        $attr['url'] = $url;
        $attr['target-form'] = 'ids';
        return $this->button('彻底删除', $attr);
    }

    /**
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonSort($url, $title = '排序', $attr = [])
    {
        $attr['url'] = $url;
        return $this->button($title, $attr);
    }

    /**
     * 复选框操作.
     * @param string $title
     * @param $url
     * @param $msg
     * @param $toggle
     * @param $idname
     * @param $group
     * @param $class
     * @param $br
     * @return $this
     * @author  微尘 <yicmf@qq.com>
     */
    public function groupAction($title, $url, $msg, $toggle, $idname = null, $group = null, $class = null, $br = null)
    {
        $this->_group[] = [
            'msg' => $msg,
            'title' => $title,
            'url' => $url,
            'toggle' => $toggle,
            'idname' => $idname,
            'group' => $group,
            'class' => $class,
            'br' => $br,
        ];
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
        $this->_search[] = [
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
        $this->_search[] = [
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
        $this->_search[] = [
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
                if (time_format($default) < time_format('now')) {
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
            'format ' => $formats[$type],
            'mark ' => [],
            'min' => $min,
            'max' => $max,
            'value' => $default,
        ];
        foreach ($options as $key => $item) {
            if (!$item) {
                unset($options[$key]);
            }
        }
        $this->_search[] = [
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
        $this->_search[] = [
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
        $this->_search[] = [
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
     * 批量添加字段信息.
     * @param array $fields
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function setKeys($fields = [])
    {
        $this->_keyList = array_merge($this->_keyList, $fields);
        return $this;
    }


    public function quickUpdate($fields, $update)
    {
        $fields = is_array($fields) ? $fields : explode(',', $field);
        foreach ($fields as $index => $item) {
            if (is_numeric($index)) {
                $this->_quick_update[$item] = ['option' => ['text'], 'qucik_edit' => $update];
            } else {
                $this->_quick_update[$index] = ['option' => $item, 'qucik_edit' => $update];
            }
        }
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
            $templet = uniqid();
            if (preg_match('/(.*)\[:(.*)\]/', $field, $matches)) {
                $field = $matches[1];
                $foreignKey = $matches[2];
            } else {
                $foreignKey = '';
            }
            $with = explode('.', $field);
            if (!isset($this->_with[$with[0]])) {
                $this->_with[$with[0]] = [$with[1]];
            } else {
                $this->_with[$with[0]][] = $with[1];
            }
            if ($foreignKey) {
                $this->_field[] = $foreignKey;
            } else {
                $this->_field[] = $with[0] . '_id';
            }
            $this->_templets[] = <<<EOF
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
            $this->_field = array_merge($this->_field, is_array($field) ? $field : explode(',', $field));
        } elseif (false !== strpos($field, '{$data')) {
            $this->_field[] = substr(explode('|', $field)[0], 7);;
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
//					'type' => $type,
//					'field' => $field,
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
                //                'even' => true,
            ];
        }
        $reKey = [];
        !empty($width) && $key['width'] = $width;
        $this->_keyList[] = $key;
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
        $templet = uniqid();
        $this->_templets[] = <<<EOF
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
            $this->_left_leader = [];
        } else {
            $this->_left_leader = ['type' => $type, 'fixed' => 'left'];
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyText($field, $title, $sort = false, $width = '', $style = '')
    {
        return $this->key($field, $title, $sort, $width, 'normal', $style);
    }


    /**
     * 显示作者
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param null $width
     * @param string|null $style
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyAuthor($field, $title, $sort = false, $width = '', $style = '')
    {
        return $this->key($field, text($title), $sort, $width, 'normal', $style, '');
    }


    /**
     * 隐藏显示
     * @param string|array $field 键名
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyHidden($field)
    {
        return $this->key($field, '', false, '', 'hidden', '', '');
    }

    /**
     * 追加字段
     * @param string|array $field 键名
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function append($field)
    {
        return $this->key($field, '', false, '', 'hidden', '', '');
    }

    /**
     * 显示金额
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDecimal($field, $title, $sort = false, $width = '', $style = '')
    {
        $templet = uniqid();

        if ($style == '' && 'zh-cn' == $langSet = $this->app->lang->defaultLangSet()) {
            $style = 'rmb';
        } elseif ($style == '') {
            $style = 'dollar';
        }

        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet">
   <i class="layui-icon layui-icon-$style"></i> {{d.$field}}
</script>
EOF;
        return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);

    }

    /**
     * 显示金额 美元
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDollar($field, $title, $sort = false, $width = '', $style = '')
    {
        $templet = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDiamond($field, $title, $sort = false, $width = '', $style = '')
    {
        $templet = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyRmb($field, $title, $sort = false, $width = '', $style = '')
    {
        $templet = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyTemplate($field, $templet, $title, $sort = false, $width = '', $style = '')
    {
        $templet_name = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyCount($field, $title, $sort = true, $width = '', $style = '')
    {
        $this->_count[] = $field;
        return $this->key($field . '_count', $title, $sort, $width, 'normal', $style);
    }

    /**
     * 显示字段
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return Table
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
     * @return Table
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
     * @return Table
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
     * @return Table
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyTime($field, $title, $format = 'yyyy-MM-dd HH:mm:ss', $sort = false, $style = '')
    {
        $templet_name = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
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
     * @return Table
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
        $templet_name = uniqid();
        $map_en = json_encode($map, JSON_UNESCAPED_UNICODE);
        $this->_templets[] = <<<EOF
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
        return $this->keyText($this->_default_pk, $title, $sort, $width, $style);
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
        $templet_name = uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title="点击查看大图"
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
        $templet_name = uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-$field-{{d.id}}"  style="display: inline-block">
  {{#  layui.each(d.{$field}, function(index, item){ }}
<img style="width: 50px;cursor:pointer" title="点击查看大图" layer-src="{{item}}" src="{{item}}">
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
            $this->_with[$temp[0]] = ['id', 'url'];
        } elseif (strpos($field, '_id')) {
            $temp = explode('_', $field);
            $this->_with[$temp[0]] = ['id', 'url'];
        } else {
            $temp = $field;
        }
        $templet_name = uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        if (is_array($temp)) {
            $with_field = $temp[0];
        } else {
            $with_field = $temp;
        }
//			$this->_templets[] = <<<EOF
//<script type="text/html" id="$templet_name">
//<div class="layer-photos"  style="display: inline-block" id="layer-photos-$with_field-{{d.id}}"><img style="display: inline-block; width: 50px;cursor:pointer" title="点击查看大图"
// layer-src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}" src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}"></div>
//</script>
//EOF;
        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title="点击查看大图"
 layer-src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}" src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}"></div>
</script>
EOF;

//			$this->_templets[] = <<<EOF
//<script type="text/html" id="$templet_name">
//<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title="点击查看大图"
// layer-src="{{ d.{$field}?d.{$field}:'{$common}' }}" src="{{ d.{$field}?d.{$field}:'{$common}' }}"></div>
//</script>
//EOF;
        //            $this->_templets[] = <<<EOF
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
        $templet_name = uniqid();
        $this->_templets[] = <<<EOF
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
    public function keyUser($field, $title, $url = '/ucenter/admin/User/update', $width = 150, $style = '')
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
        $this->_with[$with_field] = ['id', 'avatar', 'nickname'];
        $templet_name = uniqid();
        $common = config('view.tpl_replace_string.__COMMON__') . '/images/avatar_default.png';
        $url = url($url) . '?id={{d.' . $with_field . '.id}}';
        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
  {{#  if(d.{$with_field}){ }}
    <a style="cursor:pointer " lay-href="$url" >
  <img style="display: inline-block; width: 25px; height: 25px;border-radius: 50%;" src= {{ d.{$with_field}?d.{$with_field}.avatar.url:'{$common}' }}>  {{ d.{$with_field}?d.{$with_field}.nickname:'无用户' }}
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
        $templet_name = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyClosure($title, $closure, $width = '', $style = '')
    {
        $pinyin = new Pinyin();
        return $this->key($pinyin->permalink($title, '_'), text($title), false, $width, $closure, $style);
    }

    /**
     * 模板显示
     * @param string $title
     * @param string $templet
     * @param int|null $width
     * @param string|null $style
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyTemplateChild($title, $templet, $width = 80, $style = '')
    {
        $templet_name = uniqid();
        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
   $templet
</script>
EOF;
        $this->_keyList[] = [
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
     * @return Table
     */
    public function keyLink($field, $title, $url, $target = '_self', $width = '', $style = '')
    {
        // 修整添加多个空字段时显示不正常的
        $templet = uniqid();
        $this->_templets[] = <<<EOF
<script type="text/html" id="$templet">
   <i class="layui-icon layui-icon-link"></i> <a href="$url" target="$target"> {{d.$field}}</a> 
</script>
EOF;
        return $this->key($field, $title, false, $width, 'normal', $style, '#' . $templet);
    }

    /**
     * 对话框
     * @param string $field
     * @param string $title
     * @param $url string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
     * @param array $arr
     * @param int|null $width
     * @return Table
     */
    public function keyDialog($field, $title, $url, $arr = [], $width = '')
    {
        $dialog_width = isset($arr['width']) ? $arr['width'] : $this->dialog_width_default;
        $dialog_height = isset($arr['height']) ? $arr['height'] : $this->dialog_height_default;
        // 修整添加多个空字段时显示不正常的
        $templet = uniqid();
        $this->_templets[] = <<<EOF
 <script type="text/html" id="$templet">
          <a style="cursor:pointer "  lay-event="dialog" data-url="$url" data-width="$dialog_width" data-height="$dialog_height" ><i class="layui-icon layui-icon-search"></i> {{d.$field}}</a>
        </script>
EOF;
        return $this->key($field, $title, false, $width, 'normal', '', '#' . $templet);
    }

    /**
     * 预览，无响应操作
     * @param string $field
     * @param string $title
     * @param $url string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
     * @param array $arr
     * @param int|null $width
     * @return Table
     */
    public function keyView($field, $title, $url, $arr = [], $width = '')
    {
        $dialog_width = isset($arr['width']) ? $arr['width'] : $this->dialog_width_default;
        $dialog_height = isset($arr['height']) ? $arr['height'] : $this->dialog_height_default;
        // 修整添加多个空字段时显示不正常的
        $templet = uniqid();
        $this->_templets[] = <<<EOF
 <script type="text/html" id="$templet">
          <a style="cursor:pointer "  lay-event="view" data-url="$url" data-width="$dialog_width" data-height="$dialog_height" ><i class="layui-icon layui-icon-search"></i> {{d.$field}}</a>
        </script>
EOF;
        return $this->key($field, $title, false, $width, 'normal', '', '#' . $templet);
    }

    /**
     * 新的tab窗口
     * @param string $field
     * @param string $title
     * @param $url string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
     * @param int|null $width
     * @return Table
     */
    public function keyTab($field, $title, $url, $width = '')
    {
        if (false !== strpos($url, '{$')) {
            // 补充
            $url = str_replace('{$', '{{d.', $url);
            $url = str_replace('}', '}}', $url);
        }
        // 修整添加多个空字段时显示不正常的
        $templet = uniqid();
        $this->_templets[] = <<<EOF
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
     * @return Table
     */
    public function keyProgress($field, $title, $sort = false, $width = '')
    {
        // 修整添加多个空字段时显示不正常的
        $templet = uniqid();
        $this->_templets[] = <<<EOF
  <script type="text/html" id="$templet">
        <div class="layui-progress layuiadmin-order-progress" lay-filter="progress-"+ {{ d.id }} +"">
          <div class="layui-progress-bar layui-bg-blue" lay-percent= {{ d.$field }}></div>
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
        $templet_name = uniqid();
        $map = !is_null($map) ? $map : [
            -2 => '已删除',
            -1 => '禁用',
            1 => '启用',
            0 => '未审核',
            2 => '推荐',
        ];
        return $this->keyMap('status', '状态', $map, $sort, '', $style);
    }

    /**
     * 操作
     * @param $url
     * @param $title
     * @param $status
     * @param $event
     * @param $message
     * @param $class
     * @param $icon
     * @return $this
     */
    public function keyDoAction($url, $title = '操作', $status = [], $event = 'edit', $message = '', $class = '', $icon = '')
    {
        if (false === strpos($url, '/')) {
            if (false !== strpos($this->request->controller(), 'Admin.')) {
                // 补充
                $url = $this->module . '/' . lcfirst($this->request->controller()) . '/' . $url;
            } else {
                // 补充
                $url = $this->module . '/' . $this->request->controller() . '/' . $url;
            }
        }
        if (false !== strpos($url, '{$')) {
            // 补充
            $url = str_replace('{$', '{{d.', $url);
            $url = str_replace('}', '}}', $url);
        }
        // 权限检查
        if (false === $this->authCheck($url)) {
            return $this;
        }
        $attr = [];
        $attr['icon'] = $icon;
        $attr['class'] = $class;
        $attr['message'] = $message ?: ('确定' . $title . '么？');
        if (is_array($event)) {
            $attr = array_merge($attr, $event);
            $event = 'dialog';
        } elseif ($event == 'min') {
            $attr['width'] = 700;
            $attr['height'] = 360;
            $event = 'dialog';
        } elseif ($event == 'mid') {
            $attr['width'] = 900;
            $attr['height'] = 660;
            $event = 'dialog';
        } elseif ($event == 'max') {
            $attr['width'] = 600;
            $attr['height'] = 300;
            $event = 'dialog';
        }
        if ($event == 'dialog') {
            if (false === strpos($url, '?')) {
                // 补充
                $url = $url . '?_namespace_filter={namespace_filter}';
            } else {
                $url = $url . '&_namespace_filter={namespace_filter}';
            }
        }

//			data-width="{$action.attr.width|default=''}"
//           data-height="{$action.attr.height|default=''}"
//           data-message="{$action.attr.message|default=''}"
//			{$action.attr.icon}
//			{$action.attr.class

        //            $pinyin = new Pinyin();
        $this->_do_action[] = [
            'url' => $url,
            'title' => $title,
            'field' => 'do_action_' . md5($url),
            //                'field' => 'do_action_' . $pinyin->permalink($title, '_'),
            'status' => $status,
            'event' => $event,
            'attr' => $attr,
        ];
        return $this;
    }


    /**
     * 不可操作
     * @param string $title
     * @param array $status
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionDisable($title = '不可操作', $status = [])
    {
        return $this->keyDoAction('', $title, empty($status) ? [0, 1, 2] : $status, 'no', '', 'layui-btn-orange', 'stop');
    }


    /**
     * 浏览操作
     * @param string $url
     * @param string $title
     * @param array $status
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionView($url = 'view?id={$id}', $title = '详情', $status = [])
    {
        return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'tab', '', 'layui-btn-green', 'search');

    }

    /**
     * 授权操作
     * @param string $url
     * @param string $title
     * @param array $status
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionManager($url = 'manager?id={$id}', $title = '授权', $status = [])
    {
        return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'form', '', 'layui-btn-green', 'auz');
    }


    /**
     * 链接操作
     * @param string $url
     * @param string $title
     * @param array $status
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionLink($url, $title, $status = [])
    {
        return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'tab', '', 'layui-bg-green', 'link');
    }

    /**
     * ajax操作
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @param string $icon
     * @param string $class
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionAjax($url = 'delete?id={$id}', $title = '删除', $status = [], $message = '', $icon = 'set', $class = 'layui-btn-normal')
    {
        return $this->keyDoAction($url, $title, empty($status) ? [-1, 0, 1, 2] : $status, 'ajax', $message, $class, $icon);
    }

    /**
     * 删除操作
     * @param string $url
     * @param string $title
     * @param string $message
     * @param array $status
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionDelete($url = 'delete?id={$id}', $title = '删除', $status = [], $message = '')
    {
        return $this->keyDoAction($url, $title, empty($status) ? [-1, 0, 1, 2] : $status, 'ajax', $message, 'layui-btn-danger', 'delete');
    }

    /**
     *  更新操作
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $dialog false 使用tab , min max mid 分别大中小弹窗，或者数组自定义
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionUpdate($url = 'update?id={$id}', $title = '编辑', $status = [], $dialog = false)
    {
        return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, $dialog == false ? 'tab' : $dialog, '', 'layui-bg-green', 'edit');
    }

    /**
     * 彻底删除
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionRemove($url = 'clear?id={$id}', $title = '彻底删除', $status = [], $message = '')
    {
        return $this->keyDoAction($url, $title, empty($status) ? [-2] : $status, 'ajax', $message, 'btn-red', 'trash-o');
    }

    /**
     * 禁用
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionForbid($url = 'forbid?id={$id}', $title = '禁用', $status = [], $message = '')
    {
        return $this->keyDoAction($url, $title, empty($status) ? [1, 2] : $status, 'ajax', $message, 'layui-btn-danger', 'close-fill');
    }

    /**
     * 通过审核
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionToCheck($url = 'check?id={$id}', $title = '通过审核', $status = [], $message = '')
    {
        return $this->keyDoAction($url, $title, empty($status) ? [0] : $status, 'ajax', $message, 'layui-bg-green', 'ok');
    }

    /**
     * 还原
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return Table
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionRestore($url = 'restore?id={$id}', $title = '启用', $status = [], $message = '')
    {
        return $this->keyDoAction($url, $title, empty($status) ? [-1, -2] : $status, 'ajax', $message, 'btn-red', 'ok-circle');
    }

    /**
     * 设置行样式
     *      .active    鼠标悬停在行或单元格上时所设置的颜色
     *      .success    标识成功或积极的动作
     *      .info    标识普通的提示信息或动作
     *      .warning    标识警告或需要用户注意
     *      .danger    标识危险或潜在的带来负面影响的动作*
     *      例如：
     *      ->rowStyle(function($row){
     *           if (0== $row['status']) {
     *               return 'danger';
     *           }else {
     *               return '';
     *           }
     *       })
     * @param $function
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function rowStyle($function)
    {
        $this->_row_style = $function;
        return $this;
    }

    /**
     * 设置行样式
     *      ->rowStyle(function($row){
     *           if (0== $row['status']) {
     *               return 'layui-bg-cyan';
     *           }else {
     *               return '';
     *           }
     *       })
     * @param $function
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function rowClass($function)
    {
        $this->_row_class = $function;
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
        $this->_data = $data;
        $this->_pagination = $pagination;
        return $this;
    }

    /**
     * 当前列表操作宽度
     * @param int $width
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function actionWidth($width)
    {
        $this->_action_width = $width;
        return $this;
    }

    /**
     * 返回页面
     * @param string $name
     * @param array $vars
     * @return string
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function fetch($name = '', $vars = [])
    {
        if ($this->request->isPost()) {
            if ($this->request->get('__method', 'excel') == 'quick') {
                $update = $this->request->post();
                $searchWhere = $this->_searchWhere();
                foreach ($this->_quick_update as $__field => $item) {
                    try {
                        if ($update['__field'] == $__field) {
                            $qucikEdit = $item['qucik_edit'];
                            if ($qucikEdit instanceof \Closure) {
                                // 闭包
                                $qucikEdit($update, $this->_where, $searchWhere);
                            } else {
                                $this->_model::where('id', $update['id'])->where($this->_where)->update([$update['__field'] => $update['__value']]);
                            }
                        }
                        $result = ['code' => 0, 'message' => ''];
                    } catch (Exception $e) {
                        $result = ['code' => 1, 'message' => $e->getMessage()];
                    }
                }
            } else {
                try {
                    $searchWhere = $this->_searchWhere();
                    $searchOrder = $this->_searchOrder();
                    if ($this->request->has('columns')) {
                        // 获取筛选条件
                        $columns = json_decode(htmlspecialchars_decode($this->request->post('columns/s')), true);
                        $result = [];
                        $model = $this->_model;
                        if ($model instanceof \Closure) {
                            // 闭包
                            $result = [];
                        } elseif (empty($this->_data)) {
                            if (is_string($model)) {
                                $whereModel = $model::where($searchWhere)->where($this->_where);
                            } else {
                                $whereModel = $model->where($searchWhere)->where($this->_where);
                            }
                            if (count($this->_count)) {
                                $result = [];
                            } else {
                                foreach ($this->_keyList as $index => $item) {
                                    if (in_array($item['field'], $columns)) {
                                        $column = $whereModel->field($item['field'])->distinct(true)->limit(10)->column($item['field']);
                                        //										if (count($item['map']) > 0 && $column) {
                                        //											$temp = [];
                                        //											foreach ($column as $i => $co) {
                                        //												if (isset($item['map'][$co])) {
                                        //													$temp[] = $item['map'][$co];
                                        //												}
                                        //											}
                                        //											$column = $temp;
                                        //										}
                                        $result[$item['field']] = $column;
                                    }
                                }
                            }
                        } else {
                            $result = [];
                        }
                    } else {
                        $this->_field = $this->_getField($this->_field);
                        $list_rows = 1000000;
                        $page = 1;
                        $result = [];
                        $model = $this->_model;
                        if ($model instanceof \Closure) {
                            // 闭包
                            $result = $model($searchWhere, $this->_field, $searchOrder, $page, $list_rows);
                        } elseif (empty($this->_data)) {
                            $whereModel = $model::where($searchWhere)->where($this->_where);
                            $result['code'] = 0;
                            if (count($this->_count)) {
                                $lists = $whereModel->withCount($this->_count)->order($searchOrder)->limit($list_rows * ($page - 1), $list_rows)->select();
                            } else {
                                $lists = $whereModel->order($searchOrder)->limit($list_rows * ($page - 1), $list_rows)->select();
                            }
                            $result['count'] = $whereModel->count();
                        } else {
                            if ($this->_data instanceof \Closure) {
                                $data = $this->_data;
                                // 闭包
                                $lists = $data($searchWhere, $this->_field, $searchOrder, $page, $list_rows);
                            } else {
                                $lists = $this->_data;
                            }
                            if (isset($lists['code'])) {
                                $result = $lists;
                                $lists = $lists['data'];
                            } else {
                                $result['code'] = 0;
                                $result['count'] = count($this->_data);
                            }
                        }
                        // 数据转换
                        if (!empty($lists)) {
                            // 采用分页类||单纯的数据数组
                            foreach ($lists as $key => $list) {
                                $lists[$key] = $this->convertKey($list, true);
                            }
                        }
                        $result['data'] = $lists;
                    }
                } catch (Exception $e) {
                    $result = [];
                }
            }
            return json($result);
        } else {

            $__method = $this->request->get('__method', 'fetch');

            switch ($__method) {
                case 'quick':

                    foreach ($this->_quick_update as $index => $item) {

                        dump($item);
                    }
                    dump($__method);
                    break;
                case 'ajax':
                    $result = $this->_formatAjaxData();
                    return json($result);
                    break;
                default:
                    if ($name == '' && $this->request->param('__selected_type', '')) {
                        $selected_type = $this->request->param('__selected_type', '');
                        if ($selected_type == 'radio') {
                            $this->keyLeftLeader('radio');
                        } else {
                            $this->keyLeftLeader('checkbox');
                        }
                        $name = 'select';
                    } else {
                        $name = 'table';
                    }
                    $this->_formantKeyList();
                    $this->_setMenu();
                    // 显示页面
                    $this->assign('templets', $this->_templets);
                    $this->assign('do_action', $this->_do_action);
                    $this->assign('toolbar', $this->_toolbar);
                    $this->assign('namespace', $this->_namespace);
                    $this->assign('suggest', $this->_suggest);
                    $this->assign('statistics', $this->_statistics);
                    $this->assign('warning', $this->_warning);
                    $this->assign('keyList', array_values($this->_keyList));
                    $this->assign('buttonList', $this->_buttonList);
                    $this->assign('callback', $this->_callback);
                    $this->assign('excel', $this->_excel);
                    if (isset($this->_excel['filename']) && !$this->_excel['filename']) {
                        $this->_excel['filename'] = $this->_title . '_' . time_format(time(), 'Y_m_d') . '.xlsx';
                    }
                    $this->assign('filter', $this->_filter);
                    // 数据转换
                    /*
                     * 配置主键*
                     */
                    $this->assign('pk', $this->_default_pk);
                    /* 加入搜索 */
                    $search_value = [];
                    if (count($this->_search) > 0) {
                        $this->assign('searches', $this->_search);
                        if (count($this->_search_more) > 0) {
                            $this->assign('search_more', $this->_search_more);
                        }
                        foreach ($this->_search as $index => $search_item) {
                            $search_value[$search_item['field']] = $search_item['value'];
                        }
                    }
                    $this->assign('search_value', $search_value);
                    if (empty($this->_searchPostUrl)) {
                        $this->_searchPostUrl = $this->request->url();
                    }
                    if (strpos($this->_searchPostUrl, '/Admin')) {
                        $this->_searchPostUrl = str_replace('/Admin', '/admin', $this->_searchPostUrl);
                    }
                    $this->assign('searchPostUrl', $this->_searchPostUrl);
                    /* 复选框 */
                    $this->assign('group', $this->_group);
                    /* 加入筛选select */
                    $this->assign('selects', $this->_select);
                    $this->assign('selectPostUrl', $this->_selectPostUrl);
                    /* 加入隐藏表单 */
                    $this->assign('hidden', $this->_hidden);
                    $this->assign('page', $this->_pagination ? 1 : 0);
                    $this->assign('auto_refresh', $this->_auto_refresh);
                    if ($this->_tabs['field']) {
                        $this->assign('tabs', $this->_tabs['tabs']);
                        $this->assign('tabs_value', $this->_tabs['default']);
                        $this->assign('tabs_field', $this->_tabs['field']);
//                    $this->assign('tabs_value', $this->_tabs['tabs'][$this->_tabs['default']]['id']);
                    } else {
                        $this->assign('tabs', []);
                    }
                    $this->assign('tag_tree', $this->_left_tag);
                    return parent::_fetch($name, $vars);
            }
        }
    }

    protected function _formantKeyList()
    {
        foreach ($this->_keyList as $index => $item) {
            foreach ($this->_quick_update as $index2 => $item2) {
                if ($index2 == $this->_keyList[$index]['field']) {
                    $this->_keyList[$index]['edit'] = 'text';
                }
            }
            if (isset($item['type']) && $item['type'] == 'hidden') {
                unset($this->_keyList[$index]);
            } elseif (isset($item['type']) && $item['type'] == 'child') //'type'=>'child',
            {
                unset($this->_keyList[$index]['field']);
            }
        }
        if (count($this->_do_action)) {
            if (is_null($this->_action_width)) {
                $status = [];
                $object = [];
                foreach ($this->_do_action as $item) {
                    if (is_object($item['status'])) {
                        if (!in_array($item['title'], $object)) {
                            $object[] = $item['title'];
                        }
                    } else {
                        foreach ($item['status'] as $v) {
                            if (isset($status[$v])) {
                                $status[$v] = $status[$v] + 1;
                            } else {
                                $status[$v] = 1;
                            }
                        }
                    }
                }
                $max = 1;
                foreach ($status as $v) {
                    if ($max < $v) {
                        $max = $v;
                    }
                }
                $this->_action_width = (($max + count($object) - 1) * 70 + 100);
            }
            $this->_keyList[] = [
                'fixed' => 'right',
                'title' => '操作',
                'align' => 'center',
                'toolbar' => '#' . $this->_namespace . '-table-action',
                'width' => $this->_action_width
            ];
        }
        !empty($this->_left_leader) && array_unshift($this->_keyList, $this->_left_leader);
    }

    protected function _setMenu()
    {
        $get = $this->request->except(explode(',', 'v,m,status'), 'get');
        if (!empty($get)) {
            $menu_param = http_build_query($get);
        } else {
            $menu_param = '';
        }
        // 查询当前菜单
        $menu = Db::name('menu')->where('status', 1)
            ->where('param', 'in', [$menu_param, ''])
            ->where('action', $this->request->action())
            ->when($this->module, function ($query) {
                // 满足条件后执行
                $query > where('module', $this->module);
            }, function ($query) {
                // 不满足条件执行
                $query->where('controller', str_replace('.', '/', $this->request->controller()));
            })
            ->order('param DESC')
            ->find();
        if ($menu) {
            $this->_title = $menu['title'];
        }
        if ($menu && !$this->_title) {
            if ($menu['group']) {
                $this->assign('menu_group_title', $menu['group']);
            }
            if ($menu['pid']) {
                $p_menu = Db::name('menu')->where('status', 1)
                    ->where('id', $menu['pid'])
                    ->find();
                if ($p_menu) {
                    $this->assign('p_menu_title', $p_menu['title']);
                } else {
                    $this->assign('p_menu_title', $menu['title']);
                }
            } else {
                $this->assign('p_menu_title', $menu['title']);
            }
        }
        $this->assign('menu_title', $this->_title);
    }

    protected function _formatAjaxData()
    {
        try {
            $this->_field = $this->_getField($this->_field);
            $list_rows = $this->request->has('limit', 'param') ? $this->request->param('limit') : Config::get('paginate.list_rows');
            $page = $this->request->has('page', 'param') ? $this->request->param('page') : 1;
            $result = [];
            $searchWhere = $this->_searchWhere();
            $searchOrder = $this->_searchOrder();
//                    dump($searchWhere);
//                    exit();
            $model = $this->_model;
            if ($model instanceof \Closure) {
                // 闭包
                $result = $model($searchWhere, $this->_field, $searchOrder, $page, $list_rows);
            } elseif (!is_null($model)) {
                if (is_string($model)) {
                    $whereModel = $model::where($searchWhere)
//							->field(implode($this->_field, ','))
                        ->where($this->_where);
                } else {
                    $whereModel = $model->where($searchWhere)
//							->field(implode($this->_field, ','))
                        ->where($this->_where);
                }

                $result['code'] = 0;
                $result['count'] = $whereModel->count();
                if (count($this->_count)) {
                    $lists = $whereModel->withCount($this->_count)
                        ->order($searchOrder)
                        ->limit($list_rows * ($page - 1), $list_rows)->select();
                } else {
                    $lists = $whereModel
                        ->order($searchOrder)
                        ->limit($list_rows * ($page - 1), $list_rows)->select();
                }
            } else {
                if ($this->_data instanceof \Closure) {
                    $data = $this->_data;
                    // 闭包
                    $lists = $data($searchWhere, $this->_field, $searchOrder, $page, $list_rows);
                } else {
                    $lists = $this->_data;
                }

                if (isset($lists['code'])) {
                    $result = $lists;
                    $lists = $lists['data'];
                } else {
                    $result['code'] = 0;
                    $result['count'] = count($lists);
                }
            }
//					dump($lists);
//					exit();
            // 数据转换
            if (!empty($lists)) {
                // 采用分页类||单纯的数据数组
                foreach ($lists as $key => $list) {
                    $lists[$key] = $this->convertKey($list);
                }
            }
            $result['data'] = $lists;
        } catch (Exception $e) {
            $result['code'] = 0;
            $result['message'] = $e->getMessage();
        }
        return $result;
    }


    protected function _getField($field)
    {
        $field = array_unique($field);
        $model = $this->_model;
        if (!is_null($model)) {
            if (is_string($model)) {
                $db_fields = $model::getTableFields();
            } else {
                $db_fields = $model->getTableFields();
            }
            foreach ($field as $index => $item) {
                if (!strpos($item, ' ') && !in_array($item, $db_fields)) {
                    unset($field[$index]);
                }
            }
            $field = array_values($field);
        }
        return $field;
    }

    protected function _searchOrder()
    {
        if ($this->request->has('order_field')) {
            return $this->request->param('order_field') . ' ' . $this->request->param('order');
        } elseif (!$this->_order) {
            return 'id DESC';
        } else {
            return $this->_order;
        }
    }

    protected function _searchWhere()
    {
        $fields = $this->request->param('field/a');
//        dump($fields);
//        dump($this->_search);
        $model = $this->_model;
        if (!is_null($model)) {
            if (is_string($model)) {
                $db_fields = $model::getTableFields();
            } else {
                $db_fields = $model->getTableFields();
            }
        } else {
            $db_fields = $this->_field;
        }
        $where = [];
        $_search_field = [];
        if (is_array($fields)) {
            foreach ($this->_search as $search) {
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
                        $ids = Db::name('user')
                            ->where('status', '>', -2)
                            ->where('id|account|email|nickname', 'like', '%' . $fields[$search['field']] . '%')
                            ->column('id');
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
//					foreach ($this->_search as $search) {
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
        $filterSos = json_decode(htmlspecialchars_decode($this->request->param('filterSos/s')), true);

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

    private function _getMode($filter)
    {
        if ('in' == $filter['mode']) {
            $data = [$filter['field'], 'in', $filter['values']];
//				$data = [$filter['field'], 'in', $this->_getFieldValue($filter['field'], $filter['values'])];
        } elseif ('condition' == $filter['mode']) {
            if ('eq' == $filter['type']) {
                $data = [$filter['field'], '=', $this->_getFieldValue($filter['field'], $filter['value'])];
            }
        }
        return $data;
    }

    private function _getFieldValue($field, $value)
    {
        foreach ($this->_keyList as $index => $item) {
            if ($item['field'] == $field) {
                if (count($item['map']) > 0) {
                    if (is_array($value)) {
                        $temp = [];
                        foreach ($value as $va) {
                            foreach ($item['map'] as $i => $co) {
                                if ($co == $va) {
                                    $temp[] = $i;
                                    break;
                                }
                            }
                        }
                        $value = $temp;
                    } else {
                        foreach ($item['map'] as $i => $co) {
                            if ($co == $value) {
                                $value = $i;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $value;
    }

    /**
     * 数据处理
     */
    private function convertKey($data, $excel = false)
    {
        $conver_data = [];
        isset($data['status']) && $conver_data['status'] = $data['status'];
        isset($data['id']) && $conver_data['id'] = $data['id'];

        foreach ($this->_keyList as $key) {
            if (isset($key['field'])) {

                if (isset($key['type']) && $key['type'] instanceof \Closure) {
                    // 闭包
                    $conver_data[$key['field']] = $key['type']($data, $excel);
                } elseif (false !== strpos($key['field'], ',')) {
                    $fields = explode(',', $key['field']);
                    foreach ($fields as $field) {
                        $conver_data[$field] = $data[$field];
                    }
                } else {
                    if (false !== strpos($key['field'], '{$')) {
                        $value = $this->app['view']->display($key['field'], ['data' => $data]);
                    } elseif (false === strpos($key['field'], '{$') && strpos($key['field'], '.')) {
                        $field = explode('.', $key['field']);
                        $conver_data[$field[0]][$field[1]] = $data[$field[0]] ? $data[$field[0]][$field[1]] : '';
                    } else {
                        $conver_data[$key['field']] = $data[$key['field']];
                    }
                }
            }
        }
        foreach ($this->_with as $key => $items) {
            if (isset($data[$key]) && !is_string($data[$key])) {
                $temp = [];
                foreach ($items as $item) {
//						$conver_data[$key][$item] = $data[$key][$item];
                    $temp[$item] = $data[$key][$item];
                }
                $conver_data[$key] = $temp;
            } else {
                $conver_data[$key] = null;
            }
        }
        foreach ($this->_do_action as $item) {
            if ($item['status'] instanceof \Closure) {
                $closure = $item['status'];
                $conver_data[$item['field']] = $closure($data, $item);
            }
        }
        if ($this->_row_style instanceof \Closure) {
            $closure = $this->_row_style;
            $conver_data['_row_style'] = $closure($data, $item);
        }
        if ($this->_row_class instanceof \Closure) {
            $closure = $this->_row_class;
            $conver_data['_row_class'] = $closure($data, $item);
        }
        //            if ($excel)
        //            {
        //                foreach ($conver_data as $index => &$conver_datum) {
        //                    dump(htmlspecialchars_decode($conver_datum));
        //                }
        //            }
        //            exit();
        return $conver_data;
    }
}
