<?php

	// +----------------------------------------------------------------------
	// | builder
	// +----------------------------------------------------------------------
	// | Copyright (c) 2015-2024 http://www.yicmf.com, All rights reserved.
	// +----------------------------------------------------------------------
	// | Author: 微尘 <yicmf@qq.com>
	// +----------------------------------------------------------------------

	namespace yicmf\builder;

	use think\App;
	use think\Container;
	use think\facade\Lang;
	use think\facade\Config;
	use think\facade\View;

	abstract class Builder
	{
		/**
		 * 应用实例
		 * @var \think\App
		 */
		protected $app;

		/**
		 * 视图类实例
		 * @var \think\View
		 */
		protected $view;

		/**
		 * Request实例
		 * @var \think\Request
		 */
		protected $request;

		/** 弹窗默认宽度 */
		protected $dialog_width_default = 1200;

		/** 弹窗默认高度 */
		protected $dialog_height_default = 700;

		/** 展示方式，默认弹窗（dialog） */
		protected $toggle = 'dialog';

		/** 当前模块名 */
		protected $module = '';
		/**
		 * 视图文件路径
		 * @var string
		 */
		protected $view_data = [];

		/**
		 * 自定义模板根目录（2026-09-06 模板驱动：来自 builder.view_path 配置）
		 * 支持绝对路径或相对项目根目录；空字符串表示使用扩展内置 tpl/。
		 * 目录结构需与扩展 tpl/ 一致，未提供的模板回落扩展内置实现。
		 * @var string
		 */
		protected $viewPath = '';

		/**
		 * 构造方法
		 * @access public
		 * @author 微尘 <yicmf@qq.com>
		 */
		public function __construct()
		{
			$this->app = app();
			$this->request = $this->app['request'];
			$this->module = app('http')->getName();
			$this->assign('module', $this->module);
			// 控制器初始化
			$this->initialize();
			// 增加配置
			if (Config::get('builder.toggle')) {
				$this->toggle = Config::get('builder.toggle');
			}
			if (Config::get('builder.dialog_height')) {
				$this->dialog_height_default = Config::get('builder.dialog_height');
			}
			if (Config::get('builder.dialog_width')) {
				$this->dialog_width_default = Config::get('builder.dialog_width');
			}
			// 2026-09-06 模板驱动：读取自定义模板根目录（绝对路径或相对项目根目录），空则使用扩展内置 tpl/
			$viewPathConfig = (string) Config::get('builder.view_path');
			if ('' !== $viewPathConfig) {
				if (preg_match('#^([a-zA-Z]:)?[/\\\\]#', $viewPathConfig)) {
					$this->viewPath = $viewPathConfig;
				} else {
					$this->viewPath = rtrim($this->app->getRootPath(), '/\\') . DIRECTORY_SEPARATOR . ltrim($viewPathConfig, '/\\');
				}
			}
			//         $this->dialog_height_default = $config['height'];
			//         $this->dialog_width_default = $config['width'];
			// 加载builder语言包
			$langSet = $this->app->lang->getLangSet();
			$lang_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $langSet . '.php';
			is_file($lang_file) && $this->app->lang->load($lang_file);
		}

		/**
		 * 初始化方法，供子类覆写
		 * @access protected
		 */
		// 初始化
		protected function initialize(){}

		/**
		 * 模板路径解析（2026-09-06 模板驱动）
		 * 自定义模板根目录（builder.view_path）命中时优先使用，未命中回落扩展内置 tpl/
		 * 目录结构需与扩展 tpl/ 一致，可整体或部分覆盖，用于接入不同前端 UI
		 * @param string $template 模板文件名（不含扩展名）
		 * @return string 模板文件绝对路径
		 */
		protected function templatePath($template)
		{
			$name = $template . '.html';
			if ('' !== $this->viewPath) {
				$custom = rtrim($this->viewPath, '/\\') . DIRECTORY_SEPARATOR . $name;
				if (is_file($custom)) {
					return $custom;
				}
			}
			return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . $name;
		}

		/**
		 * 注入 _key.html 分发器所需的各字段类型子模板路径（2026-09-06 模板拆分）
		 * 变量名 key_tpl_<type>，编译期内联使用；
		 * 自定义主题目录 key/ 下的同名子文件优先，未提供的类型回落扩展内置 tpl/key/
		 */
		protected function assignKeyTemplates()
		{
			$builtinDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'key';
			$customDir = '' !== $this->viewPath ? rtrim($this->viewPath, '/\\') . DIRECTORY_SEPARATOR . 'key' : '';
			$files = [];
			if ('' !== $customDir && is_dir($customDir)) {
				foreach (glob($customDir . DIRECTORY_SEPARATOR . '*.html') ?: [] as $file) {
					$files[basename($file, '.html')] = $file;
				}
			}
			foreach (glob($builtinDir . DIRECTORY_SEPARATOR . '*.html') ?: [] as $file) {
				$type = basename($file, '.html');
				if (!isset($files[$type])) {
					$files[$type] = $file;
				}
			}
			foreach ($files as $type => $path) {
				$this->assign('key_tpl_' . $type, $path);
			}
		}

		/**
		 * 加载模板输出
		 * @access  protected
		 * @param string $template 模板文件名
		 * @param array $vars 模板输出变量
		 * @return string
		 * @author 微尘 <yicmf@qq.com>
		 */
		protected function _fetch($template = '', $vars = [])
		{
			// 获取模版的名称
			// 2026-09-06 模板驱动：模板路径统一经 templatePath() 解析，支持 builder.view_path 自定义模板根目录
			$this->assign('key_path', $this->templatePath('_key'));
			// $this->assign('key_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_key.html');
			$this->assign('search_path', $this->templatePath('_search'));
			// $this->assign('search_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_search.html');
			// 2026-09-06 方案D：edit/dialog 合并后的公共表单主体路径（edit.html/dialog.html 壳经 $form_path 引入）
			$this->assign('form_path', $this->templatePath('_form'));
			// 2026-09-06 方案A死资源清理：edit_path/dialog_path 自始指向不存在的 _edit.html/_dialog.html，
			// 且全部模板（edit/dialog/table 等）均未引用这两个变量，确认为死赋值，注释保留
			// $this->assign('edit_path', $this->templatePath('_edit'));
			// $this->assign('edit_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_edit.html');
			// $this->assign('dialog_path', $this->templatePath('_dialog'));
			// $this->assign('dialog_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_dialog.html');
			// 2026-09-06 模板拆分：注入 _key.html 分发器所需的 key_tpl_<type> 子模板路径变量
			$this->assignKeyTemplates();
			$template = $this->templatePath($template);
			// $template = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . $template . '.html';
			return View::fetch($template, array_merge($this->view_data, $vars));
		}


		/**
		 * 渲染内容输出
		 * @access protected
		 * @param string $content 模板内容
		 * @param array $vars 模板输出变量
		 * @return mixed
		 * @author 微尘 <yicmf@qq.com>
		 */
		protected function display($content = '', $vars = [])
		{
			return \think\facade\View::display($content, $vars);
		}

		/**
		 * 模板变量赋值
		 * @access protected
		 * @param mixed $name 要显示的模板变量
		 * @param mixed $value 变量的值
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		protected function assign($name, $value = '')
		{
			if (is_array($name)) {
				$this->view_data = array_merge($this->view_data, $name);
			} else {
				$this->view_data[$name] = $value;
			}
			return $this;
		}

		/**
		 * 视图过滤
		 * @access protected
		 * @param Callable $filter 过滤方法或闭包
		 * @return $this
		 * @author 微尘 <yicmf@qq.com>
		 */
		protected function filter($filter)
		{
			$this->view->filter($filter);
			return $this;
		}

		/**
		 * 数组转html.
		 * @param array $attr
		 * @param string $prefix
		 * @author 微尘 <yicmf@qq.com>
		 */
		protected function _compileHtmlAttr($attr, $prefix = null)
		{
			$result = [];
			foreach ($attr as $key => $value) {
				$value = htmlspecialchars((string)$value);
				if (strlen($value) > 0) {
					$result[] = (is_null($prefix) ? '' : $prefix) . "$key=\"$value\"";
				}
			}
			return implode(' ', $result);
		}

		/**
		 * 将属性数组编译为html属性字符串（已做htmlspecialchars转义）
		 * @access protected
		 * @param array $attr 属性数组
		 * @return string
		 */
		protected function compileHtmlAttr($attr)
		{
			return $this->_compileHtmlAttr($attr);
			// 2026-09-06 XSS修复：原实现未做htmlspecialchars转义，改为委托_compileHtmlAttr，原代码注释保留
			// $result = [];
			// foreach ($attr as $key => $value) {
			//     $result[] = $key . ' = "' . $value . '"';
			// }
			// return implode(' ', $result);
		}
	}
