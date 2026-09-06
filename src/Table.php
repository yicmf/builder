<?php

// +----------------------------------------------------------------------
// | builder
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2026 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

namespace yicmf\builder;

// 2026-09-06 解耦收尾：以下三个 app\* 引用全部移出包依赖，原实现改为由项目侧服务提供者注入
// （默认权限检查回调见 app\common\service\BuilderService::boot，通过 Table::setDefaultAuthChecker 注入）
// use app\ucenter\event\AuthGroup as AuthGroupEvent;
use think\exception\HttpException;
use yicmf\tools\ChinesePinyin;
use think\Model;
use think\facade\Db;
use think\db\Where;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Hook;
use think\Exception;
// 2026-09-06 解耦收尾：Picture/Attachment 经排查为死引用（无任何活跃调用），注释保留
// use app\file\model\Picture as PictureModel;
// use app\file\model\Attachment as AttachmentModel;
use yicmf\builder\table\ColumnBuilder;
use yicmf\builder\table\SearchBuilder;
use yicmf\builder\table\ButtonBuilder;
use yicmf\builder\table\QueryResolver;

class Table extends Builder
{
    private $_title;
    private $_namespace;

    private $_suggest;
    private $_statistics;

    private $_warning;

    /** 2026-09-06 拆分重构：列定义状态持有者 */
    private $columns;

    /** 2026-09-06 拆分重构：搜索配置持有者 */
    private $searchBuilderObj;

    /** 2026-09-06 拆分重构：按钮配置持有者 */
    private $buttonBuilderObj;

    /** 2026-09-06 拆分重构：查询配置持有者 */
    private $queryResolverObj;

    /** 2026-09-06 解耦：可注入的权限检查回调，签名 function(string $url, $user): bool */
    protected $authChecker;

    /**
     * 2026-09-06 解耦收尾：静态默认权限检查回调（项目级，服务提供者注入）
     * 优先级低于实例回调（setAuthChecker）；由 app\common\service\BuilderService 注入，
     * 包装原默认实现 app\ucenter\event\AuthGroup::checkRule
     * @var callable|null
     */
    protected static $defaultAuthChecker;

    /**
     * 注入项目级默认权限检查回调（2026-09-06 解耦收尾，由服务提供者调用）
     * @param callable|null $checker function(string $url, $user): bool
     */
    public static function setDefaultAuthChecker($checker)
    {
        self::$defaultAuthChecker = $checker;
    }

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
    protected $_total_row = [];
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
    protected $_where = [];
    protected $_order;
    protected $_field = ['id', 'status'];
    protected $_count = [];
    protected $_hidden_field = [];
    protected $_sum = [];
    protected $_avg = [];
    protected $_max = [];
    protected $_min = [];
    protected $_user;

    /**
	 * 列定义构建器（懒加载，兼容跳过构造函数的测试场景）
	 * @return \yicmf\builder\table\ColumnBuilder
	 */
	private function columnBuilder()
	{
		if (!isset($this->columns)) {
			$this->columns = new ColumnBuilder($this->_default_pk);
		}
		return $this->columns;
	}

	/**
	 * 搜索配置构建器（懒加载，兼容跳过构造函数的测试场景）
	 * @return \yicmf\builder\table\SearchBuilder
	 */
	private function searchBuilder()
	{
		if (!isset($this->searchBuilderObj)) {
			$this->searchBuilderObj = new SearchBuilder();
		}
		return $this->searchBuilderObj;
	}

	/**
	 * 按钮配置构建器（懒加载，兼容跳过构造函数的测试场景）
	 * @return \yicmf\builder\table\ButtonBuilder
	 */
	private function buttonBuilder()
	{
		if (!isset($this->buttonBuilderObj)) {
			$this->buttonBuilderObj = new ButtonBuilder($this);
		}
		return $this->buttonBuilderObj;
	}

	/**
	 * 查询配置构建器（懒加载，兼容跳过构造函数的测试场景）
	 * 2026-09-06 拆分重构：自 Table 迁出的查询状态持有者
	 * @return \yicmf\builder\table\QueryResolver
	 */
	private function queryResolver()
	{
		if (!isset($this->queryResolverObj)) {
			$this->queryResolverObj = new QueryResolver($this);
		}
		return $this->queryResolverObj;
	}

	/**
	 * 2026-09-06 拆分重构：供 QueryResolver 写入快捷编辑模板
	 */
	public function getColumnBuilder()
	{
		return $this->columnBuilder();
	}

	/**
	 * 2026-09-06 拆分重构：供 ButtonBuilder 访问当前请求
	 */
	public function getRequest()
	{
		return $this->request;
	}
	/**
	 * 2026-09-06 拆分重构：供 ButtonBuilder 访问当前模块名
	 */
	public function getModule()
	{
		return $this->module;
	}
	/**
	 * 2026-09-06 拆分重构：供 ButtonBuilder 访问交互方式配置
	 */
	public function getToggle()
	{
		return $this->toggle;
	}
	/**
	 * 2026-09-06 拆分重构：供 ButtonBuilder 访问弹窗默认宽度
	 */
	public function getDialogWidth()
	{
		return $this->dialog_width_default;
	}
	/**
	 * 2026-09-06 拆分重构：供 ButtonBuilder 访问弹窗默认高度
	 */
	public function getDialogHeight()
	{
		return $this->dialog_height_default;
	}
	/**
	 * 2026-09-06 拆分重构：供 ButtonBuilder 访问当前用户
	 */
	public function getUser()
	{
		return $this->_user;
	}

	/**
	 * 解析搜索可用的数据表字段（2026-09-06 拆分重构：自原 _searchWhere 抽出）
	 * @return array
	 */
	private function resolveSearchDbFields()
	{
		$model = $this->queryResolver()->getModel(); // 2026-09-06 拆分重构：改为从QueryResolver读取
		// $model = $this->_model;
		if (!is_null($model)) {
			if (is_string($model)) {
				$db_fields = $model::getTableFields();
			} else {
				$db_fields = $model->getTableFields();
			}
		} else {
			$db_fields = array_merge($this->queryResolver()->getField(), $this->columnBuilder()->getExtraFields()); // 2026-09-06 拆分重构：改为从QueryResolver读取
			// $db_fields = array_merge($this->_field, $this->columnBuilder()->getExtraFields());
		}
		return $db_fields;
	}

    /**
     * 初始化表格构建器
     * @return $this
     */
    protected function initialize()
    {
        if ($this->request->param('callback', '')) {
            $this->_callback = $this->request->param('callback');
            $this->_callback_field = trim($this->request->param('field'));
        }
        // 复选框
//        $this->_namespace = $this->module . '_' . str_replace('.', '_', $this->request->controller())
//            . '_' . $this->request->action() . '_'
//            . md5(json_encode($this->request->except(['v', 'user'])));
        $this->_namespace = uniqid();
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
        $this->queryResolver()->model(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_model = $model;
        //         $this->_pagination = $pagination;
        //         return $this;
    }

    /**
     * 左侧分类
     */
    public function tag($tag, $title = '标签', $value = '', $field = 'tag_id', $width = 2)
    {

        $this->searchBuilder()->pushSearch([
            'field' => $field,
            'type' => 'hidden',
            'condition' => 'in',
            'value' => $value,
        ]); // 2026-09-06 拆分重构：改为写入SearchBuilder
        // $this->_search[] = [
        //     'field' => $field,
        //     'type' => 'hidden',
        //     'condition' => 'in',
        //     'value' => $value,
        // ];
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
        $this->searchBuilder()->pushSearch([
            'field' => $field,
            'type' => 'tabs',
            'condition' => '=',
            'value' => $default,
        ]); // 2026-09-06 拆分重构：改为写入SearchBuilder
        // $this->_search[] = [
        //     'field' => $field,
        //     'type' => 'tabs',
        //     'condition' => '=',
        //     'value' => $default,
        // ];
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
     * 筛选条件
     * @param array $filter
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function totalRowField($field,$templet)
    {
        $this->queryResolver()->totalRowField(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_total_row[] = ['field' => $field, 'templet' => $templet];
        //         return $this;
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
     * 模型的where条件，用法与模型where一致，支持链式多次调用（条件之间为AND关系）
     * where('id', 1) 等于 where('id', '=', 1)
     * where('id', '>', 1) / where('status', 1) / where(['status' => 1]) / where([['id', '>', 1]])
     * where('status=1') 字符串原生条件 / where(function($q){...}) 闭包子查询
     * 多次调用会累积为 AND 条件（与模型链式 where 一致）
     * @param mixed ...$args 透传给模型 where 的参数（1/2/3 个，或数组/字符串/闭包）
     * @return $this
     * [Buddy 2026-08-28] 调整：改为条件包数组累积，兼容模型一致的 1/2/3 参数及闭包/字符串，支持链式
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function where(...$args)
    {
        $this->queryResolver()->where(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_where[] = $args;
        //         return $this;
    }

    /**
     * 将 Table 上累积的 where 条件包应用到查询对象
     * 每个条件包是一组传给模型 where 的参数（支持数组/字符串/闭包/2参/3参）
     * [Buddy 2026-08-28] 新增：统一消费 _where 条件包，兼容链式多次调用
     * @param \think\db\Query|\think\Model $query
     * @return mixed
     */
    protected function _applyWhere($query)
    {
        $wherePacks = $this->queryResolver()->getWhere(); // 2026-09-06 拆分重构：改为从QueryResolver读取
        // foreach ($this->_where as $pack) {
        foreach ($wherePacks as $pack) {
            $query = $query->where(...$pack);
        }
        return $query;
    }

    /**
     * 模型排序
     * @param $order
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function order($order)
    {
        $this->queryResolver()->order(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_order = $order;
        //         return $this;
    }

    /**
     * 模型指定字段
     * @param string $field
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function field($field)
    {
        $this->queryResolver()->field(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_field = array_merge($this->_field, is_array($field) ? $field : [$field]);
        //         return $this;
    }

    /**
     * 模型指定字段
     * @param string $field
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function hiddenField($field)
    {
        $this->queryResolver()->hiddenField(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_hidden_field = array_merge($this->_hidden_field, is_array($field) ? $field : [$field]);
        //         return $this;
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
        $this->searchBuilder()->setClearUrl(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_setClearUrl = $url;
        //         return $this;
    }

    /**
     * 筛选下拉选择url
     * @param string $url
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function setSelectPostUrl($url)
    {
        $this->searchBuilder()->setSelectPostUrl(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_selectPostUrl = url($url);
        //         return $this;
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
        $param = empty($param) ? $get : $param;
        $this->searchBuilder()->setSearchPostUrlValue(url($url, $param)); // 2026-09-06 拆分重构：改为写入SearchBuilder
        // $this->_searchPostUrl = url($url, $param);
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
        $this->buttonBuilder()->button(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         if (isset($attr['url']) && strpos($attr['url'], '/Admin')) {
        //             $attr['url'] = str_replace('/Admin', '/admin', $attr['url']);
        //         }
        //         if (false === $this->authCheck($attr['url'])) {
        //             return $this;
        //         }
        //         $this->_buttonList[] = [
        //             'title' => $title,
        //             'attr' => $attr,
        //         ];
        //         return $this;
    }

    /**
     * 加入新增按钮.
     * @param string $url
     * @param string $title
     * @param string $width
     * @param string $height
     * @param array $attr
     * @return $this
     */
    public function buttonUpdate($url = 'update', $title = '新增', $width = '', $height = '', $attr = [])
    {
        $this->buttonBuilder()->buttonUpdate(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $default['url'] = $url;
        //         $default['class'] = 'layui-bg-green';
        //         $default['icon'] = 'plus';
        //         $default['width'] = $width ?: $this->dialog_width_default;
        //         $default['height'] = $height ?: $this->dialog_height_default;
        //         $default['data-title'] = $title != '新增' ? $title : $this->request->controller() . '新增';
        //         $default['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-add-' . $this->request->time());
        //         return $this->buttonDialog($title, array_merge($default, $attr));
    }

    /**
     * 导入表格
     * @param string $url
     * @param string $title
     * @return $this
     */
    public function buttonExcelImport($url = 'import', $title = '导入',$attr = [])
    {
        $this->buttonBuilder()->buttonExcelImport(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         if (false === strpos($url, '/')) {
        //             // 补充
        //             if ($this->module)
        //             {
        //                 $url = $this->module . '/' . $this->request->controller() . '/' . $url;
        //             }else{
        //                 $url =  $this->request->controller() . '/' . $url;
        //             }
        //         }
        //         $default['url'] = $url;
        //         $default['class'] = 'layui-bg-green';
        //         $default['icon'] = 'plus';
        //         $default['event'] = 'import';
        //         $default['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-add-' . $this->request->time());
        //
        //         return $this->button($title, array_merge($default, $attr));
    }

    /**
     * 打开全屏操作
     * @param        $url
     * @param string $title
     * @param string $icon
     * @param array $attr
     * @return $this
     */
    public function buttonFull($url, $title = '新增', $icon = 'plus', $attr = [])
    {
        $this->buttonBuilder()->buttonFull(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $default['url'] = $url;
        //         $default['class'] = 'layui-bg-green';
        //         if (is_string($icon)) {
        //             $default['icon'] = $icon;
        //         }
        //         $default['width'] = '100%';
        //         $default['height'] = '100%';
        //         $default['data-title'] = $title != '新增' ? $title : $this->request->controller() . '新增';
        //         $default['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-add-' . $this->request->time());
        //         return $this->buttonDialog($title, array_merge($default, $attr));
    }

    /**
     * 自定义按钮.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonCustom($url, $title, $attr = [])
    {
        $this->buttonBuilder()->buttonCustom(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['url'] = $url;
        //         $attr['class'] = isset($attr['class']) ? $attr['class'] : 'layui-bg-green';
        //         $attr['width'] = isset($attr['width']) ? $attr['width'] : $this->dialog_width_default;
        //         $attr['height'] = isset($attr['height']) ? $attr['height'] : $this->dialog_height_default;
        //         $attr['toggle'] = $this->toggle;
        //         $attr['event'] = 'edit';
        //         $attr['title'] = $title ?: $this->request->controller();
        //         return $this->button($title, $attr);
    }

    /**
     * button的ajax操作.
     * @param string $title
     * @param array $attr
     * @param string $toggle
     * @return $this
     */
    public function buttonDialog($title, $attr, $toggle = 'navtab')
    {
        $this->buttonBuilder()->buttonDialog(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         if (false === strpos($attr['url'], '/')) {
        //             // 补充
        //             if ($this->module)
        //             {
        //                 $attr['url'] = $this->module . '/' . $this->request->controller() . '/' . $attr['url'];
        //             }else{
        //                 $attr['url'] =  $this->request->controller() . '/' . $attr['url'];
        //             }
        //         }
        //         $attr['height'] = is_numeric($attr['height']) ? ($attr['height'] . 'px') : $attr['height'];
        //         $attr['width'] = is_numeric($attr['width']) ? ($attr['width'] . 'px') : $attr['width'];
        //         //            if (false === strpos($attr['url'], '?')) {
        //         //                // 补充
        //         //                $attr['url'] = $attr['url'] . '?auto_builder={$auto_builder}';
        //         //            } else {
        //         //                $attr['url'] = $attr['url'] . '&auto_builder={$auto_builder}';
        //         //            }
        //         return $this->button($title, array_merge($attr, [
        //             'toggle' => $this->toggle,
        //             'event' => 'popup',
        //         ]));
    }

    /**
     * button的ajax操作.
     * @param string $title
     * @param array $attr
     * @param string $toggle
     * @return $this
     */
    public function buttonAjax($url, $title, $toggle = 'doajax', $attr = [])
    {
        $this->buttonBuilder()->buttonAjax(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['url'] = url($url);
        //         if (false === strpos($attr['url'], '?')) {
        //             // 补充
        //             $attr['url'] = $attr['url'] . '?auto_builder={$auto_builder}';
        //         } else {
        //             $attr['url'] = $attr['url'] . '&auto_builder={$auto_builder}';
        //         }
        //
        //         $attr['class'] = isset($attr['class']) ? $attr['class'] : 'btn-default';
        //         if (!isset($attr['icon'])) {
        //             $attr['icon'] = 'refresh';
        //         }
        //         $attr['toggle'] = $toggle;
        //         $attr['event'] = 'ajax';
        //         return $this->button($title, $attr);
    }


    /**
     * 批量选定禁用按钮，必须有选定情况.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonDisable($url, $title = '禁用', $attr = [])
    {
        $this->buttonBuilder()->buttonDisable(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['class'] = 'btn-red';
        //         $attr['message'] = '确定要' . $title . '么？';
        //         $attr['icon'] = 'minus-circle';
        //         $attr['type'] = 'button';
        //         return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 批量选定启用按钮，必须有选定情况.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonEnable($url, $title = '启用', $attr = [])
    {
        $this->buttonBuilder()->buttonEnable(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['class'] = 'layui-bg-green';
        //         $attr['message'] = '确定要' . $title . '么？';
        //         $attr['icon'] = 'check-circle-o';
        //         $attr['type'] = 'button';
        //         return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 批量选定删除到回收站.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonDelete($url, $title = '删除选中', $attr = [])
    {
        $this->buttonBuilder()->buttonDelete(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['class'] = 'btn-blue';
        //         $attr['message'] = '确定要' . $title . '么？';
        //         $attr['icon'] = 'trash-o';
        //         $attr['data-idname'] = 'id';
        //         $attr['data-group'] = 'ids';
        //         $attr['type'] = 'button';
        //         return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 无条件ajax请求
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonDeleteAll($url, $title = '删除所有', $attr = [])
    {
        $this->buttonBuilder()->buttonDeleteAll(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['class'] = 'btn-blue';
        //         $attr['message'] = '确定要' . $title . '么？';
        //         $attr['icon'] = 'trash-o';
        //         return $this->buttonAjax($url, $title, 'doajax', $attr);
    }

    /**
     * 权限检查
     * @param string $url
     * @return bool
     */
    public function authCheck($url)
    {
        // 2026-09-06 解耦收尾：权限检查回调三级解析——
        // 1) 实例回调（setAuthChecker 注入，优先级最高）
        if (isset($this->authChecker) && is_callable($this->authChecker)) {
            return (bool) call_user_func($this->authChecker, $url, $this->_user);
        }
        // 2) 静态默认回调（由项目侧服务提供者注入，见 app\common\service\BuilderService::boot，
        //    其内包装 app\event\ucenter\AuthGroup::checkRule）。
        //    与原实现语义一致：仅在有用户时执行权限判定（_user 为 false/null 直接放行），
        //    且调用前先做 URL 规整（去 query、去 .html 后缀、去前导斜杠）
        if (isset(self::$defaultAuthChecker) && is_callable(self::$defaultAuthChecker)) {
            if (!$this->_user) {
                return true;
            }
            $checkUrl = explode('?', $url)[0];
            if (strpos($checkUrl, '.html')) {
                $checkUrl = str_replace('.html', '', $checkUrl);
            }
            if (0 === strpos($checkUrl, '/')) {
                $checkUrl = substr($checkUrl, 1);
            }
            return (bool) call_user_func(self::$defaultAuthChecker, $checkUrl, $this->_user);
        }
        // 3) 均未注入时放行（authCheck 仅控制按钮可见性，真实鉴权仍由 UserAuth 中间件执行）；
        //    原 AuthGroupEvent::checkRule 逻辑已整体迁至项目侧注入实现，注释保留如下
        // if ($this->_user) {
        //     $url = explode('?', $url)[0];
        //     if (strpos($url, '.html')) {
        //         $url = str_replace('.html', '', $url);
        //     }
        //     if (0 === strpos($url, '/')) {
        //         $url = substr($url, 1);
        //     }
        //     return AuthGroupEvent::checkRule($url, $this->_user);
        // } else {
        //     return true;
        // }
        return true;
    }

    /**
     * 注入自定义权限检查回调（2026-09-06 解耦：替代对 app\ucenter 的硬依赖）
     * @param callable|null $checker function(string $url, $user): bool
     * @return $this
     */
    public function setAuthChecker($checker)
    {
        $this->authChecker = $checker;
        return $this;
    }

    /**
     * 无条件ajax请求
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function buttonRefresh($url, $title = '刷新', $attr = [])
    {
        $this->buttonBuilder()->buttonRefresh(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         !isset($attr['class']) && $attr['class'] = 'btn-blue';
        //         //         $attr['icon'] = 'trash-o';
        //         return $this->buttonAjax($url, $title, 'doajax', $attr);
    }

    /**
     * 根据指定条件还原禁用.
     * @param string $url
     * @param string $title
     * @param array $attr
     * @return $this
     */
    public function buttonRestore($url, $title = '还原', $attr = [])
    {
        $this->buttonBuilder()->buttonRestore(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['class'] = 'btn-blue';
        //         $attr['message'] = '确定要' . $title . '么？';
        //         $attr['icon'] = 'undo';
        //         $attr['type'] = 'button';
        //         return $this->buttonAjax($url, $title, 'doajaxchecked', $attr);
    }

    /**
     * 彻底删除回收站.
     * @param null|string $url
     * @return $this
     */
    public function buttonClear($url = null)
    {
        if (!$url) {
            $url = $this->searchBuilder()->getSetClearUrl(); // 2026-09-06 拆分重构：改为从SearchBuilder读取
            // $url = $this->_setClearUrl;
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
        $this->buttonBuilder()->buttonSort(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $attr['url'] = $url;
        //         return $this->button($title, $attr);
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
        $this->buttonBuilder()->groupAction(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         $this->_group[] = [
        //             'msg' => $msg,
        //             'title' => $title,
        //             'url' => $url,
        //             'toggle' => $toggle,
        //             'idname' => $idname,
        //             'group' => $group,
        //             'class' => $class,
        //             'br' => $br,
        //         ];
        //         return $this;
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
        $this->searchBuilder()->searchText(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'type' => 'text',
        //             'condition' => '=',
        //             'placeholder' => $placeholder,
        //             'value' => $default,
        //             'attr' => $attr,
        //         ];
        //         return $this;
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
        $this->searchBuilder()->searchTextLike(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'type' => 'text',
        //             'condition' => 'like',
        //             'value' => $default,
        //             'placeholder' => $placeholder,
        //             'attr' => $attr,
        //         ];
        //         return $this;
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
        $this->searchBuilder()->searchTextIn(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'type' => 'text',
        //             'condition' => 'in',
        //             'value' => $default,
        //             'placeholder' => $placeholder,
        //             'attr' => $attr,
        //         ];
        //         return $this;
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
        $this->searchBuilder()->searchUser(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'type' => 'text',
        //             'condition' => 'search_user',
        //             'value' => $default,
        //             'placeholder' => $placeholder,
        //             'attr' => $attr,
        //         ];
        //         return $this;
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
        $this->searchBuilder()->searchDate(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $formats = [
        //             'year' => 'yyyy',
        //             'date' => 'yyyy-MM-dd',
        //             'datetime' => 'yyyy-MM-dd HH:mm:ss',
        //         ];
        //         $format = [
        //             'date' => 'Y-m-d',
        //             'datetime' => 'Y-m-d H:i:s',
        //         ];
        //
        //         if (is_string($default)) {
        //             if (!strpos($default, ' - ')) {
        //                 if (strtotime($default) < time()) {
        //                     $default = time_format($default, $format[$type]) . ' - ' . time_format('now', $format[$type]);
        //                 } else {
        //                     $default = time_format('now', $format[$type]) . ' - ' . time_format($default, $format[$type]);
        //                 }
        //             }
        //         }
        //         $options = [
        //             'elem' => '#j_table_builder_' . (strpos($field, '|') ? md5($field) : $field),
        //             'type' => $type,
        //             'range' => $range,
        //             'format' => $formats[$type],
        //             'mark' => [],
        //             'min' => $min,
        //             'max' => $max,
        //             'value' => $default,
        //         ];
        //         foreach ($options as $key => $item) {
        //             if (!$item) {
        //                 unset($options[$key]);
        //             }
        //         }
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'type' => 'datepicker',
        //             'value' => $default,
        //             'condition' => 'between',
        //             'placeholder' => $placeholder,
        //             'width' => $width,
        //             'options' => $options,
        //         ];
        //         return $this;
        //
        //     }
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
        $this->searchBuilder()->searchDateTimeRange(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         return $this->searchDate($field, $title, $placeholder, $default, $width, $min, $max, 'datetime', true);
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
        $this->searchBuilder()->searchDateRange(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         return $this->searchDate($field, $title, $placeholder, $default, $width, $min, $max, 'date', true);
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
        $this->searchBuilder()->searchBool(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $options = [
        //             [
        //                 'id' => 0,
        //                 'value' => '否',
        //             ],
        //             [
        //                 'id' => 1,
        //                 'value' => '是',
        //             ],
        //         ];
        //
        //         return $this->searchSelect($field, $title, $options, $des, $default, $attr);
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
        $this->searchBuilder()->searchSelect(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'value' => $default,
        //             'default' => $default,
        //             'type' => 'select',
        //             'placeholder' => $placeholder,
        //             'attr' => $attr,
        //             'condition' => '=',
        //             'options' => $options,
        //         ];
        //         return $this;
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
        $this->searchBuilder()->search(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $this->_search[] = [
        //             'title' => $title,
        //             'field' => $field,
        //             'value' => $default,
        //             'type' => $type,
        //             'condition' => '=',
        //             'placeholder' => $placeholder,
        //             'attr' => $attr,
        //             'options' => $options,
        //         ];
        //         return $this;
    }

    /**
     * 批量添加字段信息.
     * @param array $fields
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function setKeys($fields = [])
    {
        $this->columnBuilder()->setKeys(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $this->_keyList = array_merge($this->_keyList, $fields);
        //         return $this;
    }


    /**
     * 设置需要快捷编辑（行内编辑）的字段
     * 支持文本、下拉（select）、开关（switch）等类型，select/switch 会自动生成对应模板
     * @param array|string $fields 字段名列表，可为数组或逗号分隔的字符串，也可为字段名=>配置的数组
     * @param mixed $update 快捷编辑提交的附加参数（是否可编辑等）
     * @return $this
     */
    public function quickUpdate($fields, $update=null)
    {
        $this->queryResolver()->quickUpdate(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：quickUpdate 迁至 table/QueryResolver（模板写入经 getColumnBuilder 回调），原实现注释保留
        //
        //         $fields = is_array($fields) ? $fields : explode(',', $fields);
        //         foreach ($fields as $index => $item) {
        //             if (is_numeric($index)) {
        //                 $this->_quick_update[$item] = ['option' => ['type'=>'text'], 'qucik_edit' => $update];
        //             } else {
        //                 if ($item['type'] == 'select')
        //                 {
        //                     $templet = 'k'.uniqid();
        //                     $op = json_encode($item['option']);
        //                     $this->columnBuilder()->templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //   {{#  var cityList = $op; }}
        //   <select name="$index" lay-filter="select-demo" lay-append-to="body"  lay-ignore>
        //     <option value="">请选择</option>
        //     {{#  layui.each(cityList, function(i, v){ }}
        //     <option value="{{= v }}" {{= v === d.city ? 'selected' : '' }}>{{= v }}</option>
        //     {{#  }); }}
        //   </select>
        // </script>
        // EOF;
        //                     $templet = '#' . $templet;
        //                     $item['templet'] = $templet;
        //                 }elseif ('switch' == $item['type']) {
        //
        //                     $templet = 'k'.uniqid();
        //                     $op = json_encode($item['option']);
        //                     $this->columnBuilder()->templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //   <!-- 这里的 checked 的状态值判断仅作为演示 -->
        //   <input type="checkbox" data-name="$index" name="$index" value="{{= d.$index }}" title="ON|OFF"  lay-skin="switch" lay-filter="demo-templet-status" {{= d.$index == 1 ? "checked" : "" }}>
        // </script>
        // EOF;
        //                     $templet = '#' . $templet;
        //                     $item['templet'] = $templet;
        //
        //                 }
        //                 $this->_quick_update[$index] = ['option' => $item, 'qucik_edit' => $update];
        //             }
        //         }
        //         return $this;
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
        $this->columnBuilder()->key(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (false === strpos($field, '{$') && strpos($field, '.')) {
        //             $templet = 'k'.uniqid();
        //             if (preg_match('/(.*)\[:(.*)\]/', $field, $matches)) {
        //                 $field = $matches[1];
        //                 $foreignKey = $matches[2];
        //             } else {
        //                 $foreignKey = '';
        //             }
        //             $with = explode('.', $field);
        //
        //             if (!isset($this->_with[$with[0]])) {
        //                 $this->_with[$with[0]] = [$with[1]];
        //             } else {
        //                 $this->_with[$with[0]][] = $with[1];
        //             }
        //             if ($foreignKey) {
        //                 $this->_field[] = $foreignKey;
        //             } else {
        //                 $this->_field[] = $with[0] . '_id';
        //             }
        //             $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //  {{#  if(d.{$with[0]}){ }}
        //    {{d.{$field}}}
        //     {{#  }else{ }}
        //     -
        //   {{#  } }}
        // </script>
        // EOF;
        //             $templet = '#' . $templet;
        //         }
        //
        //         if (preg_match('/(.*)\[(.*)\]/', $title, $matches)) {
        //             $title = $matches[2];
        //             $hide = true;
        //         } else {
        //             $hide = false;
        //         }
        //         if (!($templet instanceof \Closure) && false === strpos($field, '.')) {
        //             $this->_field = array_merge($this->_field, is_array($field) ? $field : explode(',', $field));
        //         } elseif (false !== strpos($field, '{$data')) {
        //             $this->_field[] = substr(explode('|', $field)[0], 7);;
        //         }
        //         if (!$sort) {
        //             $sort = false;
        //             $filter = false;
        //         } elseif (true === $sort || 'desc' == $sort || 'asc' == $sort) {
        //             $sort = true;
        //             $filter = false;
        //         } else {
        //             if (false !== strpos($sort, 'sort') && false !== strpos($sort, 'filter')) {
        //                 $sort = true;
        //                 $filter = true;
        //             } elseif (false === strpos($sort, 'sort') && false !== strpos($sort, 'filter')) {
        //                 $sort = false;
        //                 $filter = true;
        //             } else {
        //                 $sort = true;
        //                 $filter = false;
        //             }
        //         }
        //         if ($field == 'id') {
        //             $fixed = 'left';
        //         } else {
        //             $fixed = '';
        //         }
        //         if ($type == 'children') {
        //             $key = [
        //                 'type' => $type,
        //                 'field' => $field,
        //                 'title' => $title,
        //                 'collapse' => 1,
        //                 'childWidth' => 'full',
        //                 'style' => $style,
        //                 'templet' => $templet,
        //             ];
        //         } else {
        //
        //             $key = [
        //                 'field' => $field,
        //                 'type' => $type,
        //                 'title' => $title,
        //                 'sort' => $sort,
        //                 'hide' => $hide,
        //                 'filter' => $filter,
        //                 //                'tips' => $tips,
        //                 'style' => $style,
        //                 'fixed' => $fixed,
        //                 'templet' => $templet,
        //                 'map' => $map,
        // //                'totalRow' => '{{= parseInt(d.TOTAL_NUMS) }} 次',//totalRow: '合计：'
        //                 //                'even' => true,
        //             ];
        //             if (!$templet)
        //             {
        //                 unset($key['templet']);
        //             }
        //         }
        //         $reKey = [];
        //         !empty($width) && $key['width'] = $width;
        //         $this->_keyList[] = $key;
        //         return $this;
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
        $this->columnBuilder()->keyBool(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->keySwitch($field, $title, '是|否', $sort, $width, $style);
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
        $this->columnBuilder()->keySwitch(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (is_array($map)) {
        //             $map_text = implode('|', $map);
        //         } else {
        //             $map_text = $map;
        //             $map = explode('|', $map);
        //         }
        //         $map_result[0] = $map[1];
        //         $map_result[1] = $map[0];
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //     <input type="checkbox" disabled  lay-skin="switch" lay-text="$map_text" {{ d.{$field} == 1 ? 'checked' : '' }}>
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet, $map_result);
    }


    /**
     * 第一序列显示
     * @param $type
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function keyLeftLeader($type)
    {
        $this->columnBuilder()->keyLeftLeader(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (false == $type) {
        //             $this->_left_leader = [];
        //         } else {
        //             $this->_left_leader = ['type' => $type, 'fixed' => 'left'];
        //         }
        //         return $this;
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
        $this->columnBuilder()->keyText(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, $title, $sort, $width, 'normal', $style);
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
        $this->columnBuilder()->keyEditerText(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet = 'k'.uniqid();
        //
        //
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //
        //     <div>{{- d.$field }}</div>
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style);
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
        $this->columnBuilder()->keyAuthor(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, text($title), $sort, $width, 'normal', $style, '');
    }


    /**
     * 隐藏显示
     * @param string|array $field 键名
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyHidden($field)
    {
        $this->columnBuilder()->keyHidden(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, '', false, '', 'hidden', '', '');
    }

    /**
     * 追加字段
     * @param string|array $field 键名
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function append($field)
    {
        $this->columnBuilder()->append(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, '', false, '', 'hidden', '', '');
    }

    /**
     * 显示金额
     * @param string $field 键名
     * @param string $title 标题
     * @param bool $sort 排序方式，默认是不参与排序
     * @param string|null $width
     * @param string|null $style
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDecimal($field, $title, $sort = false, $width = '', $style = '')
    {
        $templet = 'k'.uniqid();

        if ($style == '' && 'zh-cn' == $langSet = $this->app->lang->defaultLangSet()) {
            $style = 'rmb';
        } elseif ($style == '') {
            $style = 'dollar';
        }

        // 2026-09-06 拆分重构：改为写入ColumnBuilder的templets（原写入行注释保留）
        // $this->_templets[] = <<<EOF
        $this->columnBuilder()->templets[] = <<<EOF
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
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyDollar($field, $title, $sort = false, $width = '', $style = '')
    {
        $this->columnBuilder()->keyDollar(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //    <i class="layui-icon layui-icon-dollar"></i> {{d.$field}}
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);
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
        $this->columnBuilder()->keyDiamond(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //    <i class="layui-icon layui-icon-diamond"></i> {{d.$field}}
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);
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
        $this->columnBuilder()->keyRmb(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //    <i class="layui-icon layui-icon-rmb"></i> {{d.$field}}
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet);
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
        $this->columnBuilder()->keyTemplate(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        //    $templet
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet_name);
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
        $this->columnBuilder()->keyCount(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $this->_count[] = $field;
        //         return $this->key($field . '_count', $title, $sort, $width, 'normal', $style);
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
        $this->columnBuilder()->keyField(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, $templet);
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
        $this->columnBuilder()->keyColor(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, text($title), $sort, $width, 'normal');
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
        $this->columnBuilder()->keyCreateTime(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->keyTime('create_time', text($title), 'yyyy-MM-dd HH:mm:ss', $sort, $style);
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
        $this->columnBuilder()->keyUpdateTime(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->keyTime('update_time', text($title), 'yyyy-MM-dd HH:mm:ss', $sort, $style);
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
        $this->columnBuilder()->keyTime(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        //    {{#
        //    if(d.$field && '0000-00-00 00:00:00' != d.$field){
        //   var date = new Date(d.$field);
        //   var time = date.Format("$format");
        //   }else{
        //   var time = '-';
        //   }
        // }}
        // <span title="{{d.{$field}}}">{{time}}</span>
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, strlen($format) * 8 + 10, 'normal', $style, '#' . $templet_name);
        //         //            $opt['format'] = $format;
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
        $this->columnBuilder()->keyEmail(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, text($title), $sort, $width, 'normal');
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
        $this->columnBuilder()->keyHtml(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->key($field, $title, 'html', '', 'normal');
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
        $this->columnBuilder()->keyMap(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (empty($width)) {
        //             $max = strlen($title);
        //             foreach ($map as $v) {
        //                 if ($max < strlen($v)) {
        //                     $max = strlen($v);
        //                 }
        //             }
        //             $width = $max * 5 + 40;
        //         }
        //         $templet_name = 'k'.uniqid();
        //         $map_en = json_encode($map, JSON_UNESCAPED_UNICODE);
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        //    {{#  var map = $map_en }}
        //    {{map[d.{$field}]}}
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, $width, 'normal', $style, '#' . $templet_name, $map);
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
        $this->columnBuilder()->keyId(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->keyText($this->_default_pk, $title, $sort, $width, $style);
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
        $this->columnBuilder()->keyImage(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        // <div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title=""
        //  layer-src="{{ d.{$field}?d.{$field}:'{$common}' }}" src="{{ d.{$field}?d.{$field}:'{$common}' }}"></div>
        // </script>
        // EOF;
        //         return $this->key($field, $title, false, 50 + 35, $style, 'normal', '#' . $templet_name);
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
        $this->columnBuilder()->keyImages(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        // <div class="layer-photos" id="layer-photos-$field-{{d.id}}"  style="display: inline-block">
        //   {{#  layui.each(d.{$field}, function(index, item){ }}
        // <img style="width: 50px;cursor:pointer" title="" layer-src="{{item}}" src="{{item}}">
        //  {{#  }); }}
        // </div>
        // </script>
        // EOF;
        //         return $this->key($field, $title, false, '300', $style, 'normal', '#' . $templet_name);
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
        $this->columnBuilder()->keyImageModel(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (strpos($field, '|')) {
        //             $temp = explode('|', $field);
        //             $field = $temp[1];
        //             $this->_with[$temp[0]] = ['id', 'url'];
        //         } elseif (strpos($field, '_id')) {
        //             $temp = explode('_', $field);
        //             $this->_with[$temp[0]] = ['id', 'url'];
        //         } else {
        //             $temp = $field;
        //         }
        //         $templet_name = 'k'.uniqid();
        //         $common = config('view.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
        //         if (is_array($temp)) {
        //             $with_field = $temp[0];
        //         } else {
        //             $with_field = $temp;
        //         }
        // //			$this->_templets[] = <<<EOF
        // //<script type="text/html" id="$templet_name">
        // //<div class="layer-photos"  style="display: inline-block" id="layer-photos-$with_field-{{d.id}}"><img style="display: inline-block; width: 50px;cursor:pointer" title=""
        // // layer-src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}" src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}"></div>
        // //</script>
        // //EOF;
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        // <div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title=""
        //  layer-src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}" src="{{ d.{$with_field}?d.{$with_field}.url:'{$common}' }}"></div>
        // </script>
        // EOF;
        //
        // //			$this->_templets[] = <<<EOF
        // //<script type="text/html" id="$templet_name">
        // //<div class="layer-photos" id="layer-photos-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title=""
        // // layer-src="{{ d.{$field}?d.{$field}:'{$common}' }}" src="{{ d.{$field}?d.{$field}:'{$common}' }}"></div>
        // //</script>
        // //EOF;
        //         //            $this->_templets[] = <<<EOF
        //         //<script type="text/html" id="$templet_name">
        //         // <img style="display: inline-block; width: 25px; height: 25px;" src= {{ d.{$temp}?d.{$field}:'{$common}/images/default_image.gif' }}>
        //         //</script>
        //         //EOF;
        //         return $this->key($field, $title, false, 50 + mb_strlen($title, 'utf-8') * 14, $style, 'normal', '#' . $templet_name);
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
        $this->columnBuilder()->keyImagesModel(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        // <div class="layer-photos"  style="display: inline-block" id="layer-photos-$field-{{d.id}}">
        //  {{#  layui.each(d.{$field}, function(index, item){ }}
        // <img style="display: inline-block; width: 50px;cursor:pointer" title="点击查看2大图"
        //  layer-src="{{ item.url }}" src="{{ item.url }}">
        //    {{#  }); }}
        //    </div>
        // </script>
        // EOF;
        //         return $this->key($field, $title, false, 300, $style, 'normal', '#' . $templet_name);
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
        $this->columnBuilder()->keyUser(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (strpos($field, '|')) {
        //             $temp = explode('|', $field);
        //             $with_field = $temp[0];
        //             $field = $temp[1];
        //         } else {
        //             $temp = explode('_', $field);
        //             unset($temp[count($temp) - 1]);
        //             $with_field = implode('_', $temp);
        //         }
        //         if (isset($this->_with[$with_field]))
        //         {
        //             $this->_with[$with_field] = array_merge($this->_with[$with_field], ['id', 'avatar', 'nickname']);
        //         }else{
        //             $this->_with[$with_field] = ['id', 'avatar', 'nickname'];
        //         }
        //         $templet_name = 'k'.uniqid();
        //         $common = config('view.tpl_replace_string.__COMMON__') . '/images/avatar_default.png';
        //         $url = url($url) . '?id={{d.' . $with_field . '.id}}';
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        //   {{#  if(d.{$with_field}){ }}
        //     <a style="cursor:pointer " lay-href="$url" >
        //   <img style="display: inline-block; width: 25px; height: 25px;border-radius: 50%;" src= {{ d.{$with_field}.avatar?d.{$with_field}.avatar:'{$common}' }}>  {{ d.{$with_field}?d.{$with_field}.nickname:'无用户' }}
        //   </a>
        //   {{#  }else{ }}
        //        <div style="cursor:pointer ">
        // -
        //   </div>
        //   {{#  } }}
        // </script>
        // EOF;
        //         return $this->key($field, $title, false, $width, 'normal', $style, '#' . $templet_name);
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
        $this->columnBuilder()->keyIp(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        //    <i class="layui-icon layui-icon-link"></i> <a href="https://www.ip.cn/?ip={{d.$field}}" target="_blank"> {{d.$field}}</a>
        // </script>
        // EOF;
        //         return $this->key($field, $title, $sort, 160, 'normal', $type, '#' . $templet_name);
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
        $this->columnBuilder()->keyTitle(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         return $this->keyText('title', $title, $sort, $width);
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
        $this->columnBuilder()->keyClosure(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $pinyin = new ChinesePinyin();
        //         return $this->key($pinyin->transformWithoutTone($title, '_'), text($title), false, $width, $closure, $style);
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
        $this->columnBuilder()->keyTemplateChild(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet_name">
        //    $templet
        // </script>
        // EOF;
        //         $this->_keyList[] = [
        //             'field' => 'skus',
        //             'title' => $title,
        //             'type' => 'child',
        //             'width' => $width,
        //             'style' => $style,
        //             'collapse' => 1,
        //             'children' => '#' . $templet_name,
        //             'childWidth' => 'full',
        //         ];
        //         return $this;
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
        $this->columnBuilder()->keyLink(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         // 修整添加多个空字段时显示不正常的
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        // <script type="text/html" id="$templet">
        //    <i class="layui-icon layui-icon-link"></i> <a href="$url" target="$target"> {{d.$field}}</a>
        // </script>
        // EOF;
        //         return $this->key($field, $title, false, $width, 'normal', $style, '#' . $templet);
    }

    /**
     * 可点击复制的文本列（2026-09-06 新增，实现在 ColumnBuilder::keyCopy）
     * 单元格内容点击即复制到剪贴板，点击处理器见 tpl/table.html 的 .builder-copy-text 委托
     * @param string $field 字段名
     * @param string $title 列标题
     * @param string $width 列宽
     * @param string $style 单元格样式
     * @return $this
     */
    public function keyCopy($field, $title, $width = '', $style = '')
    {
        $this->columnBuilder()->keyCopy(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
    }

    /**
     * 对话框
     * @param string $field
     * @param string $title
     * @param $url string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
     * @param array $arr
     * @param int|null $width
     * @return $this
     */
    public function keyDialog($field, $title, $url, $arr = [], $width = '')
    {
        $dialog_width = isset($arr['width']) ? $arr['width'] : $this->dialog_width_default;
        $dialog_height = isset($arr['height']) ? $arr['height'] : $this->dialog_height_default;
        // 修整添加多个空字段时显示不正常的
        $templet = 'k'.uniqid();
        // 2026-09-06 拆分重构：改为写入ColumnBuilder的templets（原写入行注释保留）
        // $this->_templets[] = <<<EOF
        $this->columnBuilder()->templets[] = <<<EOF
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
     * @return $this
     */
    public function keyView($field, $title, $url, $arr = [], $width = '')
    {
        $dialog_width = isset($arr['width']) ? $arr['width'] : $this->dialog_width_default;
        $dialog_height = isset($arr['height']) ? $arr['height'] : $this->dialog_height_default;
        // 修整添加多个空字段时显示不正常的
        $templet = 'k'.uniqid();
        // 2026-09-06 拆分重构：改为写入ColumnBuilder的templets（原写入行注释保留）
        // $this->_templets[] = <<<EOF
        $this->columnBuilder()->templets[] = <<<EOF
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
     * @return $this
     */
    public function keyTab($field, $title, $url, $width = '')
    {
        $this->columnBuilder()->keyTab(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         if (false !== strpos($url, '{$')) {
        //             // 补充
        //             $url = str_replace('{$', '{{d.', $url);
        //             $url = str_replace('}', '}}', $url);
        //         }
        //         // 修整添加多个空字段时显示不正常的
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        //  <script type="text/html" id="$templet">
        //           <a style="cursor:pointer " lay-href="$url" ><i class="layui-icon layui-icon-layouts"></i> {{d.$field}}</a>
        //         </script>
        // EOF;
        //         return $this->key($field, $title, false, $width, 'tab', '', '#' . $templet);
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
        $this->columnBuilder()->keyProgress(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         // 修整添加多个空字段时显示不正常的
        //         $templet = 'k'.uniqid();
        //         $this->_templets[] = <<<EOF
        //   <script type="text/html" id="$templet">
        //         <div class="layui-progress layuiadmin-order-progress" lay-filter="progress-{{ d.id }}" lay-showPercent="true">
        //           <div class="layui-progress-bar layui-bg-blue" style="width: {{ d.$field }}%;"></div>
        //         </div>
        //       </script>
        //
        // EOF;
        //         return $this->key($field, $title, false, $width, 'normal', '', '#' . $templet);
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
        $this->columnBuilder()->keyStatus(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：key*系列迁至 Table/ColumnBuilder，原实现注释保留
        //
        //         $templet_name = 'k'.uniqid();
        //         $map = !is_null($map) ? $map : [
        //             -2 => '已删除',
        //             -1 => '禁用',
        //             1 => '启用',
        //             0 => '未审核',
        //             2 => '推荐',
        //         ];
        //         return $this->keyMap('status', '状态', $map, $sort, '', $style);
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
                $url = ($this->module?($this->module.'/'):'') . lcfirst(str_replace('.','/',$this->request->controller()) ) . '/' . $url;
            } else {
                // 补充
                $url =  ($this->module?($this->module.'/'):'')  . str_replace('.','/',$this->request->controller()) . '/' . $url;
            }
        }
//        dump($this->request->controller());
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
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionDisable($title = '不可操作', $status = [])
    {
        $this->buttonBuilder()->actionDisable(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction('', $title, empty($status) ? [0, 1, 2] : $status, 'no', '', 'layui-btn-orange', 'stop');
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
        $this->buttonBuilder()->actionView(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'tab', '', 'layui-btn-green', 'search');
        //
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
        $this->buttonBuilder()->actionManager(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'form', '', 'layui-btn-green', 'auz');
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
        $this->buttonBuilder()->actionLink(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'tab', '', 'layui-bg-green', 'link');
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
        $this->buttonBuilder()->actionAjax(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [-1, 0, 1, 2] : $status, 'ajax', $message, $class, $icon);
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
        $this->buttonBuilder()->actionDelete(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [-1, 0, 1, 2] : $status, 'ajax', $message, 'layui-btn-danger', 'delete');
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
        $this->buttonBuilder()->actionUpdate(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, $dialog == false ? 'tab' : $dialog, '', 'layui-bg-green', 'edit');
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
        $this->buttonBuilder()->actionRemove(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [-2] : $status, 'ajax', $message, 'btn-red', 'trash-o');
    }

    /**
     * 禁用
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionForbid($url = 'forbid?id={$id}', $title = '禁用', $status = [], $message = '')
    {
        $this->buttonBuilder()->actionForbid(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [1, 2] : $status, 'ajax', $message, 'layui-btn-danger', 'close-fill');
    }

    /**
     * 通过审核
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionToCheck($url = 'check?id={$id}', $title = '通过审核', $status = [], $message = '')
    {
        $this->buttonBuilder()->actionToCheck(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [0] : $status, 'ajax', $message, 'layui-bg-green', 'ok');
    }

    /**
     * 还原
     * @param string $url
     * @param string $title
     * @param array $status
     * @param string $message
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionRestore($url = 'restore?id={$id}', $title = '启用', $status = [], $message = '')
    {
        $this->buttonBuilder()->actionRestore(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：button/action系列迁至 table/ButtonBuilder，原实现注释保留
        //
        //         return $this->keyDoAction($url, $title, empty($status) ? [-1, -2] : $status, 'ajax', $message, 'btn-red', 'ok-circle');
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
        $this->queryResolver()->data(...func_get_args());
        // 2026-09-06 拆分重构：保持原链式语义，返回 Table 自身
        return $this;
        // 2026-09-06 拆分重构：查询配置迁至 table/QueryResolver，原实现注释保留
        //
        //         $this->_data = $data;
        //         $this->_pagination = $pagination;
        //         return $this;
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
                foreach ($this->queryResolver()->getQuickUpdate() as $__field => $item) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                // foreach ($this->_quick_update as $__field => $item) {
                    try {
                        if ($update['__field'] == $__field) {
                            $qucikEdit = $item['qucik_edit'];
                            if ($qucikEdit instanceof \Closure) {
                                // 闭包
                                $qucikEdit($update,$update['__field'],$update['__value'], $this->queryResolver()->getWhere(), $searchWhere); // 2026-09-06 拆分重构：改为从QueryResolver读取
                            } else {
//                                $this->_model::where('id', $update['id'])->where($this->_where)->update([$update['__field'] => $update['__value']]);
                                $__qrModel = $this->queryResolver()->getModel(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                                $whereModel = $this->_applyWhere($__qrModel::where([]));
                                // $whereModel = $this->_applyWhere($this->_model::where([]));
                                $qucikEditData = $whereModel->where('id', $update['id'])->find();
                                if ($qucikEditData) {
                                    $qucikEditData[$update['__field']] = $update['__value'];
                                    $qucikEditData->save();
                                }
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
                        $model = $this->queryResolver()->getModel(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                        // $model = $this->_model;
                        if ($model instanceof \Closure) {
                            // 闭包
                            $result = [];
                        } elseif (empty($this->queryResolver()->getData())) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                        // } elseif (empty($this->_data)) {
                            if (is_string($model)) {
                                $whereModel = $this->_applyWhere($model::where($searchWhere));
                            } else {
                                $whereModel = $this->_applyWhere($model->where($searchWhere));
                            }
                            if (count($this->columnBuilder()->getCount())) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                            //                             if (count($this->_count)) {
                                $result = [];
                            } else {
                                foreach ($this->columnBuilder()->getKeyList() as $index => $item) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                                //                                 foreach ($this->_keyList as $index => $item) {
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
                        $_qrField = $this->_getField($this->queryResolver()->getField()); // 2026-09-06 拆分重构：改为从QueryResolver读取（局部变量承接原 _field 赋值）
                        // $this->_field = $this->_getField($this->_field);
                        $list_rows = 1000000;
                        $page = 1;
                        $result = [];
                        $model = $this->queryResolver()->getModel(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                        // $model = $this->_model;
                        if ($model instanceof \Closure) {
                            // 闭包
                            $result = $model($searchWhere, $_qrField, $searchOrder, $page, $list_rows);
                        } elseif (empty($this->queryResolver()->getData())) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                        // } elseif (empty($this->_data)) {
                            $whereModel = $this->_applyWhere($model::where($searchWhere));
                            $result['code'] = 0;
                            if (count($this->columnBuilder()->getCount())) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                            //                             if (count($this->_count)) {
                                $lists = $whereModel->withCount($this->columnBuilder()->getCount())->order($searchOrder)->limit($list_rows * ($page - 1), $list_rows)->select(); // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                                //                                 $lists = $whereModel->withCount($this->_count)->order($searchOrder)->limit($list_rows * ($page - 1), $list_rows)->select();
                            } else {
                                $lists = $whereModel->order($searchOrder)->limit($list_rows * ($page - 1), $list_rows)->select();
                            }
                            $result['count'] = $whereModel->count();
                        } else {
                            if ($this->queryResolver()->getData() instanceof \Closure) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                            // if ($this->_data instanceof \Closure) {
                                $data = $this->queryResolver()->getData(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                                // $data = $this->_data;
                                // 闭包
                                $lists = $data($searchWhere, $_qrField, $searchOrder, $page, $list_rows);
                            } else {
                                $lists = $this->queryResolver()->getData(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                                // $lists = $this->_data;
                            }
                            if (isset($lists['code'])) {
                                $result = $lists;
                                $lists = $lists['data'];
                            } else {
                                $result['code'] = 0;
                                $result['count'] = count($this->queryResolver()->getData()); // 2026-09-06 拆分重构：改为从QueryResolver读取
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

                    foreach ($this->queryResolver()->getQuickUpdate() as $index => $item) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                    // foreach ($this->_quick_update as $index => $item) {

                        dump($item);
                    }
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
                    $this->_formatKeyList();
                    $this->_setMenu();
                    // 显示页面
                    $this->assign('templets', $this->columnBuilder()->getTemplets()); // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                    // $this->assign('templets', $this->_templets);
                    $this->assign('do_action', $this->_do_action);
                    $this->assign('toolbar', $this->_toolbar);
                    $this->assign('namespace', $this->_namespace?:'');
                    $this->assign('suggest', $this->_suggest);
                    $this->assign('statistics', $this->_statistics);
                    $this->assign('warning', $this->_warning);


                    $keyListRef = &$this->columnBuilder()->getKeyListRef();
                    $this->assign('keyList', array_values($keyListRef)); // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                    // $this->assign('keyList', array_values($this->_keyList));
                    $this->assign('buttonList', $this->buttonBuilder()->getButtonList()); // 2026-09-06 拆分重构：改为从ButtonBuilder读取
                    // $this->assign('buttonList', $this->_buttonList);
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
                    if (count($this->searchBuilder()->getSearch()) > 0) { // 2026-09-06 拆分重构：改为从SearchBuilder读取
                        // if (count($this->_search) > 0) {
                        $this->assign('searches', $this->searchBuilder()->getSearch()); // 2026-09-06 拆分重构：改为从SearchBuilder读取
                        // $this->assign('searches', $this->_search);
                        if (count($this->_search_more) > 0) {
                            $this->assign('search_more', $this->_search_more);
                        }
                        foreach ($this->searchBuilder()->getSearch() as $index => $search_item) { // 2026-09-06 拆分重构：改为从SearchBuilder读取
                            // foreach ($this->_search as $index => $search_item) {
                            $search_value[$search_item['field']] = $search_item['value'];
                        }
                    }
                    $this->assign('search_value', $search_value);
                    if (empty($this->searchBuilder()->getSearchPostUrl())) { // 2026-09-06 拆分重构：改为从SearchBuilder读取
                        $this->searchBuilder()->setSearchPostUrlValue($this->request->url()); // 2026-09-06 拆分重构：改为写入SearchBuilder
                        // if (empty($this->_searchPostUrl)) {
                        //     $this->_searchPostUrl = $this->request->url();
                        // }
                    }
                    $searchPostUrl = $this->searchBuilder()->getSearchPostUrl(); // 2026-09-06 拆分重构：改为从SearchBuilder读取
                    if (strpos($searchPostUrl, '/Admin')) {
                        $searchPostUrl = str_replace('/Admin', '/admin', $searchPostUrl);
                    }
                    $this->assign('searchPostUrl', $searchPostUrl); // 2026-09-06 拆分重构：改为从SearchBuilder读取
                    // if (strpos($this->_searchPostUrl, '/Admin')) {
                    //     $this->_searchPostUrl = str_replace('/Admin', '/admin', $this->_searchPostUrl);
                    // }
                    // $this->assign('searchPostUrl', $this->_searchPostUrl);
                    /* 复选框 */
                    $this->assign('group', $this->buttonBuilder()->getGroup()); // 2026-09-06 拆分重构：改为从ButtonBuilder读取
                    // $this->assign('group', $this->_group);
                    /* 加入筛选select */
                    $this->assign('selects', $this->_select);
                    $this->assign('selectPostUrl', $this->searchBuilder()->getSelectPostUrl()); // 2026-09-06 拆分重构：改为从SearchBuilder读取
                    // $this->assign('selectPostUrl', $this->_selectPostUrl);
                    /* 加入隐藏表单 */
                    $this->assign('hidden', $this->_hidden);
                    $this->assign('page', $this->queryResolver()->isPaginated() ? 1 : 0); // 2026-09-06 拆分重构：改为从QueryResolver读取
                    // $this->assign('page', $this->_pagination ? 1 : 0);
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

    /**
     * 格式化列清单
     * 合并快捷编辑配置、移除隐藏列、附加合计行模板，并按操作按钮数量计算操作列宽度后追加操作列
     * @return void
     */
    protected function _formatKeyList()
    {
        $keyListRef = &$this->columnBuilder()->getKeyListRef(); // 2026-09-06 拆分重构：改为从ColumnBuilder读取
        foreach ($keyListRef as $index => $item) {
            foreach ($this->queryResolver()->getQuickUpdate() as $index2 => $item2) { // 2026-09-06 拆分重构：改为从QueryResolver读取
            // foreach ($this->_quick_update as $index2 => $item2) {
                if ($index2 == $keyListRef[$index]['field']) {
                    if ($item2['option']['type']=='select')
                    {
                        $keyListRef[$index]['templet'] =$item2['option']['templet'];
                    }elseif ($item2['option']['type']=='switch')
                    {
                        $keyListRef[$index]['templet'] =$item2['option']['templet'];
                    }else{
                        $keyListRef[$index]['edit'] = 'text';
                    }
                }
            }
            if (isset($item['type']) && $item['type'] == 'hidden') {
                unset($keyListRef[$index]);
            } elseif (isset($item['type']) && $item['type'] == 'child') //'type'=>'child',
            {
                unset($keyListRef[$index]['field']);
            }
            $totalRowList = $this->queryResolver()->getTotalRow(); // 2026-09-06 拆分重构：改为从QueryResolver读取
            // foreach ($this->_total_row as $index2 => $item2) {
            foreach ($totalRowList as $index2 => $item2) {

                if ($keyListRef[$index]['field'] == $totalRowList[$index2]['field']) {
                    $keyListRef[$index]['totalRow'] = $totalRowList[$index2]['templet'];
                }
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
            $keyListRef[] = [
                'fixed' => 'right',
                'title' => '操作',
                'align' => 'center',
                'toolbar' => '#' . $this->_namespace . '-table-action',
                'width' => $this->_action_width
            ];
        }
        !empty($this->columnBuilder()->getLeftLeader()) && array_unshift($keyListRef, $this->columnBuilder()->getLeftLeader());
    }

    /**
     * 根据当前请求查询并设置菜单标题
     * 按请求参数匹配 menu 表记录，填充页面的一级/父级菜单标题
     * @return void
     */
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
        $this->assign('menu_title', $this->_title?:'');
    }

    /**
     * 组装表格 AJAX 请求返回的数据
     * 根据模型（或闭包、数据数组）查询列表，应用搜索条件、排序、分页与隐藏字段，转换后返回 code/count/data 结构
     * @return array
     */
    protected function _formatAjaxData()
    {
        try {
            $_qrField = $this->_getField($this->queryResolver()->getField()); // 2026-09-06 拆分重构：改为从QueryResolver读取（局部变量承接原 _field 赋值）
            // $this->_field = $this->_getField($this->_field);
            $list_rows = $this->request->has('limit', 'param') ? $this->request->param('limit') : 15;
            $page = $this->request->has('page', 'param') ? $this->request->param('page') : 1;
            $result = [];
            $searchWhere = $this->_searchWhere();
            $searchOrder = $this->_searchOrder();
//                    dump($searchWhere);
//                    exit();
            $model = $this->queryResolver()->getModel(); // 2026-09-06 拆分重构：改为从QueryResolver读取
            // $model = $this->_model;
            if ($model instanceof \Closure) {
                // 闭包
                $result = $model($searchWhere, $_qrField, $searchOrder, $page, $list_rows);
            } elseif (!is_null($model)) {
                if (is_string($model)) {
                    $whereModel = $this->_applyWhere($model::where($searchWhere));
//							->field(implode($this->_field, ','))
                } else {
                    $whereModel = $this->_applyWhere($model->where($searchWhere));
//							->field(implode($this->_field, ','))
                }

                // 列表仅查询展示字段白名单，避免 SELECT * 把 longblob 等二进制大字段带出导致 JSON 编码失败（Malformed UTF-8）
                // 未显式 ->field() 的页面 _field 为空，行为不变（仍 SELECT *）
                if (!empty($_qrField)) { // 2026-09-06 拆分重构：改为读取QueryResolver解析结果（原 _field）
                    // if (!empty($this->_field)) {
                    //   $whereModel->field($this->_field);
                }
                if (!empty($this->queryResolver()->getHiddenField())) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                    // if (!empty($this->_hidden_field)) {
                    $whereModel->hidden($this->queryResolver()->getHiddenField()); // 2026-09-06 拆分重构：改为从QueryResolver读取
                    // $whereModel->hidden($this->_hidden_field);
                }

                $result['code'] = 0;
                $result['count'] = $whereModel->count();
                if (count($this->columnBuilder()->getCount())) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
                //                 if (count($this->_count)) {
                    $lists = $whereModel->withCount($this->columnBuilder()->getCount())
                        //                     $lists = $whereModel->withCount($this->_count)
                        ->order($searchOrder)
                        ->limit($list_rows * ($page - 1), $list_rows)->select();
                } else {
                    $lists = $whereModel
                        ->order($searchOrder)
                        ->limit($list_rows * ($page - 1), $list_rows)->select();
                }
            } else {
                if ($this->queryResolver()->getData() instanceof \Closure) { // 2026-09-06 拆分重构：改为从QueryResolver读取
                // if ($this->_data instanceof \Closure) {
                    $data = $this->queryResolver()->getData(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                    // $data = $this->_data;
                    // 闭包
                    $lists = $data($searchWhere, $_qrField, $searchOrder, $page, $list_rows);
                } else {
                    $lists = $this->queryResolver()->getData(); // 2026-09-06 拆分重构：改为从QueryResolver读取
                    // $lists = $this->_data;
                }

                if (isset($lists['code'])) {
                    $result = $lists;
                    $lists = $lists['data'];
                } else {
                    $result['code'] = 0;
                    $result['count'] = count($lists);
                }
            }
//
//                dump($lists);	dump($lists);
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


    /**
     * 过滤字段列表，仅保留模型数据表中真实存在的字段
     * @param array $field 待过滤的字段名数组
     * @return array 过滤后的字段数组
     */
    protected function _getField($field)
    {
        $field = array_merge($field, $this->columnBuilder()->getExtraFields()); // 2026-09-06 拆分重构：合并ColumnBuilder的extraFields
        $field = array_unique($field);
        $model = $this->queryResolver()->getModel(); // 2026-09-06 拆分重构：改为从QueryResolver读取
        // $model = $this->_model;
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

    /**
     * 获取当前请求的排序列表 SQL 片段
     * 优先取请求中的 order_field/order，其次取设置的默认排序，最终回退为 id DESC
     * @return string
     */
    protected function _searchOrder()
    {
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，转发并传入请求与默认排序
        return $this->searchBuilder()->searchOrder($this->request, $this->queryResolver()->getOrder()); // 2026-09-06 拆分重构：改为从QueryResolver读取
        // return $this->searchBuilder()->searchOrder($this->request, $this->_order);
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         if ($this->request->has('order_field')) {
        //             return $this->request->param('order_field') . ' ' . $this->request->param('order');
        //         } elseif (!$this->_order) {
        //             return 'id DESC';
        //         } else {
        //             return $this->_order;
        //         }
    }

    /**
     * 根据请求中的搜索参数组装查询条件
     * 遍历请求的 field/a 参数并匹配已设置的搜索项，生成模型 where 条件数组
     * @return array
     */
    protected function _searchWhere()
    {
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，此处解析数据表字段后转发
        $db_fields = $this->resolveSearchDbFields();
        $this->searchBuilder()->request = $this->request; // 2026-09-06 拆分重构：注入当前请求供 SearchBuilder 解析搜索参数
        return $this->searchBuilder()->searchWhere($db_fields);
        // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，原实现注释保留
        //
        //         $fields = $this->request->param('field/a');
        // //        dump($fields);
        // //        dump($this->_search);
        //         $model = $this->_model;
        //         if (!is_null($model)) {
        //             if (is_string($model)) {
        //                 $db_fields = $model::getTableFields();
        //             } else {
        //                 $db_fields = $model->getTableFields();
        //             }
        //         } else {
        //             $db_fields = array_merge($this->_field, $this->columnBuilder()->getExtraFields()); // 2026-09-06 拆分重构：合并extraFields后读取
        //             // $db_fields = $this->_field;
        //         }
        //         $where = [];
        //         $_search_field = [];
        //         if (is_array($fields)) {
        //             foreach ($this->_search as $search) {
        //                 if (in_array($search['field'], $db_fields) && isset($fields[$search['field']]) && $fields[$search['field']] != '') {
        //                     $_search_field[] = $search['field'];
        //                     if ('like' === $search['condition']) {
        //                         $where[] = [$search['field'], 'like', '%' . $fields[$search['field']] . '%'];
        //                     } elseif ('between' === $search['condition']) {
        //                         if ('datepicker' === $search['type']) {
        //                             $temp = explode(' - ', $fields[$search['field']]);
        //                             if ($temp[0] && $temp[1]) {
        //                                 $where[] = [$search['field'], 'between time', $temp];
        //                             }
        //                         }
        //                     } elseif ('search_user' == $search['condition']) {
        //                         $ids = Db::name('user')
        //                             ->where('status', '>', -2)
        //                             ->where('id|username|email|nickname', 'like', '%' . $fields[$search['field']] . '%')
        //                             ->column('id');
        //                         $where[] = [$search['field'], 'in', $ids];
        //                     } elseif ('in' == $search['condition']) {
        //                         $where[] = [$search['field'], 'in', $fields[$search['field']]];
        //                     } else {
        //                         $where[] = [$search['field'], '=', $fields[$search['field']]];
        //                     }
        //                 }
        //             }
        //         }
        //         $urlFields = $this->request->except(explode(',', 'v,page,limit,user,m,field,video,store'));
        //         if (is_array($urlFields)) {
        //             foreach ($urlFields as $field => $field_value) {
        //                 if (!in_array($field, $db_fields) || in_array($field, $_search_field)) {
        //                     continue;
        //                 }
        // //					$out = false;
        // //					foreach ($this->_search as $search) {
        // //						if ($search['field'] == $field) {
        // //							$out = true;
        // //							continue;
        // //						}
        // //					}
        // //					if ($out) {
        // //						continue;
        // //					}
        //                 $where[] = [$field, '=', $field_value];
        //             }
        //         }
        //         //
        //
        //         $filterSos = $this->request->param('filterSos/s')?json_decode(htmlspecialchars_decode($this->request->param('filterSos/s')), true):[];
        //
        //
        //         //筛选数据支持
        //         if (is_array($filterSos)) {
        //             foreach ($filterSos as $index => $filterSo) {
        //                 if ('in' == $filterSo['mode']) {
        //                     $where[] = $this->_getMode($filterSo);
        //                 } elseif ('group' == $filterSo['mode']) {
        //                     throw new Exception('暂不支持');
        // //						foreach ($filterSo['children'] as $child) {
        // //							$where[] = $this->_getMode($child);
        // //						}
        //                 } else {
        //                 }
        //             }
        //         }
        //         return $where;
    }

    // 2026-09-06 拆分重构：search*系列迁至 Table/SearchBuilder，_getMode 原实现注释保留
    //
    //     private function _getMode($filter)
    //     {
    //         if ('in' == $filter['mode']) {
    //             $data = [$filter['field'], 'in', $filter['values']];
    // //				$data = [$filter['field'], 'in', $this->_getFieldValue($filter['field'], $filter['values'])];
    //         } elseif ('condition' == $filter['mode']) {
    //             if ('eq' == $filter['type']) {
    //                 $data = [$filter['field'], '=', $this->_getFieldValue($filter['field'], $filter['value'])];
    //             }
    //         }
    //         return $data;
    //     }

    private function _getFieldValue($field, $value)
    {
        foreach ($this->columnBuilder()->getKeyList() as $index => $item) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
        //         foreach ($this->_keyList as $index => $item) {
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
        $conver_data = $data;
        isset($data['status']) && $conver_data['status'] = $data['status'];
        isset($data['id']) && $conver_data['id'] = $data['id'];

        foreach ($this->columnBuilder()->getKeyList() as $key) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
        //         foreach ($this->_keyList as $key) {
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
                        if ($data[$field[0]])
                        {
                            $conver_data[$field[0]][$field[1]] = $data[$field[0]][$field[1]];
                        }else{
                            $conver_data[$field[0]] = null;
                        }
                    } else {
                        $conver_data[$key['field']] = $data[$key['field']];
                    }
                }
            }
        }
        foreach ($this->columnBuilder()->getWith() as $key => $items) { // 2026-09-06 拆分重构：改为从ColumnBuilder读取
        //         foreach ($this->_with as $key => $items) {
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
            $conver_data['_row_style'] = $closure($data);
        }
        if ($this->_row_class instanceof \Closure) {
            $closure = $this->_row_class;
            $conver_data['_row_class'] = $closure($data);
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
