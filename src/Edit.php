<?php

	// +----------------------------------------------------------------------
	// | builder
	// +----------------------------------------------------------------------
	// | Copyright (c) 2015-2024 http://www.yicmf.com, All rights reserved.
	// +----------------------------------------------------------------------
	// | Author: 微尘 <yicmf@qq.com>
	// +----------------------------------------------------------------------

	namespace yicmf\builder;

	use Overtrue\Pinyin\Pinyin;
	use think\facade\Db;
	use think\Exception;
	use think\exception\ValidateException;
	use think\facade\Lang;
	use think\Model;
	use yicmf\builder\edit\FormItemBuilder;

	class Edit extends Builder
	{
		private $_title = '';


		private $_keyList = [];
		/** 2026-09-06 拆分重构：表单项配置持有者 */
		private $formItemBuilderObj;

		// 提示信息
		private $_explaints = [];
		private $_templets = [];

		/**
		 * @var Model|array
		 */
		private $_data;

		private $_form_submit;
		private $_form_close;
		private $_form_reset;
		private $_form_buttons;

		private $_reload = 'true';
		private $_mask = 'true';


		private $_group = [];

		private $_triggers = [];

		// 默认获取主键的字段
		protected $_default_pk = 'id';
		/**
		 * 验证失败是否抛出异常
		 * @var bool
		 */
		protected $failException = false;

		/**
		 * 是否批量验证
		 * @var bool
		 */
		protected $batchValidate = false;

		protected $_namespace;

	/**
	 * 表单项构建器（懒加载，兼容跳过构造函数的测试场景）
	 * @return \yicmf\builder\edit\FormItemBuilder
	 */
	private function formItemBuilder()
	{
		if (!isset($this->formItemBuilderObj)) {
			$this->formItemBuilderObj = new FormItemBuilder($this->_default_pk);
		}
		return $this->formItemBuilderObj;
	}

		/**
		 * 初始化编辑器构建器，生成当前请求唯一的表单命名空间标识
		 */
		protected function initialize()
		{

			// 命名空间
			$this->_namespace = $this->module . '_' . str_replace('.', '_', $this->request->controller())
				. '_' . $this->request->action() . '_'
				. md5(json_encode($this->request->except(explode(',', 'v,user'))) . uniqid());
			//                .implode('_',$this->request->except('v'));
		}

		/**
		 * 设置请求表单头
		 * @param $reload
		 * @param $mask
		 * @return $this
		 */
		public function form($reload = true, $mask = true)
		{
			$this->_mask = $mask;
			$this->_reload = $reload;
			return $this;
		}

		/**
		 * 获取命名空间
		 * @return string
		 */
		public function getNamespace()
		{
			return $this->_namespace;
		}

		/**
		 * 设置标题.
		 * @param string $title
		 * @return $this
		 */
		public function title($title)
		{
			$this->_title = $title;
			return $this;
		}

		/**
		 * 页面下面的提示.
		 * @param string|array $explain
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function explain($explain)
		{
			if (is_array($explain)) {
				$this->_explaints = array_merge($this->_explaints, $explain);
			} else {
				$this->_explaints[] = $explain;
			}
			return $this;
		}

		/**
		 * 设置触发.
		 * @param string|array $trigger 需要触发的表单项名，目前支持select（单选类型）、text、radio三种
		 * @param string|array $value 触发的值 单一
		 * @param string|array $shows 触发后要显示的表单项名，目前不支持普通联动、范围、拖动排序、静态文本
		 * @param string $tpl 执行的js方法
		 * @return $this
		 */
		public function setTrigger($trigger, $value = '', $shows = '', $tpl = '')
		{
			if (is_array($trigger)) {
				$this->_triggers = array_merge($this->_triggers, $trigger);
			} else {
			// [Buddy 2026-09-02] 调整：null/int 安全 + 修正 strpos 位置 0 被判为 falsy 的逻辑缺陷
			if (is_string($value) && strpos($value, ',') !== false) {
				$value = explode(',', $value);
				} elseif (!is_array($value) && (is_numeric($value) || is_string($value))) {
					$value = [$value];
				} elseif (is_array($value) && empty($value)) {
					$value = [''];
				}
				foreach ($value as $item) {
					$this->_triggers[$trigger][] = ['value' => $item, 'show' => $shows, 'tpl' => $tpl];
				}
			}
			//参与的所有字段合集
			//                if (isset($this->_triggers[$trigger]['field'])) {
			//                    $this->_triggers[$trigger]['field'] .= ',' . $show;
			//                } else {
			//                    $this->_triggers[$trigger]['field'] = $show;
			//
			return $this;
		}

		/**
		 * 直接显示html.
		 * @param string $name
		 * @param string $html
		 * @param string $title
		 * @param string|null $tips
		 * @return $this
		 */
		public function keyHtml($name, $html, $title, $tips = null)
		{
		$this->formItemBuilder()->keyHtml(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($name, $title, $tips, 'html', $html);
		}


		/**
		 * 隐藏表单
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyHidden($name, $default = '')
		{
		$this->formItemBuilder()->keyHidden(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($name, null, null, 'hidden', [], $default);
		}


		/**
		 * 只读文本.
		 * @param string $name
		 * @param string $title
		 * @param string|null $tips
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyReadOnly($name, $title, $tips = null)
		{
		$this->formItemBuilder()->keyReadOnly(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($name, $title, $tips, 'readonly');
		}

		/**
		 * 可以复制内容
		 * @param string $name
		 * @param string $title
		 * @param string|null $tips
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyCopy($name, $title, $tips = null)
		{
		$this->formItemBuilder()->keyCopy(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($name, $title, $tips, 'copy');
		}

		/**
		 * 文本输入框.
		 * @param string $name
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @param array|null $verify
		 * @return $this
		 */
		public function keyText($name, $title, $tips = null, $default = null, $verify = null)
		{
		$this->formItemBuilder()->keyText(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($name, $title, $tips, 'string', null, $default, $verify);
		}

		/**
		 * 备案信息
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @param array|null $verify
		 * @return $this
		 */
		public function keyIcp($field, $title, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keyIcp(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'icp', null, $default, $verify);
		}

		/**
		 * 字符串
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @param string|null $verify
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function keyTextInline($field, $title, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keyTextInline(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'inline', null, $default, $verify);
		}

		/**
		 * 备安全验证
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param int $wait_time
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function keySafeCheck($field, $title, $tips = null, $wait_time = 60)
		{
		$this->formItemBuilder()->keySafeCheck(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$this->key($field, $title, $tips, 'safe_check', ['wait_time' => $wait_time, 'obj_id' => uniqid()]);
		// 			return $this->keyTextInline('check_code', '验证码', '请输入收到的验证码', '', 'required');
		}

		/**
		 * 数组输入框，内容以’,‘分隔
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $verify
		 * @param int $size
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function keyArray($field, $title, $tips = null, $size = 30, $verify = null)
		{
		$this->formItemBuilder()->keyArray(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'string', null, $size, $verify);
		}

		/**
		 * 文本输入框常用显示
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param int $size
		 * @param array|null $verify
		 * @return $this
		 */
		public function keyTitle($field = 'title', $title = '标题', $tips = null, $size = 30, $verify = null)
		{
		$this->formItemBuilder()->keyTitle(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->keyText($field, $title, $tips, null, $size, $verify);
		}

		/**
		 * 地图选择（需安装高德插件）
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyAmap($field, $title, $tips = null, $default = null, $verify = null)
		{
		$this->formItemBuilder()->keyAmap(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'amap', null, $default, $verify);
		}

		/**
		 * 单选按钮.
		 * @param string $field
		 * @param string $title
		 * @param array $options
		 * @param string|null $tips
		 * @param array|null $verify
		 * @param array|null $disabled
		 * @return $this
		 */
		public function keyRadio($field, $title, $options, $tips = null, $default = 0, $verify = null, $disabled = null)
		{
		$this->formItemBuilder()->keyRadio(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'radio', $options, $default, $verify, 30, $disabled);
		}

		/**
		 * 是与否的单选.
		 * @param string $field
		 * @param string $title
		 * @param array $options
		 * @param int $default
		 * @param string|null $tips
		 * @param string|null $disabled
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyBool($field, $title, $tips = null, $default = 0, $options = ['否', '是'], $verify = null, $disabled = null)
		{
		$this->formItemBuilder()->keyBool(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->keyRadio($field, $title, $options, $tips, $default, $verify, $disabled);
		}

		/**
		 * 开关
		 * @param string $field
		 * @param string $title
		 * @param array $options
		 * @param int $default
		 * @param string|null $tips
		 * @param string|null $disabled
		 * @param string|null $verify
		 * @return $this
		 */
		public function keySwitch($field, $title, $tips = null, $default = 1, $options = [1 => '是', 0 => '否'], $verify = null, $disabled = null)
		{
		$this->formItemBuilder()->keySwitch(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'switch', $options, $default, $verify, 30, $disabled);
		}

		/**
		 * 性别
		 * @param string $field
		 * @param string $title
		 * @param int $default
		 * @param string|null $tips
		 * @return $this
		 */
		public function keySex($field = 'sex', $title = '性别', $tips = null, $default = 0)
		{
		$this->formItemBuilder()->keySex(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$options = [
		// 				2 => '女',
		// 				1 => '男',
		// 				0 => '保密',
		// 			];
		// 			return $this->keyRadio($field, $title, $options, $tips, $default);
		}


		/**
		 * 新窗口选择多个
		 * @param string $field
		 * @param string $title
		 * @param string $column
		 * @param string|null $tips
		 * @param string|null $default
		 * @param int $size
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyBelongsToMany($field, $title, $column, $tips = null, $default = null, $size = 30, $verify = null)
		{
		$this->formItemBuilder()->keyBelongsToMany(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'belongsToMany', ['column' => $column, 'field' => $field, 'limit' => 0], $default, $verify, $size);
		}



		/**
		 * 新窗口选择一个
         * ->keyBelongTo('document.title|ducoment_id','im/admin.Document/index','关联常见问题')
         * ->keyBelongTo('document|ducoment_id','im/admin.Document/index','关联常见问题','title')
         * ->keyBelongTo('document.title','im/admin.Document/index','关联常见问题')
         * ->setTrigger('type',0,'reply')
         * ->setTrigger('type',1,'Document_id')
         * @param $field
         * @param $url
         * @param $title
         * @param $show_field
         * @param $tips
         * @param $default
         * @param $size
         * @param $verify
         * @return $this
         */
        public function keyBelongTo($field, $url, $title, $show_field = '', $tips = null, $default = null, $size = null, $verify = null)
		{
		$this->formItemBuilder()->keyBelongTo(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		//             $old_filed = $field;
		// 			if (strpos($field, '|')) {
		// 				// 获取field
		// 				$temp = explode('|', $field);
		// 				$v_field = $temp[1];
		// 				$field = $temp[0];
		// 			}
		// 			if (!strpos($url, '?')) {
		// 				// 获取field
		//                 $url = $url.'?';
		// 			}
		// 			if (strpos($field, '.')) {
		// 				$temp = explode('.', $field);
		// 				$show_field = $temp[1];
		// 				$model_name = $temp[0];
		//                 if (!strpos($old_filed, '|')) {
		//                     $v_field = $temp[0] . '_id';
		//                 }
		// 			} else {
		//                 $show_field = 'id';
		// 			}
		// 			return $this->key($v_field, $title, $tips, 'belongTo', ['model_name'=>$model_name,'old_field'=>$old_filed,'show_field_value'=>'','url' => $url, 'show_field' => $show_field, 'field' => $field, 'limit' => 0], $default, $verify, $size);
		}

		/**
		 * 下拉选择
		 * @param string $field
		 * @param string $title
		 * @param array $options
		 * @param string|null $tips
		 * @param string|null $default
		 * @param array|null $verify
		 * @return $this
		 */
		public function keySelect($field, $title, $options, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keySelect(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'select', $options, $default, $verify);
		}

		/**
		 * 下拉列表多选
		 * @param string $field
		 * @param string $title
		 * @param array|\Closure $options
		 * @param string|null $tips
		 * @param array|null $verify
		 * @param string|null $default
		 * @param int $size
		 * @return $this
		 */
		public function keySelectMultiple($field, $title, $options, $tips = null, $default = '', $size = 30, $verify = null)
		{
		$this->formItemBuilder()->keySelectMultiple(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'select_multiple', $options, $default, $verify, $size);
		}

		/**
		 * 多级下拉选择
		 * @param string $field
		 * @param string $title
		 * @param array $options
		 * @param string|null $tips
		 * @param string|null $default
		 * @param array|null $verify
		 * @return $this
		 */
		public function keySelectMultistage($field, $title, $options, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keySelectMultistage(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'select_multistage', $options, $default, $verify);
		}

		/**
		 * 状态
		 * @param string|null $default
		 * @param string $tips
		 * @param array|null $options
		 * @param array|null $verify
		 * @return $this
		 */
		public function keyStatus($options = null, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keyStatus(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$options = $options ?: [
		// 				-2 => '删除',
		// 				-1 => '禁用',
		// 				1 => '启用',
		// 				0 => '未审核',
		// 				2 => '推荐',
		// 			];
		// 			return $this->keySelect('status', '状态', $options, $tips, $default, $verify);
		}

		/**
		 * 复选框.
		 * @param string $field
		 * @param string $title
		 * @param array $options
		 * @param string|null $tips
		 * @param array|null $verify
		 * @return $this
		 */
		public function keyCheckBox($field, $title, $options, $tips = null, $default = [], $verify = null)
		{
		$this->formItemBuilder()->keyCheckBox(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'checkbox', $options, $default, $verify);
		}

//		/**
//		 * 关联单选复选框.
//		 * @param string $field
//		 * @param string $title
//		 * @param array $options
//		 *                                   string $ajax_url 请求的url
//		 *                                   string $field 关联的字段
//		 * @param string|null $tips
//		 * @param array|null $verify
//		 * @return $this
//		 */
//		public function keyCheckBoxLinkCheck($field, $title, $options, $tips = null, $verify = null)
//		{
//			return $this->key($field, $title, $tips, 'checkbox_link_check', $options, 30, $verify);
//		}


		/**
		 * TextArea
		 * @param      $field
		 * @param      $title
		 * @param null $tips
		 * @param string $default
		 * @param int $cols 列
		 * @param int $rows 行
		 * @param null $verify
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyTextArea($field, $title, $tips = null, $default = '', $cols = 50, $rows = 2, $verify = null)
		{
		$this->formItemBuilder()->keyTextArea(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'textarea', null, $default, $verify, [$cols, $rows]);
		}

		/**
		 * 意图配置可视化编辑器（intent_config）
		 * [Buddy 2026-09-12] 新增：委托 FormItemBuilder::keyIntentConfig，结构化编辑 fa_ai_flow_node.config。
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string $default
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyIntentConfig($field, $title, $tips = null, $default = '', $verify = null)
		{
		// [ZCode 2026-09-14] 调整：改为委托 keyJson 通用 JSON 编辑器（兼容旧调用，原专用 intent_config 模板不再使用）
		$this->formItemBuilder()->keyJson(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		}

		/**
		 * 任意 JSON 结构可视化编辑器（json）
		 * [ZCode 2026-09-14] 新增：委托 FormItemBuilder::keyJson，支持 对象(分类子项)/数组(逗号分隔)/开关(is_ 前缀按 0/1 存储，
		 * 已定义为非数字则按字符串)/字符串/数字 任意组合；复杂结构自动回退为该键独立「原始 JSON」编辑（保存无损）。
		 * $title 缺省直接用字段名，也可传入自定义标题。
		 * [ZCode 2026-09-14] 调整：新增 $titles 参数（键名=>标题 映射），定义 JSON 内部各字段的展示标题，缺省仍用键名。
		 * @param string $field
		 * @param string|null $title 缺省用字段名
		 * @param string|null $tips
		 * @param string $default
		 * @param string|null $verify
		 * @param array $titles JSON 内部字段标题映射：['scene'=>'场景','is_llm'=>'启用LLM']
		 * @return $this
		 */
		public function keyJson($field, $title = null, $tips = null, $default = '', $verify = null, $titles = [])
		{
		$this->formItemBuilder()->keyJson(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		}

		/**
		 * 显示文本
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyLabel($field, $title, $tips = null)
		{
		$this->formItemBuilder()->keyLabel(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'label');
		}

		/**
		 * 闭包函数
		 * @param string $title
		 * @param \Closure $closure
		 * @param null $tips
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyClosure($title, $closure, $tips = null)
		{
		$this->formItemBuilder()->keyClosure(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$pinyin = new Pinyin();
		// 			return $this->key($pinyin->permalink($title, '_'), text($title), $tips, $closure);
		}

		/**
		 * 输入密码
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @return $this
		 */
		public function keyPassword($field, $title, $tips = null, $default = null)
		{
		$this->formItemBuilder()->keyPassword(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'password', null, $default);
		}


		/**
		 * 输入url地址
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param int $size
		 * @param array|null $verify
		 * @param string|null $default
		 * @return $this
		 */
		public function keyUrl($field, $title, $tips = '需要以http或者https开头', $default = '', $size = 50, $verify = '')
		{
		$this->formItemBuilder()->keyUrl(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'url', null, $default, $verify ? ($verify . '|url') : 'url', $size);
		}

		/**
		 * 颜色选择器.
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @return $this
		 */
		public function keyColor($field, $title, $tips = null, $default = '')
		{
		$this->formItemBuilder()->keyColor(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'color', null, $default);
		}

		/**
		 * 可拖动插件列表.
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @return $this
		 */
		public function keyDragsortLi($field, $title, $tips = null, $options = null)
		{
		$this->formItemBuilder()->keyDragsortLi(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'dragsort_li', $options);
		}

		/**
		 * 自带的标签功能.
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param array|null $verify
		 * @param string|null $default
		 * @return $this
		 */
		public function keyTags($field, $title, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keyTags(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'tags', null, $default, $verify);
		}

		/**
		 * 滑块
		 * @param string $field 字段
		 * @param string $title 标题
		 * @param $tips
		 * @param string|null $default
		 * @param array $option
		 * @param string|null $verify
		 * @return $this
		 */
		public function keySlider($field, $title, $tips = null, $default = '', $option = [], $verify = null)
		{
		$this->formItemBuilder()->keySlider(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$option_default = [
		// 				'min' => 0,
		// 				'max' => 255,
		// 				'step' => 1,
		// 				'decimal-place' => 0,
		// 			];
		// 			$option = is_array($option) ? array_merge($option_default, $option) : $option_default;
		// 			return $this->key($field, $title, $tips, 'slider', $option, $default, $verify);
		}

		/**
		 * 价格
		 * @param      $field
		 * @param      $title
		 * @param null $tips
		 * @param null $verify
		 * @param string|null $default
		 * @param int $size
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyDecimal($field, $title, $tips = null, $default = '', $verify = null, $size = '')
		{
		$this->formItemBuilder()->keyDecimal(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$verify_default = [
		// 				'rule' => 'money',
		// 				'rule-money' => '[/^(?!0+(?:\.0+)?$)(?:[1-9]\d*|0)(?:\.\d{1,2})?$/, \'金额必须大于0并且只能精确到分\']',
		// 				'tip' => '请填写金额',
		// 				'ok' => '',
		// 			];
		// 			$verify = is_array($verify) ? array_merge($verify_default, $verify) : $verify_default;
		// 			return $this->key($field, $title, $tips, 'number', null, $default, $verify, $size, null, '￥');
		}

		/**
		 * 排序
		 * @param string $field
		 * @param string $title
		 * @param string $tips
		 * @param string|null $default
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keySort($field = 'sort', $title = '排序', $tips = '数值越大越靠前，最大255', $default = 0)
		{
		$this->formItemBuilder()->keySort(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'number', null, $default, 'require|number|sort');
		}

		/**
		 * 要求验证填写数字.
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $verify
		 * @param string|null $default
		 * @return $this
		 */
		public function keyNumber($field, $title, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keyNumber(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'number', null, $default, $verify);
		}

		/**
		 * 要求验证填写数字.
		 * @param string $field
		 * @param string $title
		 * @param string $tips
		 * @param string|null $default
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyTimeCycle($field, $title, $tips = null, $default = '', $verify = null)
		{
		$this->formItemBuilder()->keyTimeCycle(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'time_cycle', null, $default, $verify);
		}

		/**
		 * 邮箱.
		 * @param string $field
		 * @param string $title
		 * @param string $tips
		 * @param string|null $default
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyEmail($field, $title, $tips = null, $default = '', $verify = 'email')
		{
		$this->formItemBuilder()->keyEmail(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'email', null, $default, $verify);
		}


		/**
		 * 手机
		 * @param             $field
		 * @param             $title
		 * @param string|null $tips
		 * @param string|null $default
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function keyMobile($field, $title, $tips = null, $default = '')
		{
		$this->formItemBuilder()->keyMobile(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'inline', null, $default, 'mobile');
		}

		/**
		 * 评分
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param int $default
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function keyRate($field, $title, $tips = '', $default = 3)
		{
		$this->formItemBuilder()->keyRate(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'rate', null, $default);
		}

		//        /**
		//         * 选择来源、有待改进.
		//         * @param string     $field
		//         * @param string     $title
		//         * @param string     $tips
		//         * @param number     $size
		//         * @param array|null $verify
		//         * @return $this
		//         */
		//        public function keySourse($field, $title, $tips, $options)
		//        {
		//            return $this->key($field, $title, $tips, 'sourse', $options);
		//        }

		/**
		 * 富文本编辑器
		 * http://fex.baidu.com/ueditor/#start-start
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param array $config
		 * @param array $style
		 * @return $this
		 */
		public function keyEditor($field, $title, $tips = null, $default = '', $config = [], $style = ['width' => '900', 'height' => '400'])
		{
			$items =
				[
					'all' => [[
						'fullscreen', 'source', '|', 'undo', 'redo', '|',
						'bold', 'italic', 'underline', 'fontborder', 'strikethrough', 'superscript', 'subscript', 'removeformat', 'formatmatch', 'autotypeset', 'blockquote', 'pasteplain', '|', 'forecolor', 'backcolor', 'insertorderedlist', 'insertunorderedlist', 'selectall', 'cleardoc', '|',
						'rowspacingtop', 'rowspacingbottom', 'lineheight', '|',
						'customstyle', 'paragraph', 'fontfamily', 'fontsize', '|',
						'directionalityltr', 'directionalityrtl', 'indent', '|',
						'justifyleft', 'justifycenter', 'justifyright', 'justifyjustify', '|', 'touppercase', 'tolowercase', '|',
						'link', 'unlink', 'anchor', '|', 'imagenone', 'imageleft', 'imageright', 'imagecenter', '|',
						'simpleupload', 'insertimage', 'emotion', 'scrawl', 'insertvideo', 'music', 'attachment', 'map', 'gmap', 'insertframe', 'insertcode', 'webapp', 'pagebreak', 'template', 'background', '|',
						'horizontal', 'date', 'time', 'spechars', 'snapscreen', 'wordimage', '|',
						'inserttable', 'deletetable', 'insertparagraphbeforetable', 'insertrow', 'deleterow', 'insertcol', 'deletecol', 'mergecells', 'mergeright', 'mergedown', 'splittocells', 'splittorows', 'splittocols', 'charts', '|',
						'print', 'preview', 'searchreplace', 'drafts', 'help'
					]]
					,
					'common' => [
						[
							'fullscreen', 'source', 'preview', '|', 'insertcode',
							'fontfamily', 'fontsize', 'bold', 'italic', 'underline', 'fontborder', 'strikethrough', 'forecolor', 'backcolor', 'superscript', 'subscript',
							'link', '|', 'insertorderedlist', 'insertunorderedlist',
							'justifyleft', 'justifycenter', 'justifyright', 'justifyjustify', 'horizontal', 'spechars', 'cleardoc', 'removeformat', 'selectall', 'pasteplain', 'undo', 'redo'
						],
						[
							'inserttable', 'deletetable', 'insertparagraphbeforetable', 'insertrow', 'deleterow', 'insertcol', 'deletecol', 'mergecells', 'mergeright', 'mergedown', 'splittocells', 'splittorows', 'splittocols', '|',
							'simpleupload', 'insertimage', 'imagenone', 'imageleft', 'imageright', 'imagecenter', 'emotion', 'scrawl', 'insertvideo', 'music', 'attachment', 'map', 'background', 'drafts'
						]
					]
					,
					'simple' => [
						[
							'fullscreen', 'preview', 'undo', 'redo', '|', 'fontsize', 'bold', 'italic', 'underline', 'fontborder', 'strikethrough', 'forecolor', 'backcolor',
							'link', '|', 'horizontal', 'spechars', 'emotion', '|', 'simpleupload', 'insertimage', 'attachment', 'removeformat', 'drafts'
						]
					],
					'two_line' => [
						['fullscreen', 'source', 'undo', 'redo'],
						['bold', 'italic', 'underline', 'fontborder', 'strikethrough', 'superscript', 'subscript', 'removeformat', 'formatmatch', 'autotypeset', 'blockquote', 'pasteplain', '|', 'forecolor', 'backcolor', 'insertorderedlist', 'insertunorderedlist', 'selectall', 'cleardoc']
					]
				];
			$default_config = [
				'manager' => url('file/Editor/manager', ['module' => $this->module]),
				'upload' => url('file/Editor/upload', ['module' => $this->module])
			];
			$config = array_merge($default_config, $config);
			if (isset($config['items'])) {
				if (!is_array($config['items']) && isset($items[$config['items']])) {
					$config['items'] = $items[$config['items']];
				}
			} else {
				$config['items'] = $items['all'];
			}
			$key = [
				'id_name' => uniqid(),
				'field' => $field,
				'title' => $title,
				'tips' => $tips,
				'type' => 'ueditor',
				'config' => $config,
				'style' => $style,
				'default' => $default,
			];
			$this->formItemBuilder()->keyList[] = $key; // 2026-09-06 拆分重构：改为写入FormItemBuilder
			// $this->_keyList[] = $key;
			return $this;
		}

		/**
		 * Markdown 所见即所得编辑器（Vditor）
		 * 普通用户友好：左侧所见即所得编辑，底层存储 Markdown，与 keyEditor 体验接近但更现代
		 * 图片批量/在线管理后续处理，此处仅引入编辑器本体
		 * http://b3log.org/vditor/
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string $default
		 * @param array $config
		 * @param array $style
		 * @return $this
		 */
		// [Buddy 2026-09-03] 新增：keyVditor —— 引入 Vditor 所见即所得 Markdown 编辑器
		public function keyVditor($field, $title, $tips = null, $default = '', $config = [], $style = ['width' => '900', 'height' => '400'])
		{
		$this->formItemBuilder()->keyVditor(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$default_config = [
		// 				'mode'   => 'wysiwyg', // 所见即所得，普通用户友好
		// 				'cdn'    => '/static/vditor',
		// 				'upload' => [
		// 					'url'          => '/file/vditor/upload',
		// 					'fieldName'    => 'file',
		// 					'linkToImgUrl' => '/file/vditor/link',
		// 					'accept'       => 'image/*',
		// 					'max'          => 10485760,
		// 					'multiple'     => true,
		// 					'handler'      => 'form-data',
		// 				],
		// 			];
		// 			$config = array_merge($default_config, $config);
		// 			$key = [
		// 				'id_name' => uniqid(),
		// 				'field'   => $field,
		// 				'title'   => $title,
		// 				'tips'    => $tips,
		// 				'type'    => 'vditor',
		// 				'config'  => $config,
		// 				'style'   => $style,
		// 				'default' => $default,
		// 			];
		// 			$this->_keyList[] = $key;
		// 			return $this;
		}

		/**
		 * 时间选择器
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $value
		 * @param string|null $min
		 * @param string|null $max
		 * @return $this
		 */
		public function keyTime($field, $title, $tips = null, $value = null, $min = '', $max = '')
		{
		$this->formItemBuilder()->keyTime(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->keyDate($field, $title, $tips, $value, $min, $max, 'time');
		}

		/**
		 * 时间选择器
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string|null $value
		 * @param string|null $min
		 * @param string|null $max
		 * @return $this
		 */
		public function keyDateTime($field, $title, $tips = null, $value = null, $min = '', $max = '')
		{
		$this->formItemBuilder()->keyDateTime(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->keyDate($field, $title, $tips, $value, $min, $max, 'datetime');
		}

		/**
		 * 日期选择器.
		 * @param $field
		 * @param $title
		 * @param $tips
		 * @param $default
		 * @param $min
		 * @param $max
		 * @param $type
		 * @param $range
		 * @param $done
		 * @return $this
		 */
		public function keyDate($field, $title, $tips = null, $default = null, $min = '', $max = '', $type = 'date', $range = false, $done = '')
		{
		$this->formItemBuilder()->keyDate(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$formats = [
		// 				'year' => 'yyyy',
		// 				'month' => 'MM',
		// 				'date' => 'MM-dd',
		// 				'time' => 'HH:mm:ss',
		// 				'datetime' => 'yyyy-MM-dd HH:mm:ss',
		// 			];
		// 			$opiton = [
		// 				'elem' => '#j_builder_' . (strpos($field, '|') ? md5($field) : $field),
		// 				'type' => $type,
		// 				'range' => $range,
		// 				'format ' => $formats[$type],
		// 				'mark ' => [],
		// 				'min' => $min,
		// 				'max' => $max,
		// 				'done' => $done
		// 			];
		// 			foreach ($opiton as $key => $item) {
		// 				if (!$item) {
		// 					unset($opiton[$key]);
		// 				}
		// 			}
		// 			return $this->key($field, $title, $tips, 'date', $opiton, $default, null, 50);
		}

		/**
		 * 时间区间
		 * @param $field
		 * @param $title
		 * @param $tips
		 * @param $default
		 * @param $min
		 * @param $max
		 * @return $this
		 */
		public function keyDateTimeRange($field, $title, $tips = null, $default = null, $min = '', $max = '')
		{
		$this->formItemBuilder()->keyDateTimeRange(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->keyDate($field, $title, $tips, $default, $min, $max, 'datetime', true);
		}

		/**
		 * 日期区间
		 * @param $field
		 * @param $title
		 * @param $tips
		 * @param $default
		 * @param $min
		 * @param $max
		 * @return $this
		 */
		public function keyDateRange($field, $title, $tips = null, $default = null, $min = '', $max = '')
		{
		$this->formItemBuilder()->keyDateRange(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			//            $default = '2020-05-01 - 2020-06-30';
		// 			if (is_null($default)) {
		// 				$default = time_format(time(), 'Y-m-d') . ' - ' . time_format('1 month', 'Y-m-d');
		// 			} else if (!is_null($default) && false === strpos($default, '-')) {
		// 				$default = time_format(time(), 'Y-m-d') . ' - ' . time_format($default, 'Y-m-d');
		// 			}
		// 			return $this->keyDate($field, $title, $tips, $default, $min, $max, 'date', true);
		}

		/**
		 * 时间区间
		 * @param $field
		 * @param $title
		 * @param $tips
		 * @param $default
		 * @param $min
		 * @param $max
		 * @return $this
		 */
		public function keyTimeRange($field, $title, $tips = null, $default = null, $min = '', $max = '')
		{
		$this->formItemBuilder()->keyTimeRange(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->keyDate($field, $title, $tips, $default, $min, $max, 'time', true);
		}

		/**
		 * 仅展示一张图片
		 * @param string $field
		 * @param string $title
		 * @param string $tips
		 * @return $this
		 */
		public function keyShowImg($field, $title, $tips = '点击图片即可下载')
		{
		$this->formItemBuilder()->keyShowImg(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			return $this->key($field, $title, $tips, 'showImg');
		}

		/**
		 * 上传单个音频
		 * @param string $field
		 * @param string $title
		 * @param string|null $remark
		 * @param int $size
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyVoice($field, $title, $remark = null, $size = 50, $verify = null)
		{
		$this->formItemBuilder()->keyVoice(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$extensions = 'mp3,wav,wma,amr';
		// 			return $this->key($field, $title, $remark, 'attachment', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions, 'size' => $size], 30, $verify);
		}

		/**
		 * 上传单个视频
		 * @param string $field
		 * @param string $title
		 * @param string|null $remark
		 * @param int $size
		 * @param string|null $verify
		 * @return $this
		 */
		public function keyVideo($field, $title, $remark = null, $size = 300, $verify = null)
		{
		$this->formItemBuilder()->keyVideo(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$extensions = 'mp4,avi,rmvb,rm,wmv';
		// 			return $this->key($field, $title, $remark, 'attachment', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions, 'size' => $size], 30, $verify);
		}

		/**
		 * 单图片上传，不关联模型
		 * @param string $field 需要保存的字段，为URL地址，且必须是以_url结尾的字符串
		 * @param string $title
		 * @param string $button
		 * @param string|null $default
		 * @param string|null $tips
		 * @param string|null $verify
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyImage($field, $title, $tips = null, $button = '上传单个图片', $default = null, $verify = null)
		{
		$this->formItemBuilder()->keyImage(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$max_size = 0;
		// 			$exts = '';
		// 			$mimes = '';
		// 			return $this->key($field, $title, $tips, 'image',
		// 				['button' => $button, 'limit' => 1, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
		// 				, $default, $verify);
		}


		/**
		 * 单图片上传，关联模型
		 * @param string $field
		 * @param string $title
		 * @param string $button
		 * @param string|null $default
		 * @param string|null $tips
		 * @param string|null $verify
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyImageModel($field, $title, $tips = null, $button = '上传单个图片', $default = null, $verify = null)
		{
		$this->formItemBuilder()->keyImageModel(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$max_size = 0;
		// 			$exts = '';
		// 			$mimes = '';
		// 			return $this->key($field, $title, $tips, 'image_model',
		// 				['button' => $button, 'limit' => 1, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
		// 				, $default, $verify);
		}

		/**
		 * 多图片上传,不关联模型
		 * @param string $field
		 * @param string $title
		 * @param string|null $default
		 * @param string|null $tips
		 * @param string|null $verify
		 * @param int $limit
		 * @return $this
		 */
		public function keyImageMultiple($field, $title, $tips = null, $default = null, $limit = 5, $verify = null)
		{
		$this->formItemBuilder()->keyImageMultiple(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		//
		// 			$max_size = 0;
		// 			$exts = '';
		// 			$mimes = '';
		// 			return $this->key($field, $title, $tips, 'ImageMultiple',
		// 				['limit' => $limit, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
		// 				, $default, $verify);
		}

		/**
		 * 多图片上传，关联模型
		 * @param string $field 需要保存的字段，为URL地址，且必须是以_url结尾的字符串
		 * @param string $title
		 * @param string|null $default
		 * @param string|null $tips
		 * @param string|null $verify
		 * @param int $limit
		 * @return $this
		 */
		public function keyImageMultipleModel($field, $title, $tips = null, $default = null, $limit = 5, $verify = null)
		{
		$this->formItemBuilder()->keyImageMultipleModel(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		//
		// 			$max_size = 0;
		// 			$exts = '';
		// 			$mimes = '';
		// 			return $this->key($field, $title, $tips, 'ImageMultiple',
		// 				['limit' => $limit, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
		// 				, $default, $verify);
		}

		/**
		 * 多图片展示
		 * @param string $field 需要保存的字段，为URL地址，且必须是以_url结尾的字符串
		 * @param string $title
		 * @param null $tips
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyImageShowMultiple($field, $title, $tips = null)
		{
		$this->formItemBuilder()->keyImageShowMultiple(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$max_size = 0;
		// 			$exts = '';
		// 			$mimes = '';
		// 			return $this->key($field, $title, $tips, 'ImageShowMultiple',
		// 				['limit' => 5, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
		// 				, null, null);
		}

		/**
		 * 实名认证
		 * @param string $title
		 * @param string|null $tips
		 * @param int $need_hand
		 * @return $this
		 */
		public function keyAuth($title = '实名认证', $tips = null, $need_hand = 1)
		{
		$this->formItemBuilder()->keyAuth(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$options['need_hand'] = $need_hand ?: 0;
		// 			return $this->key('_auth', $title, $tips, 'auth', $options);
		}

		/**
		 * 上传单个附件
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param int $default
		 * @param string|null $verify
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyAttachment($field, $title, $tips = null, $default = 0, $verify = null)
		{
		$this->formItemBuilder()->keyAttachment(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$extensions = '*';
		// 			$remark = '';
		// 			return $this->key($field, $title, $tips, 'attachment', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions], $default, $verify);
		}

        /**
         * 上传单个附件
         * @param string $field
         * @param string $title
         * @param string|null $tips
         * @param int $default
         * @param string|null $verify
         * @return $this
         * @author  : 微尘 <yicmf@qq.com>
         */
        public function keyAttachmentModel($field, $title, $tips = null, $default = 0, $verify = null)
        {
		$this->formItemBuilder()->keyAttachmentModel(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		//             $extensions = '*';
		//             $remark = '';
		//             return $this->key($field, $title, $tips, 'attachment_model', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions], $default, $verify);
        }

        /**
		 * 上传多个附件
		 * @param      $field
		 * @param      $title
		 * @param string|null $tips
		 * @param string|null $verify
		 * @param int $limit
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyAttachmentMultiple($field, $title, $tips = null, $limit = 5, $verify = null)
		{
		$this->formItemBuilder()->keyAttachmentMultiple(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$extensions = '*';
		// 			$remark = '';
		// 			return $this->key($field, $title, $tips, 'attachmentMultiple', ['remark' => $remark, 'limit' => $limit, 'extensions' => $extensions], 0, $verify);
		}

		/**
		 * 添加城市选择（需安装城市联动插件）
		 * @param      $field
		 * @param      $title
		 * @param string|null $tips
		 * @param int $default
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keyCity($field, $title, $tips = null, $default = 110101)
		{
		$this->formItemBuilder()->keyCity(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			// 修正在编辑信息时无法正常显示已经保存的地区信息
		// 			return $this->key($field, $title, $tips, 'city', null, $default, null);
		}

		/**
		 * 批量添加字段信息.
		 * @param array $fields
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function setKeys($fields = [])
		{
		$this->formItemBuilder()->setKeys(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			$this->_keyList = empty($this->_keyList) ? $fields : array_merge($this->_keyList, $fields);
		// 			return $this;
		}

		/**
		 * 表单参数组合
		 * @param string $field
		 * @param string $title
		 * @param string|null $tips
		 * @param string $type
		 * @param array $options
		 * @param string|null $default
		 * @param string|null $verify
		 * @param string|null $placeholder
		 * @param string|null $disabled
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		protected function key($field, $title, $tips, $type, $options = null, $default = '', $verify = null, $size = null, $disabled = null, $placeholder = '')
		{
		$this->formItemBuilder()->key(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 		if (is_array($verify)) {
		// 			$verify = implode('|', $verify);
		// 		}
		// 		// [Buddy 2026-09-02] 调整：null 安全 + 修正 strpos 位置 0 被判为 falsy 的逻辑缺陷
		// 		if (is_string($verify) && strpos($verify, ',') !== false) {
		// 			$verify = str_replace(',', '|', $verify);
		// 		}
		// 		if (is_string($verify) && false !== strpos($verify, 'require')) {
		// 				$verify = str_replace('require', 'required', $verify);
		// 			}
		// 			$key = [
		// 				'field' => $field,
		// 				'title' => $title,
		// 				'tips' => (string)$tips,
		// 				'type' => $type,
		// 				'default' => $default,
		// 				'disabled' => $disabled,
		// 				'placeholder' => $placeholder,
		// 				'size' => $size,
		// 				'verify' => $verify,
		// 				'options' => $options,
		// 			];
		// 			$this->_keyList[] = $key;
		// 			return $this;
		}

		/**
		 * 批量配置key
		 * @param array $keyList
		 * @return $this
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function keys($keyList)
		{
		$this->formItemBuilder()->keys(...func_get_args());
		// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身
		return $this;
		// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留
		//
		// 			foreach ($keyList as $index => $item) {
		// 				$this->key($item['field']
		// 					, isset($item['title']) ? $item['title'] : ''
		// 					, isset($item['tips']) ? $item['tips'] : ''
		// 					, $item['type']
		// 					, isset($item['options']) ? $item['options'] : null
		// 					, isset($item['default']) ? $item['default'] : ''
		// 					, isset($item['verify']) ? $item['verify'] : null
		// 					, isset($item['size']) ? $item['size'] : 30
		// 					, isset($item['disabled']) ? $item['disabled'] : null
		// 				);
		// 			}
		// 			return $this;
		}


		/**
		 * 提交按钮.
		 * @param string|null $url 提交的url地址，默认当前页
		 * @param string $title
		 * @return $this
		 */
		public function buttonSubmit($url = null, $title = '保存')
		{

			if (is_null($url)) {
				$url = url();
			} else {
				$url = url($url);
			}
			if (strpos($url, '/Admin')) {
				$url = str_replace('/Admin', '/admin', $url);
			}
			$attr = [
				'class' => 'layui-btn',
				'lay-filter' => "LAY-app-workorder-submit",
				'lay-submit' => '',
			];
			$this->_form_buttons[] = ['attr' => $attr, 'url' => $url, 'type' => 'submit', 'icon' => 'save', 'title' => $title];
			return $this;
		}

		/**
		 * 提交按钮.
		 * @param string|null $url 提交的url地址，默认当前页
		 * @param string $title
		 * @return $this
		 */
		public function button($url = null, $title = '保存')
		{
			$attr = [
				'class' => 'layui-btn',
				'lay-filter' => "LAY-app-workorder-submit",
				'lay-submit' => '',
			];
			$this->_form_buttons[] = ['attr' => $attr, 'url' => $url, 'type' => 'button', 'icon' => 'save', 'title' => $title];
			return $this;
		}

		/**
		 * 普通按钮.
		 * @param string|null $click
		 * @param string $title
		 * @return $this
		 */
		public function buttonClick($click = null, $title = '保存')
		{
			$attr = [
				'class' => 'layui-btn',
				'lay-filter' => "LAY-app-workorder-submit",
				'lay-submit' => '',
			];
			$this->_form_buttons[] = ['attr' => $attr, 'url' => '', 'click' => $click, 'type' => 'button', 'icon' => 'save', 'title' => $title];
			return $this;
		}

		/**
		 * 重置按钮
		 * @param string $title
		 * @return $this
		 */
		public function buttonReset($title = '重新填写')
		{
			$attr = [
				'class' => 'layui-btn',
			];
			$this->_form_buttons[] = ['attr' => $attr, 'type' => 'reset', 'icon' => 'close', 'title' => $title];
			return $this;
		}

		/**
		 * 关闭按钮.
		 * @param string $title
		 * @return $this
		 */
		public function buttonClose($title = '关闭')
		{
			$attr = [
				'class' => 'layui-btn',
			];
			$this->_form_buttons[] = ['attr' => $attr, 'type' => 'close', 'icon' => 'close', 'title' => $title];
			return $this;
		}

		/**
		 * 当前表单值
		 * @param array $data
		 * @return $this
		 */
		public function data($data = [])
		{
			$this->_data = $data;
			return $this;
		}

		/**
		 *  TODO: 内置验证共用
		 * @access protected
		 * @param string|array $validate 验证器名或者验证规则数组
		 * @param array $message 提示信息
		 * @param bool $batch 是否批量验证
		 * @param mixed $callback 回调方法（闭包）
		 * @return mixed
		 */
		public function validate($validate, $message = [], $batch = false, $callback = null)
		{
			if (is_array($validate)) {
				$v = $this->app->validate();
				$v->rule($validate);
			} else {
				if (strpos($validate, '.')) {
					// 支持场景
					list($validate, $scene) = explode('.', $validate);
				}
				$v = $this->app->validate($validate);
				if (!empty($scene)) {
					$v->scene($scene);
				}
			}
			//TODO: 是否批量验证
			if ($batch || $this->batchValidate) {
				$v->batch(true);
			}
			if (is_array($message)) {
				$v->message($message);
			}
			if ($callback && is_callable($callback)) {
				call_user_func_array($callback, [$v, &$data]);
			}
			//            dump($v->getRule());
			//            dump($v->getRegex());
			//            if (!$v->check($data)) {
			//                if ($this->failException) {
			//                    throw new ValidateException($v->getError());
			//                }
			//                return $v->getError();
			//            }
			return $this;
		}


		/**
		 * 插入配置分组.
		 * @param string $field 组名
		 * @param array|string $list 组内字段列表
		 * @return $this
		 */
		public function group($field, $list = [])
		{
			if (is_array($field)) {
				$this->_group = array_merge($this->_group, $field);
			} else {
				!is_array($list) && $list = explode(',', $list);
				$this->_group[$field] = $list;
			}
			return $this;
		}

		/**
		 * 解析加载模版输出
		 * @param string $template
		 * @param array $vars
		 * @return string
		 * @author  : 微尘 <yicmf@qq.com>
		 */
		public function fetch($template = '', $vars = [])
		{
			if ($this->request->has('ajax')) {
				$field = $this->request->param('ajax');
				foreach ($this->formItemBuilder()->getKeyList() as $item) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取
				// foreach ($this->_keyList as $item) {
					if ($item['field'] == $field) {
						$key = $item;
						break;
					}
				}

				if ($key['options'] instanceof \Closure) {
					// 闭包
					$result = $key['options']($this->_data, $key);
				} else {
					$result = $key['options'];
				}
				return json($result);
			} else {
				// 将数据融入到key中
				$this->_formatData();
				// 配置触发
				$this->_formatTrigger();
				// 显示页面
				$this->assign('group', $this->_group);
				// 查询当前菜单
				$menu = Db::name('menu')->where('status', 1)
					->where('action', $this->request->action())
					->where('controller', $this->request->controller())
					->where('module', $this->module)
					->find();
				if ($menu) {

					if ('' === $this->_title) {
						$this->_title = $menu['title'];
					}
					if ($menu['group']) {
						$this->assign('menu_group_title', $menu['group']);
					}
					if ($menu['pid']) {
						$p_menu = MenuModel::where('status', 1)
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
				// 显示页面
				if (false !== $this->_title) {
					$this->assign('menu_title', $this->_title);
				}

				$this->assign('templets', $this->_templets);
				if ($this->request->has('_namespace_filter', 'get')) {
					$this->assign('filter', $this->request->get('_namespace_filter'));
					$this->assign('auto_builder', 1);
				} else {
					$this->assign('filter', $this->module . '-' . $this->request->controller() . '-' . $this->request->action());
				}
				if (count($this->formItemBuilder()->getKeyList())) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取
				// if (count($this->_keyList)) {
					$this->assign('keyList', $this->formItemBuilder()->getKeyList()); // 2026-09-06 拆分重构：改为从FormItemBuilder读取
					// $this->assign('keyList', $this->_keyList);
				}
				$this->assign('form_buttons', $this->_form_buttons);
				// 在有赋值的情况展示
				if (count($this->_explaints) > 0) {
					$this->assign('explaints', $this->_explaints);
				}
				$this->assign('triggers', $this->_triggers);
				$this->assign('uniqid', uniqid());
				$this->assign('reload', $this->_reload);
				$this->assign('mask', $this->_mask);
				$dialog_index = $this->request->get('_dialog_index', 0);
				$this->assign('dialog_index', $dialog_index);
				$this->assign('name_space', $this->_namespace);
				// 2026-09-06 方案D：edit/dialog 合并后，_form.html 内的 JS 行为差异（req.reload 重载父表格）
				// 由 form_mode 区分：dialog=弹窗模式（激活），page=页面模式（与原 edit.html 注释态一致）
				if (!empty($template) || (empty($template) && $dialog_index != 0)) {
					$this->assign('form_mode', 'dialog');
					return parent::_fetch('dialog', $vars);
				} else {
					$this->assign('form_mode', 'page');
					return parent::_fetch('edit', $vars);
				}
			}

		}

		/**
		 * 规范触发数据格式.
		 */
		private function _formatTrigger()
		{
			//TODO:考虑在没有配置当前字段默认值的情况，目前设计为不显示
			$triggers = $this->_triggers;
			$this->_triggers = [];
			foreach ($triggers as $field => $trigger) {
				$field_key = '';
				foreach ($this->formItemBuilder()->getKeyList() as $data) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取
				// foreach ($this->_keyList as $data) {
					if ($field == $data['field']) {
						$field_key = $data;
						break;
					}
				}
				$tpl = '';
				if ($field_key['type'] == 'checkbox') {
					$trigger = $this->_formatCheckboxTrigger($field_key, $trigger, $tpl);
				} else {
					$trigger = $this->_formatRadioTrigger($field_key, $trigger, $tpl);
				}
				$this->_triggers[] = [
					'field' => $field_key,
					'data' => $trigger,
					'tpl' => $tpl,
				];
			}
		}


		/**
		 * 规范触发数据格式.
		 */
		private function _formatCheckboxTrigger($field_key, $trigger, &$tpl)
		{

			$new_trigger = [];
			$fields = [];
			foreach ($trigger as $trigger_detail) {
				$shows = explode(',', $trigger_detail['show']);
				$fields = array_unique(array_merge($shows, $fields));

				if ($trigger_detail['tpl']) {
					$tpl = $trigger_detail['tpl'];
				}
			}

			foreach ($fields as $field) {

				$value = [];
				foreach ($trigger as $trigger_detail) {
					$shows = explode(',', $trigger_detail['show']);
					if (in_array($field, $shows)) {
						$value[] = $trigger_detail['value'];
					}
				}
				$show = 0;
				foreach ($value as $item) {
					if (in_array($item, $field_key['value'])) {
						$show = 1;
						break;
					}
				}

				$new_trigger[] = [
					'field' => $field,
					'jquery' => '#' . $this->_jquery_md5($field),
					'value' => $value,
					'is_show' => $show,
				];
			}
			return $new_trigger;
		}


		/**
		 * 规范触发数据格式.
		 */
		private function _formatRadioTrigger($field_key, $trigger, &$tpl)
		{

			$fields = [];
			foreach ($trigger as $key => $trigger_detail) {
				$shows = explode(',', $trigger_detail['show']);
				$fields = array_merge($shows, $fields);
			}
			foreach ($trigger as $key => $trigger_detail) {
				$jquery_show = [];
				$jquery_hide = [];
				$shows = explode(',', $trigger_detail['show']);
				foreach ($fields as $_field) {
					if (in_array($_field, $shows)) {
						$jquery_show[] = '#' . $this->_jquery_md5($_field);
					} else {
						$jquery_hide[] = '#' . $this->_jquery_md5($_field);
					}
				}
				$trigger[$key]['value'] = $trigger_detail['value'];
				$trigger[$key]['show'] = json_encode($shows);
				$trigger[$key]['jquery_show'] = implode(',', array_unique($jquery_show));
				$trigger[$key]['jquery_hide'] = implode(',', array_unique($jquery_hide));
				if ($trigger_detail['value'] == $field_key['value']) {
					$trigger[$key]['is_show'] = 1;
				} else {
					$trigger[$key]['is_show'] = 0;
				}
				if ($trigger_detail['tpl']) {
					$tpl = $trigger_detail['tpl'];
				}
			}
			return $trigger;
		}


		/**
		 * 数据格式统一
		 * @param $field
		 * @return string
		 */
		private function _jquery_md5($field)
		{
			//            dump($field);
			//            if (strpos($field, '.')) {
			//                $field = md5(json_encode(explode('.', $field)));
			//            } elseif (strpos($field, '|')) {
			//                $field = md5(json_encode(explode('|', $field)));
			//            } else {
			$field = md5(is_array($field) ? implode(',', $field) : $field);
			//            }
			//            dump($field);
			return 'trigger_' . md5($this->_namespace) . '_' . $field;
		}

		/**
		 * 输入数据格式化.
		 */
		private function _formatData()
		{
			if ('id' !== $this->formItemBuilder()->getDefaultPk() && is_object($this->_data)) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取
			// if ('id' !== $this->_default_pk && is_object($this->_data)) {
				$pk = $this->_data->getPk();
			} else {
				$pk = $this->formItemBuilder()->getDefaultPk(); // 2026-09-06 拆分重构：改为从FormItemBuilder读取
				// $pk = $this->_default_pk;
			}
			$flag = false;
//			foreach ($this->_keyList as $key => $e) {
//				if (!isset($this->_data[$e['field']])) {
//					$this->_data[$e['field']] = $e['default'];
//				}
//			}
			$keyList = &$this->formItemBuilder()->getKeyListRef();
			foreach ($keyList as $key => $e) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取
			// foreach ($this->_keyList as $key => $e) {
				$pk == $e['field'] && $flag = true;
				$e['data'] = $this->_data;
				$e['jquery_id'] = $this->_jquery_md5($e['field']);
				// 先给默认值
				if (is_string($e['field']) && !isset($this->_data[$e['field']])) {
					$e['value'] = $e['default'];
				}
				if ($e['type'] instanceof \Closure) {
					// 闭包
					$e['value'] = $e['type']($this->_data, $e);
					if (preg_match('/(.*)\[:(.*)\]/', $e['field'], $matches)) {
						$e['field'] = $matches[1];
						$e['type'] = $matches[2];
					} else {
						$e['type'] = 'closure';
					}
				} elseif (is_array($e['field'])) {
					$i = 0;
					$n = count($e['field']);
					while ($n > 0) {
						$e['value'][$i] = isset($this->_data[$e['field'][$i]]) ? $this->_data[$e['field'][$i]] : (isset($e['value'][$i]) ? $$e['value'][$i] : 0); //
						$i++;
						$n--;
					}
				} elseif (strpos($e['field'], '|')) { // 使用‘|’代表同级字段
//                    if (isset($e['value'])) {
//                        $e['value'] = explode('|', $e['field']);
//                    }
					$field_value = $e['field'];
					$e['field'] = explode('|', $e['field']);
					if (!isset($this->_data[$e['field'][0]]) && isset($this->_data[$field_value])) {
						$e['value'] = explode('|', $this->_data[$field_value]);
					} else {
						foreach ($e['field'] as $t_key => $t_value) {
							$e['value'][$t_key] = isset($this->_data[$t_value]) ? $this->_data[$t_value] : (isset($e['default'][$t_key]) ? $e['default'][$t_key] : '');
						} 
					}
				}  elseif (strpos($e['field'], '.')) { // 支持点语法
                    $temp = explode('.', $e['field']);
                    $e['relation']['parent'] = $temp[0];
                    $e['relation']['child'] = $temp[1];
                    if (!empty($this->_data[$temp[0]][$temp[1]]))
                    {
                        $e['value'] = $this->app['view']->display('{$data.' . $temp[0] . '?$data.' . $e['field'] . ':\'\'}', ['data' => $this->_data]);
                    }
                    $e['field'] = $temp[0] . '[' . $temp[1] . ']';
//					$e['value'] = isset($this->_data[$temp[0]][$temp[1]]) ? $this->_data[$temp[0]][$temp[1]] : (isset($e['value']) ? $e['value'] : '');
                }else {
					$e['value'] = isset($this->_data[$e['field']]) ? $this->_data[$e['field']] : (isset($e['value']) ? $e['value'] : '');
				}
                if ($e['type'] == 'belongTo')
                {
                    $e['options']['show_field_value'] = $this->app['view']->display('{$data.' . $e['options']['model_name'] . '?$data.' . $e['options']['model_name'].'.' . $e['options']['show_field'] . ':\'\'}', ['data' => $this->_data]);;

                }
//                dump($e);

//                if (false !== strpos($key['field'], '{$')) {
//                    $value = $this->app['view']->display($key['field'], ['data' => $data]);
//                } elseif (false === strpos($key['field'], '{$') && strpos($key['field'], '.')) {
//                    $field = explode('.', $key['field']);
//                    $conver_data[$field[0]][$field[1]] = $data[$field[0]] ? $data[$field[0]][$field[1]] : '';
//                } else {
//                    $conver_data[$key['field']] = $data[$key['field']];
//                }
//
				$keyList[$key] = $e; // 2026-09-06 拆分重构：改为写入FormItemBuilder
				// $this->_keyList[$key] = $e;
			}
//            dump($this->_keyList);
			if (!$flag && isset($this->_data[$pk])) {
				//自动增加隐藏表单用于编辑;
				$edit['field'] = $pk;
				$edit['type'] = 'hidden';
				$edit['value'] = $this->_data[$pk];
				$keyList[] = $edit; // 2026-09-06 拆分重构：改为写入FormItemBuilder
				// $this->_keyList[] = $edit;
			}
		}

	}
