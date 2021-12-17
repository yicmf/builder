<?php
	// +----------------------------------------------------------------------
	// | builder
	// +----------------------------------------------------------------------
	// | Copyright (c) 2015-2022 http://www.yicmf.com, All rights reserved.
	// +----------------------------------------------------------------------
	// | Author: 微尘 <yicmf@qq.com>
	// +----------------------------------------------------------------------
	namespace yicmf\builder;

	use Overtrue\Pinyin\Pinyin;
	use think\exception\HttpException;
	use think\Model;
	use think\Db;
	use think\db\Where;
	use think\facade\Cache;
	use think\facade\Config;
	use think\facade\Hook;
	use think\Loader;
	use think\facade\Url;
	use think\Exception;
	use app\file\model\Picture as PictureModel;
	use app\admin\model\Menu as MenuModel;
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
		private $_toolbar = ['filter', 'print'];// ['filter', 'exports', 'print'];
		protected $_filter = [
			//['column','data','condition','editCondition','excel']
			'items' => ['data'],
			'bottom' => false,
			'clearFilter' => true
		];
		/**
		 * 操作表宽度
		 * @var int
		 */
		protected $_key_action_width;
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

		protected function initialize()
		{
			if ($this->request->param('callback', '')) {
				$this->_callback = $this->request->param('callback');
				$this->_callback_field = trim($this->request->param('field'));
			}
			// 复选框
			$this->_namespace = $this->request->module() . '_' . str_replace('.', '_', $this->request->controller())
				. '_' . $this->request->action() . '_'
				. md5(json_encode($this->request->except('v,user')));
			//                .implode('_',$this->request->except('v'));
		}

		/**
		 * 模型
		 * @param string $model
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function model($model, $pagination = true)
		{
			$this->_model = $model;
			$this->_pagination = $pagination;
			return $this;
		}

		/**
		 * 引入css
		 * @param string $model
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function css($css)
		{
			$this->_css = $css;
			return $this;
		}

		/**
		 * 引入js
		 * @param string $model
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function js($js)
		{
			$this->_js = $js;
			return $this;
		}

		/**
		 * 模型
		 * @param array $filter
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function filter($filter)
		{
			$this->_filter = array_merge($this->_filter, $filter);
			return $this;
		}


		/**
		 * 导出表格
		 * @param string $filename //支持后缀：xlsx/xls<br>
		 * @param array $head
		 * 'family' => 'Calibri', // 字体
		 * 'size' => 12,// 字号
		 * 'color' => '000000', // 字体颜色
		 * 'bgColor' => 'FFFFFF', // 背景颜色
		 * 'cellType' => 'String' // 单元格格式 `b` 布尔值, `n` 数字, `e` 错误, `s` 字符, `d` 日期
		 * @param array $font
		 * @param array $border
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 * @datetime: 2020/5/24 8:20
		 */
		public function excel($filename = '', $head = [
			'family' => 'Calibri',
			'size' => 12,
			'color' => '000000',
			'bgColor' => 'FFFFFF',
			'cellType' => 'String'
		],                    $font = [
			'family' => 'Calibri',
			'size' => 12,
			'color' => '000000',
			'bgColor' => 'FFFFFF',
			'cellType' => 'String'
		],                    $border = [
			'top' => '{ style: \'thin\', color: \'FF5722\' }',
			'bottom' => '{ style: \'thin\', color: \'FF5722\' }',
			'left' => '{ style: \'thin\', color: \'FF5722\' }',
			'right' => '{ style: \'thin\', color: \'FF5722\' }'
		])
		{
			$this->keyLeftLeader('checkbox');
			$this->_toolbar[] = ['title' => '导出表格', 'layEvent' => 'LAYTABLE_EXCEL', 'icon' => 'layui-icon-export'];
			$this->_excel = [
				'filename' => $filename,
				'head' => $head,
				'font' => $font,
				'border' => $border,
			];
			return $this;
		}

		/**
		 * 模型
		 * @param $where
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function where($where)
		{
			$this->_where = $where;
			return $this;
		}

		/**
		 * 模型
		 * @param $model
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function order($order)
		{
			$this->_order = $order;
			return $this;
		}

		/**
		 * 模型
		 * @param $model
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function field($field)
		{
			$this->_field = array_merge($this->_field, is_array($field) ? $field : [$field]);
			return $this;
		}

		/**
		 * 配置默认主键.
		 * @param $pk
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function setDefaultPk($pk)
		{
			$this->_default_pk = $pk;
			return $this;
		}

		/**
		 * 配置默认主键.
		 * @param $pk
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function setAutoRefresh($time = 5000)
		{
			$this->_auto_refresh = $time;
			return $this;
		}

		/**
		 * 配置默认status.
		 * @param $status
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:35
		 */
		public function setDefaultStatus($status)
		{
			$this->_default_status = $status;
		}

		/**
		 * 设置页面标题
		 * @param $title
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:34
		 */
		public function title($title)
		{
			$this->_title = $title;
			return $this;
		}

		/**
		 * 设置页面隐藏数据
		 * @param $field
		 * @param $value
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:34
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
		 * @param $suggest
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:34
		 */
		public function suggest($suggest)
		{
			$this->_suggest = $suggest;
			return $this;
		}

		/**
		 * 统计信息
		 * @param $suggest
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:34
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
		 * @param $warning
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:34
		 */
		public function warning($warning)
		{
			$this->_warning = $warning;
			return $this;
		}

		/**
		 * 设置回收站根据ids彻底删除的URL
		 * @param $url
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:34
		 */
		public function setClearUrl($url)
		{
			$this->_setClearUrl = $url;
			return $this;
		}

		/**
		 * 筛选下拉选择url
		 * @param $url
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:33
		 */
		public function setSelectPostUrl($url)
		{
			$this->_selectPostUrl = Url::build($url);
			return $this;
		}

		/**
		 * 设置搜索提交表单的URL 更新筛选搜索功能
		 * @param string $url 提交的getURL
		 * @param array $param GET参数
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:33
		 */
		public function setSearchPostUrl($url, $param = [])
		{
			$get = $this->request->get();
			$param = empty($param) ? $get : array_merge($param, $get);
			$this->_searchPostUrl = Url::build($url, $param);
			return $this;
		}

		/**
		 * 加入一个按钮
		 * @param $title
		 * @param $attr
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:33
		 */
		public function button($title, $attr)
		{
			if (isset($attr['url']) && strpos($attr['url'], '/Admin')) {
				$attr['url'] = str_replace('/Admin', '/admin', $attr['url']);
			}
			$this->_buttonList[] = [
				'title' => $title,
				'attr' => $attr,
			];
			return $this;
		}

		/**
		 * 加入新增按钮.
		 * @param        $url
		 * @param string $title
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
		 * 全屏操作
		 * @param        $url
		 * @param string $title
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
		 * @param        $url
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
		 * @param 操作名称 $title
		 * @param 参数数组 $attr
		 * @param string $toggle
		 * @return Table
		 */
		public function buttonDialog($title, $attr, $toggle = 'navtab')
		{
			if (false === strpos($attr['url'], '/')) {
				// 补充
				$attr['url'] = $this->request->module() . '/' . $this->request->controller() . '/' . $attr['url'];
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
		 * @param 操作名称 $title
		 * @param 参数数组 $attr
		 * @param string $toggle
		 * @return Table
		 */
		public function buttonAjax($url, $title, $toggle = 'doajax', $attr = [])
		{
			$attr['url'] = Url::build($url);
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
		 * @param unknown $url
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
		 * @param unknown $url
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
			return $this->buttonAjax($title, $attr, 'doajaxchecked', $attr);
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
		 * 无条件ajax请求
		 * @param string $url
		 * @param string $title
		 * @param array $attr
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/14 12:52
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
		 * @param null $url
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

		public function buttonSort($url, $title = '排序', $attr = [])
		{
			$attr['url'] = $url;
			return $this->button($title, $attr);
		}

		/**
		 * 复选框操作.
		 * @param 选选名字 $title
		 * @param 提示 $msg
		 * @param 操作url $url
		 * @return Table
		 * @author  微尘 <yicmf@qq.com>
		 * @version v1.0.1
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

		public function groupOutxls($url = '', $title = '导出<span style="color: green;">全部</span>', $msg = '确定要导出信息吗？')
		{
			$url = $url ? $url : $this->request->controller() . '/outxls';
			$toggle = 'doexport';
			return $this->groupAction($title, $url, $msg, $toggle);
		}

		public function groupOutxlsCheck($url = '', $title = '导出<span style="color: red;">选中</span>', $msg = '确定要导出选中项吗？')
		{
			$url = $url ? $url : $this->request->controller() . '/outxls_check';
			$toggle = 'doexportchecked';
			$idname = 'expids';
			$group = 'ids';
			return $this->groupAction($title, $url, $msg, $toggle, $idname, $group);
		}

		public function groupDel($url = '', $title = '删除选中', $msg = '确定要删除选中项吗？')
		{
			$url = $url ? $url : $this->request->controller() . '/del';
			if ($this->request->param('bjui_nav_id')) {
				$url .= '?bjui_nav_id=' . $this->request->param('bjui_nav_id');//,['nav_id'=>'id'.$menu['id']]
			}
			$toggle = 'doajaxchecked';
			$idname = 'id';
			$group = 'ids';
			return $this->groupAction($title, $url, $msg, $toggle, $idname, $group);
		}

		public function groupBr($class = 'divider')
		{
			return $this->groupAction(null, null, null, null, null, null, $class, 1);
		}

		/**
		 * 搜索text文本信息.
		 * @param string $title
		 * @param string $field
		 * @param string $type
		 * @param string $des
		 * @param array $attr
		 * @return $this
		 */
		public function searchText($field, $title, $des = '', $default = '', $attr = [])
		{
			$this->_search[] = [
				'title' => $title,
				'field' => $field,
				'type' => 'text',
				'condition' => '=',
				'des' => $des,
				'value' => $default,
				'attr' => $attr,
			];
			return $this;
		}

		/**
		 * 搜索text文本信息.
		 * @param string $title
		 * @param string $field
		 * @param string $type
		 * @param string $desc
		 * @param array $attr
		 * @return $this
		 */
		public function searchTextLike($field, $title, $desc = '支持模糊搜索', $default = '', $attr = [])
		{
			$this->_search[] = [
				'title' => $title,
				'field' => $field,
				'type' => 'text',
				'condition' => 'like',
				'value' => $default,
				'des' => $desc,
				'attr' => $attr,
			];
			return $this;
		}


		/**
		 * 日期选择器.
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
				'placeholder' => $placeholder,
				'width' => $width,
				'options' => $options,
			];
			return $this;

		}

		/**
		 * 日期时间选择器
		 * @param $field
		 * @param $title
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
		 * @param $field
		 * @param $title
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
		 * 选择搜索
		 * @param        $field
		 * @param        $title
		 * @param int $default
		 * @param array $options
		 * @param string $des
		 * @param array $attr
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/5/8 13:15
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
		 * @param int $default
		 * @param array $options
		 * @param string $des
		 * @param array $attr
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/5/8 13:15
		 */
		public function searchSelect($field, $title, $options = [], $des = '', $default = '', $attr = [])
		{
			$this->_search[] = [
				'title' => $title,
				'field' => $field,
				'default' => $default,
				'type' => 'select',
				'des' => $des,
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
		 * @param string $type 类型，默认文本
		 * @param string $des 描述
		 * @param        $attr     标签文本
		 * @return $this
		 */
		public function search($title = '搜索', $field = 'key', $type = 'text', $des = '', $default = '', $attr = [], $options = null)
		{
			$this->_search[] = [
				'title' => $title,
				'field' => $field,
				'value' => $default,
				'type' => $type,
				'condition' => '=',
				'des' => $des,
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

		/**
		 * 需要展示的键值
		 * @param       $field
		 * @param       $title
		 * @param       $type
		 * @param array $opt
		 * @param bool $sort
		 * @param null $width
		 * @param array $param
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/2/20 12:42
		 */
		public function key($field, $title, $sort = false, $width = '', $type = 'normal', $style = '', $templet = '', $map = [], $edit = '')
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
			$key = [
				'field' => $field,
				'type' => $type,
				'title' => $title,
				'sort' => $sort,
				'hide' => $hide,
				'filter' => $filter,
				//                'tips' => $tips,
				'edit' => $edit,
				'style' => $style,
				'fixed' => $fixed,
				'templet' => $templet,
				'map' => $map,
				//                'even' => true,
			];
			!empty($width) && $key['width'] = $width;
			$this->_keyList[] = $key;
			return $this;
		}

		public function keyBool($field, $title, $sort = false, $width = '', $style = '')
		{
			return $this->keySwitch($field, $title, '是|否', $sort, $width, $style);
		}

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
		 * @author 微尘 <yicmf@qq.com>
		 * @datetime: 2020/2/3 17:19
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
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:31
		 */
		public function keyText($field, $title, $sort = false, $width = '', $style = '')
		{
			return $this->key($field, text($title), $sort, $width, 'normal', $style, '');
		}


		/**
		 * 显示纯文本
		 * @param string $field 键名
		 * @param string $title 标题
		 * @param bool $sort 排序方式，默认是不参与排序
		 * @param null $width
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:31
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
		 * @datetime: 2019/3/28 13:31
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
		 * @datetime: 2019/3/28 13:31
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
		 * @param null $width
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:32
		 */
		public function keyDecimal($field, $title, $sort = false, $width = '', $style = '')
		{
			$templet = uniqid();

			if ($style == '' && 'zh-cn' == $this->request->langset()) {
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
		 * 显示金额
		 * @param string $field 键名
		 * @param string $title 标题
		 * @param bool $sort 排序方式，默认是不参与排序
		 * @param null $width
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:32
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

		public function keyCount($field, $title, $sort = true, $width = '', $style = '')
		{
			$this->_count[] = $field;
			return $this->key($field . '_count', $title, $sort, $width, 'normal', $style);
		}

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
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:32
		 */
		//        public function keyColor($field, $title, $sort = false, $width = '', $opt = [])
		//        {
		//            return $this->key($field, text($title), $sort, $width,'normal');
		//        }
		/**
		 * 创建时间
		 * @param string $title
		 * @param string $format
		 * @param bool $sort
		 * @param null $width
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/2/22 10:52
		 */
		public function keyCreateTime($title = '创建时间', $sort = false, $style = '')
		{
			return $this->keyTime('create_time', text($title), 'yyyy-MM-dd HH:mm:ss', $sort, $sort, $style);
		}

		/**
		 * 更新时间
		 * @param string $title
		 * @param string $format
		 * @param bool $sort
		 * @param null $width
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/2/22 10:52
		 */
		public function keyUpdateTime($title = '更新时间', $sort = false, $style = '')
		{
			return $this->keyTime('update_time', text($title), 'yyyy-MM-dd HH:mm:ss', $sort, $style);
		}

		/**
		 * 时间
		 * @param string $field 键名
		 * @param string $title 标题
		 * @param string $format
		 * @param bool $sort
		 * @param null $width
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/3/28 13:31
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
			return $this->key($field, $title, $sort, strlen($format) * 9 + 20, 'normal', $style, '#' . $templet_name);
			//            $opt['format'] = $format;
		}

		/**
		 * 邮件地址
		 * @param       $field
		 * @param       $title
		 * @param bool $sort
		 * @param null $width
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/2/22 12:57
		 */
		//        public function keyEmail($field, $title, $sort = false, $width = '', $opt = [])
		//        {
		//            return $this->key($field, text($title), $sort, $width,'normal');
		//        }
		/**
		 * 显示html
		 * @param $field
		 * @param $title
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/2/22 12:57
		 */
		//        public function keyHtml($field, $title)
		//        {
		//            return $this->key($field, $title, 'html','','normal');
		//        }
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

		public function keyId($field = 'id', $title = 'ID', $sort = false, $width = 80, $style = '')
		{
			return $this->keyText($field, $title, $sort, $width, $style);
		}


		public function keyMedia($field, $title, $sort = false, $width = '', $opt = [])
		{
			return $this->key($field, $title, $sort, $width, 'normal');
		}

		//        public function keyAvatar($field, $title, $sort = false, $width = '', $style = '')
		//        {
		//            return $this->key($field, $title,  $sort, $width,'normal');
		//        }
		public function keyImage($field, $title, $sort = false, $style = '')
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
			$common = config('template.tpl_replace_string.__COMMON__') . '/images/default_image.gif';
			if (is_array($temp)) {
				$with_field = $temp[0];
				$this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-{{d.id}}-$with_field-{{d.$with_field?d.$with_field.id:d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title="点击查看大图"
 layer-src="{{ d.{$temp[0]}?d.{$temp[0]}.url:'{$common}' }}" src="{{ d.{$temp[0]}?d.{$temp[0]}.url:'{$common}' }}"></div>
</script>
EOF;
			} else {
				$this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
<div class="layer-photos" id="layer-photos-{{d.id}}-$field-{{d.id}}"><img style="display: inline-block; width: 30px;cursor:pointer" title="点击查看大图"
 layer-src="{{ d.{$field}?d.{$field}:'{$common}' }}" src="{{ d.{$field}?d.{$field}:'{$common}' }}"></div>
</script>
EOF;
			}

			//            $this->_templets[] = <<<EOF
			//<script type="text/html" id="$templet_name">
			// <img style="display: inline-block; width: 25px; height: 25px;" src= {{ d.{$temp}?d.{$field}:'{$common}/images/default_image.gif' }}>
			//</script>
			//EOF;
			return $this->key($field, $title, $sort, 50 + 35, $style, 'normal', '#' . $templet_name);
		}

		public function keyUser($field, $title, $sort = false, $width = '', $style = '')
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
			$common = config('template.tpl_replace_string.__COMMON__') . '/images/avatar_default.png';
			$this->_templets[] = <<<EOF
<script type="text/html" id="$templet_name">
 <img style="display: inline-block; width: 25px; height: 25px;border-radius: 50%;" src= {{ d.{$with_field}?d.{$with_field}.avatar:'{$common}' }}>  {{ d.{$with_field}?d.{$with_field}.nickname:'无用户' }}
</script>
EOF;
			return $this->key($field, $title, $sort, 150, 'normal', $style, '#' . $templet_name);
		}

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

		public function keyTitle($field = 'title', $title = '标题', $sort = false, $width = '')
		{
			return $this->keyText($field, $title, $sort, $width);
		}

		/**
		 * 闭包函数
		 * @param string $title
		 * @param        $closure
		 * @param null $width
		 * @param array $opt
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/2/20 12:53
		 */
		public function keyClosure($title, $closure, $width = '', $style = '')
		{
			$pinyin = new Pinyin();
			return $this->key($pinyin->permalink($title, '_'), text($title), false, $width, $closure, $style);
		}

		/**
		 * @param $field
		 * @param $title
		 * @param $url Closure|string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
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
		 * @param $field
		 * @param $title
		 * @param $url Closure|string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
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
		 * @param $field
		 * @param $title
		 * @param $url Closure|string 可以是函数或U函数解析的字符串。如果是字符串，该函数将附带一个id参数
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
		 * @param null $map
		 * @param false $sort
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

		public function keyDoAction($url, $title = '操作', $attr = [], $status = [], $event = 'edit')
		{
			if (false === strpos($url, '/')) {
				if (false !== strpos($this->request->controller(), 'Admin.')) {
					// 补充
					$url = $this->request->module() . '/' . lcfirst($this->request->controller()) . '/' . $url;
				} else {
					// 补充
					$url = $this->request->module() . '/' . $this->request->controller() . '/' . $url;
				}
			}
			if (false !== strpos($url, '{$')) {
				// 补充
				$url = str_replace('{$', '{{d.', $url);
				$url = str_replace('}', '}}', $url);
			}
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
		 * 新页面功能<a href="doc/chart/highcharts.html" toggle="navtab" data-id="doc-highcharts" data-title="Highcharts图表说明">使用说明</a>.
		 * @param unknown $url
		 * @param string $text
		 * @param unknown $arr
		 * @return Table
		 */
		public function keyDoActionMask($url, $title = '编辑', $status = [], $attrs = [])
		{
			$attr['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-mask-' . $this->request->time());
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = 'dialog';
			$attr['data-maxable'] = 'true';
			$attr['data-resizable'] = false;
			$attr['data-drawable'] = 'false';
			$attr['data-maxable'] = 'false';
			$attr['data-mask'] = 'true';
			$attr['icon'] = !empty($attr['icon']) ? $attr['icon'] : 'pencil-square-o';
			$attr['width'] = isset($attrs['width']) ? $attrs['width'] : $this->dialog_width_default;
			$attr['height'] = isset($attrs['height']) ? $attrs['height'] : $this->dialog_height_default;
			return $this->keyDoAction($url, $title, array_merge($attr, $attrs), $status);
		}

		public function keyDoActionFull($url, $title = '全屏', $status = [], $attr = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = $this->toggle;
			$attr['max'] = 'true';
			$attr['icon'] = !empty($attr['icon']) ? $attr['icon'] : 'pencil-square-o';
			$attr['message'] = '';
			return $this->keyDoAction($url, $title, $attr, $status);
		}

		public function keyDoActionUpdate($url = 'update?id={$id}', $title = '编辑', $status = [], $attrs = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = $this->toggle;
			$attr['width'] = isset($attrs['width']) ? $attrs['width'] : $this->dialog_width_default;
			$attr['height'] = isset($attrs['height']) ? $attrs['height'] : $this->dialog_height_default;
			$attr['icon'] = 'edit';
			$attr['message'] = '';
			$status = empty($status) ? [
				0,
				1,
				2,
			] : $status;
			return $this->keyDoAction($url, $title, array_merge($attr, $attrs), $status);
		}

		/**
		 * 不可操作
		 * @param string $text
		 * @param array $status
		 * @param array $attr
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 16:16
		 */
		public function keyDoActionDisable($title = '不可操作', $status = [], $attr = [])
		{
			$attr['message'] = $title;
			$attr['class'] = 'layui-bg-orange';
			$attr['custom_icon'] = 'jinyong';
			$status = empty($status) ? [
				1,
				2,
			] : $status;
			return $this->keyDoAction('', $title, $attr, $status, 'no');
		}

		/**
		 * 较大的操作框
		 * @param        $url
		 * @param string $text
		 * @param array $status
		 * @param array $attr
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 16:16
		 */
		public function keyDoActionBig($url, $title = '编辑', $status = [], $attr = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = 'dialog';
			$attr['width'] = !empty($attr['width']) ? $attr['width'] : '1200';
			$attr['height'] = !empty($attr['height']) ? $attr['height'] : '730';
			$attr['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-edit-' . $this->request->time());
			$attr['icon'] = 'pencil-square-o';
			return $this->keyDoAction($url, $title, $attr, $status);
		}

		public function keyDoActionView($url = 'view?id={$id}', $title = '详情', $status = [], $attrs = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = 'dialog';
			$attr['width'] = isset($attrs['width']) ? $attrs['width'] : $this->dialog_width_default;
			$attr['height'] = isset($attrs['height']) ? $attrs['height'] : $this->dialog_height_default;
			$attr['data-id'] = 'id' . md5('dialog-' . $this->request->controller() . '-view-' . $this->request->time());
			$attr['data-mask'] = 'false';
			$attr['icon'] = 'search';
			$status = empty($status) ? [
				0,
				1,
				2,
			] : $status;
			return $this->keyDoAction($url, $title, array_merge($attr, $attrs), $status);
		}

		public function keyDoActionManager($url = 'manager?id={$id}', $title = '授权', $status = [], $attr = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = $this->toggle;
			$attr['width'] = $this->dialog_width_default;
			$attr['height'] = $this->dialog_height_default;
			$attr['icon'] = 'auz';
			$status = empty($status) ? [
				0,
				1,
				2,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status);
		}


		public function keyDoActionLink($url, $title, $status = [], $attr = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['toggle'] = $this->toggle;
			$attr['icon'] = 'link';
			$attr['width'] = $this->dialog_width_default;
			$attr['height'] = $this->dialog_height_default;
			$status = empty($status) ? [
				-1,
				0,
				1,
				2,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status);
		}

		public function keyDoActionDelete($url = 'delete?id={$id}', $title = '删除', $status = [], $attr = [])
		{
			$attr['class'] = 'layui-btn-danger';
			$attr['toggle'] = 'doajax';
			$attr['message'] = '确定删除么？';
			$attr['icon'] = 'delete';
			$status = empty($status) ? [
				-1,
				0,
				1,
				2,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status, 'ajax');
		}

		public function keyDoActionClear($url = 'clear?id={$id}', $title = '彻底删除', $status = [], $attr = [])
		{
			$attr['class'] = 'btn-red';
			$attr['toggle'] = 'doajax';
			$attr['message'] = '确定彻底删除么？';
			$attr['icon'] = 'trash-o';
			$status = empty($status) ? [
				-2,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status, 'ajax');
		}

		/**
		 * 禁用
		 * @param        $url
		 * @param string $text
		 * @param array $status
		 * @param array $attr
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 16:16
		 */
		public function keyDoActionForbid($url = 'forbid?id={$id}', $title = '禁用', $status = [], $attr = [])
		{
			$attr['class'] = 'layui-btn-danger';
			$attr['toggle'] = 'doajax';
			$attr['message'] = '确定' . $title . '么？';
			$attr['icon'] = 'close-fill';
			$status = empty($status) ? [
				1,
				2,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status, 'ajax');
		}

		public function keyDoActionToCheck($url = 'check?id={$id}', $title = '通过审核', $status = [], $attr = [])
		{
			$attr['class'] = 'layui-bg-green';
			$attr['icon'] = 'ok';
			$attr['message'] = '确定' . $title . '么？';
			$status = empty($status) ? [
				0,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status, 'ajax');
		}

		/**
		 * 还原
		 * @param string $url
		 * @param string $text
		 * @param array $status
		 * @param array $attr
		 * @return Table
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 18:19
		 */
		public function keyDoActionRestore($url = 'restore?id={$id}', $title = '还原', $status = [], $attr = [])
		{
			$attr['class'] = 'btn-red';
			$attr['toggle'] = 'doajax';
			$attr['event'] = 'ajax';
			$attr['message'] = '确定' . $title . '么？';
			$attr['icon'] = 'ok-circle';
			$status = empty($status) ? [
				-1,
				-2,
			] : $status;
			return $this->keyDoAction($url, $title, $attr, $status, 'ajax');
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
		 * @datetime: 2019/4/12 18:19
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
		 * @datetime: 2019/4/12 18:19
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
		 * @datetime: 2019/4/12 17:56
		 */
		public function data($data, $pagination = true)
		{
			$this->_data = $data;
			$this->_pagination = $pagination;
			return $this;
		}

		/**
		 * 当前列表操作宽度
		 * @param $width
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 * @datetime: 2020/7/16 16:58
		 */
		public function keyActionWidth($width)
		{
			$this->_key_action_width = $width;
			return $this;
		}

		/**
		 * 返回页面
		 * @param array $vars
		 * @param array $config
		 * @return string
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 17:54
		 */
		public function fetch($name = 'table', $vars = [], $config = [])
		{
			if ($this->request->isPost()) {
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
							$whereModel = $model::where($searchWhere)
								->where($this->_where);
							if (count($this->_count)) {
								$result = [];
							} else {
								foreach ($this->_keyList as $index => $item) {
									if (in_array($item['field'], $columns)) {
										$column = $whereModel->field($item['field'])->distinct(true)->limit(10)->column($item['field']);
										if (count($item['map']) > 0 && $column) {
											$temp = [];
											foreach ($column as $i => $co) {
												if (isset($item['map'][$co])) {
													$temp[] = $item['map'][$co];
												}
											}
											$column = $temp;
										}
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
							$whereModel = $model::where($searchWhere)
								->where($this->_where);
							$result['code'] = 0;
							if (count($this->_count)) {
								$lists = $whereModel->withCount($this->_count)
									->order($searchOrder)
									->limit($list_rows * ($page - 1), $list_rows)->select();
							} else {
								$lists = $whereModel
									->order($searchOrder)
									->limit($list_rows * ($page - 1), $list_rows)->select();
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
				return json($result);
			} else {
				if ($this->request->has('page', 'get')) {
//					try {
					$this->_field = $this->_getField($this->_field);
					$list_rows = $this->request->has('limit', 'param') ? $this->request->param('limit') : Config::get('paginate.list_rows');
					$page = $this->request->has('page', 'param') ? $this->request->param('page') : 1;
					$result = [];
					$searchWhere = $this->_searchWhere();
					$searchOrder = $this->_searchOrder();
					$model = $this->_model;
					if ($model instanceof \Closure) {
						// 闭包
						$result = $model($searchWhere, $this->_field, $searchOrder, $page, $list_rows);
					} elseif (!is_null($model)) {
						$whereModel = $model::where($searchWhere)
//							->field(implode($this->_field, ','))
							->where($this->_where);
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
							$result['count'] = count($this->_data);
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
//					} catch (Exception $e) {
//						$result['code'] = 0;
//						$result['message'] = $e->getMessage();
//					}
					return json($result);
				} else {
					foreach ($this->_keyList as $index => $item) {
						if ($item['type'] == 'hidden') {
							unset($this->_keyList[$index]);
						}
					}
					if (count($this->_do_action)) {
						if (is_null($this->_key_action_width)) {
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
							$this->_key_action_width = (($max + count($object) - 1) * 70 + 100);
						}
						$this->_keyList[] = [
							'fixed' => 'right',
							'title' => '操作',
							'align' => 'center',
							'toolbar' => '#' . $this->_namespace . '-table-action',
							'width' => $this->_key_action_width
						];
						!empty($this->_left_leader) && array_unshift($this->_keyList, $this->_left_leader);
					}
					$get = $this->request->except('v,m,status', 'get');
					if (!empty($get)) {
						$action = $this->request->action() . '?' . http_build_query($get);
					} else {
						$action = $this->request->action();
					}
					// 查询当前菜单
					$menu = MenuModel::where('status', 1)
						->where('action', $action)
						->where('controller', $this->request->controller())
						->where('module', $this->request->module())
						->find();
					if ($menu && !$this->_title) {
						$this->_title = $menu['title'];
					}
					if (isset($this->_excel['filename']) && !$this->_excel['filename']) {
						$this->_excel['filename'] = $this->_title . '_' . time_format(time(), 'Y_m_d');
					}
					if ($menu['group']) {
						$this->assign('menu_group_title', $menu['group']);
					}
					if ($menu['pid']) {
						$p_menu = MenuModel::where('status', 1)
							->where('id', $menu['pid'])
							->find();
						$this->assign('p_menu_title', $p_menu['title']);
					} else {
						$this->assign('p_menu_title', $menu['title']);
					}
					$this->assign('menu_title', $this->_title);
					// 显示页面
					$this->assign('templets', $this->_templets);
					$this->assign('menu_title', $this->_title);
					$this->assign('do_action', $this->_do_action);
					$this->assign('toolbar', $this->_toolbar);
					$this->assign('namespace', $this->_namespace);
					$this->assign('suggest', $this->_suggest);
					$this->assign('statistics', $this->_statistics);
					$this->assign('warning', $this->_warning);
					$this->assign('keyList', $this->_keyList);
					$this->assign('buttonList', $this->_buttonList);
					$this->assign('callback', $this->_callback);
					$this->assign('excel', $this->_excel);
					$this->assign('filter', $this->_filter);
					// 数据转换
					/*
					 * 配置主键*
					 */
					$this->assign('pk', $this->_default_pk);
					/* 加入搜索 */
					if (count($this->_search) > 0) {
						$this->assign('searches', $this->_search);
						if (count($this->_search_more) > 0) {
							$this->assign('search_more', $this->_search_more);
						}
					}
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
					return parent::_fetch($name, $vars, $config);
				}
			}
		}


		protected function _getField($field)
		{
			$field = array_unique($field);
			$model = $this->_model;
			if (!is_null($model)) {
				$db_fields = $model::getTableFields();
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
			$model = $this->_model;
			if (!is_null($model)) {
				$db_fields = $model::getTableFields();
			} else {
				$db_fields = $this->_field;
			}
			$where = [];
			if (is_array($fields)) {
				foreach ($this->_search as $search) {
					if (in_array($search['field'], $db_fields) && isset($fields[$search['field']]) && $fields[$search['field']] != '') {
						if ('=' == $search['condition']) {
							$where[] = [$search['field'], '=', $fields[$search['field']]];
						} elseif ('like' === $search['condition']) {
							$where[] = [$search['field'], 'like', '%' . $fields[$search['field']] . '%'];
						} elseif ('between' === $search['condition']) {
							if ('datepicker' === $search['type']) {
								$temp = explode(' - ', $fields[$search['field']]);
								if ($temp[0] && $temp[1]) {
									$where[] = [$search['field'], 'between time', $temp];
								}
							}
						}
					}
				}
			}
			$urlFields = $this->request->except('v,page,limit,user,m,field,video,store');
			if (is_array($urlFields)) {
				foreach ($urlFields as $field => $field_value) {
					$out = false;
					foreach ($this->_search as $search) {
						if ($search['field'] == $field) {
							$out = true;
							continue;
						}
					}
					if ($out) {
						continue;
					}
					$where[] = [$field, '=', $field_value];
				}
			}
			//
			$filterSos = json_decode(htmlspecialchars_decode($this->request->param('filterSos/s')), true);
			if (is_array($filterSos)) {
				foreach ($filterSos as $index => $filterSo) {
					if ('in' == $filterSo['mode']) {
						$where[] = $this->_getMode($filterSo);
					} elseif ('group' == $filterSo['mode']) {
						throw new Exception('暂不支持');
						foreach ($filterSo['children'] as $child) {
							$where[] = $this->_getMode($child);
						}
					} else {
					}
				}
			}
			return $where;
		}

		private function _getMode($filter)
		{
			if ('in' == $filter['mode']) {
				$data = [$filter['field'], 'in', $this->_getFieldValue($filter['field'], $filter['values'])];
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
				if ($key['type'] instanceof \Closure) {
					// 闭包
					$conver_data[$key['field']] = $key['type']($data, $excel);
				} elseif (false !== strpos($key['field'], ',')) {
					$fields = explode(',', $key['field']);
					foreach ($fields as $field) {
						$conver_data[$field] = $data[$field];
					}
				} else {
					if (false !== strpos($key['field'], '{$')) {
						$display = $key['field'];
					} else {
						$display = '{$data.' . $key['field'] . '}';
					}
					$view = $this->app['view'];
					$value = $view->display($display, ['data' => $data]);
					if (false === strpos($key['field'], '{$') && strpos($key['field'], '.')) {
						$field = explode('.', $key['field']);
						$conver_data[$field[0]][$field[1]] = ('status' == $key['field']) ? (int)$value : $value;
					} else {
						$conver_data[$key['field']] = ('status' == $key['field']) ? (int)$value : $value;
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
