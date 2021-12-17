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
        /**
         * 构造方法
         * @access public
         * Builder constructor.
         * @param App|null $app
         */
        public function __construct()
        {
            $this->app = Container::get('app');
            $this->request = $this->app['request'];
            $this->view = $this->app['view'];
//            Config::set('exception_tmpl',__DIR__.DIRECTORY_SEPARATOR.'tpl'.DIRECTORY_SEPARATOR.'exception.html');
            // 控制器初始化
            $this->initialize();
            // 增加配置
            if (Config::get('builder.toggle'))
            {
                $this->toggle = Config::get('builder.toggle');
            }
            if (Config::get('builder.dialog_height'))
            {
                $this->dialog_height_default = Config::get('builder.dialog_height');
            }
            if (Config::get('builder.dialog_width'))
            {
                $this->dialog_width_default = Config::get('builder.dialog_width');
            }
            //         $this->dialog_height_default = $config['height'];
            //         $this->dialog_width_default = $config['width'];
            // 加载builder语言包
            Lang::load(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php');
        }

        // 初始化
        protected function initialize()
        {
        }

        /**
         * 加载模板输出
         * @access  protected
         * @param  string $template 模板文件名
         * @param  array  $vars     模板输出变量
         * @param  array  $config   模板参数
         * @return string
         * @throws \Exception
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/3/14 18:22
         */
        protected function _fetch($template = '', $vars = [], $config = [])
        {
            // 获取模版的名称
            $this->assign('key_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_key.html');
            $this->assign('search_path', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_search.html');
            $template_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . $template . '.html';
            return $this->view->fetch($template_file, $vars, $config);
        }


        /**
         * 渲染内容输出
         * @access protected
         * @param  string $content 模板内容
         * @param  array  $vars    模板输出变量
         * @param  array  $config  模板参数
         * @return mixed
         */
        protected function display($content = '', $vars = [], $config = [])
        {
            return $this->view->display($content, $vars, $config);
        }

        /**
         * 模板变量赋值
         * @access protected
         * @param  mixed $name  要显示的模板变量
         * @param  mixed $value 变量的值
         * @return $this
         */
        protected function assign($name, $value = '')
        {
            $this->view->assign($name, $value);

            return $this;
        }

        /**
         * 视图过滤
         * @access protected
         * @param  Callable $filter 过滤方法或闭包
         * @return $this
         */
        protected function filter($filter)
        {
            $this->view->filter($filter);

            return $this;
        }

        /**
         * 数组转html.
         * @param array  $attr
         * @param string $prefix
         */
        protected function _compileHtmlAttr($attr, $prefix = null)
        {
            $result = [];
            foreach ( $attr as $key => $value ) {
                $value = htmlspecialchars($value);
                if ( strlen($value) > 0 ) {
                    $result[] = (is_null($prefix) ? '' : $prefix) . "$key=\"$value\"";
                }
            }
            $result = implode(' ', $result);

            return $result;
        }

        protected function compileHtmlAttr($attr)
        {
            $result = [];
            foreach ( $attr as $key => $value ) {
                //            $value = htmlspecialchars($value);
                $result[] = $key . ' = "' . $value . '"';
            }
            $result = implode(' ', $result);

            return $result;
        }
    }
