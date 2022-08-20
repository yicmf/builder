<?php

	// +----------------------------------------------------------------------
	// | builder
	// +----------------------------------------------------------------------
	// | Copyright (c) 2015-2022 http://www.yicmf.com, All rights reserved.
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

		protected $dialog_width_default = 1200;

		protected $dialog_height_default = 700;

		protected $toggle = 'dialog';
		protected $module = '';
		/**
		 * 视图文件路径
		 * @var string
		 */
		protected $view_data = [];

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
			//         $this->dialog_height_default = $config['height'];
			//         $this->dialog_width_default = $config['width'];
			// 加载builder语言包
			$langSet = $this->app->lang->getLangSet();
			$lang_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $langSet . '.php';
			is_file($lang_file) && $this->app->lang->load($lang_file);
		}

		// 初始化
		protected function initialize(){}

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
			$this->assign('key_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_key.html');
			$this->assign('search_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_search.html');
			$this->assign('edit_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_edit.html');
			$this->assign('dialog_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_dialog.html');
			$template = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . $template . '.html';
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
				$value = htmlspecialchars($value);
				if (strlen($value) > 0) {
					$result[] = (is_null($prefix) ? '' : $prefix) . "$key=\"$value\"";
				}
			}
			return implode(' ', $result);
		}

		protected function compileHtmlAttr($attr)
		{
			$result = [];
			foreach ($attr as $key => $value) {
				//            $value = htmlspecialchars($value);
				$result[] = $key . ' = "' . $value . '"';
			}
			return implode(' ', $result);
		}
	}
