<?php
    // +----------------------------------------------------------------------
    // | builder
    // +----------------------------------------------------------------------
    // | Copyright (c) 2015-2022 http://www.yicmf.com, All rights reserved.
    // +----------------------------------------------------------------------
    // | Author: 微尘 <yicmf@qq.com>
    // +----------------------------------------------------------------------
    namespace yicmf\builder;

    use app\admin\model\Menu as MenuModel;
	use Overtrue\Pinyin\Pinyin;
	use think\Loader;

    class View extends Builder
    {
        private $_title;

        private $_suggest;

        private $_warning;

        private $_keyList = [];

        private $_data = [];

        private $_buttonList = [];

        private $_savePostUrl = [];

        private $_group = [];

        private $_callback = null;

        // 提示信息
        private $_explaints = [];

        public function title($title)
        {
            $this->_title = $title;
            $this->meta_title = $title;
            return $this;
        }

        public function tip($tip)
        {
            $this->_explaints[] = $tip;
            return $this;
        }

        /**
         * suggest 页面标题边上的提示信息.
         *
         * @param $suggest
         *
         * @return View
         *
         * @author :
         */
        public function suggest($suggest)
        {
            $this->_suggest = $suggest;
            return $this;
        }

        /**
         * warning 页面标题边上的错误信息.
         *
         * @param $warning
         *
         * @return View
         */
        public function warning($warning)
        {
            $this->_warning = $warning;
            return $this;
        }

        public function callback($callback)
        {
            $this->_callback = $callback;
            return $this;
        }

        /**
         * 键，一般用于内部调用.
         *
         * @param      $name
         * @param      $title
         * @param null $subtitle
         * @param      $type
         * @param null $opt
         *
         * @return View
         */
        public function key($name, $title, $subtitle, $type, $opt = null)
        {
            $key = [
                'name' => $name,
                'title' => $title,
                'subtitle' => $subtitle,
                'type' => $type,
                'opt' => $opt,
            ];
            $this->_keyList[] = $key;
            return $this;
        }

        /**
         * 闭包函数
         * @param       $name
         * @param       $title
         * @param       $closure
         * @param       $subtitle
         * @param array $opt
         * @return View
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/2/22 11:37
         */
        public function keyClosure( $title, $closure, $subtitle = '', $opt = [])
        {
			$pinyin = new Pinyin();
			return $this->key($pinyin->permalink($title, '_'),  text($title), $subtitle, $closure, $opt);
        }

        public function keyLabel($name, $title, $subtitle = null)
        {
            return $this->key($name, $title, $subtitle, 'label');
        }

        public function button($title, $attr = [])
        {
            $this->_buttonList[] = [
                'title' => $title,
                'attr' => $attr,
            ];
            return $this;
        }

        public function buttonBack($title = '关闭')
        {
            $attr = [];
            $attr['type'] = 'button';
            $attr['data-icon'] = 'close';
            $attr['class'] = 'btn-close';
            return $this->button($title, $attr);
        }

        public function data($list)
        {
            $this->_data = $list;
            return $this;
        }

        /**
         * @param string $template
         * @param array  $vars
         * @param array  $replace
         * @param array  $config
         * @return string
         * @throws \Exception
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/3/28 13:44
         */
        public function fetch($template = '', $vars = [], $replace = [], $config = [])
        {
            // 将数据融入到key中
            foreach ($this->_keyList as &$e) {
                if ($e['type'] == 'multiInput') {
                    $e['name'] = explode(',', $e['name']);
                }
                $e['value'] = $this->convertKey($e, $this->_data);
            }
            // 编译按钮的html属性
            foreach ($this->_buttonList as &$button) {
                $button['attr'] = $this->compileHtmlAttr($button['attr']);
            }
            // 查询当前菜单
            $menu = MenuModel::where('status', 1)
                ->where('action', $this->request->action())
                ->where('controller', $this->request->controller())
                ->where('module', $this->request->module())
                ->find();
            if ($menu && !$this->_title) {
                $this->_title = $menu['title'];
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
            $this->assign('group', $this->_group);
            $this->assign('title', $this->_title);
            $this->assign('suggest', $this->_suggest);
            $this->assign('warning', $this->_warning);
            $this->assign('keyList', $this->_keyList);
            $this->assign('buttonList', $this->_buttonList);
            // 在有赋值的情况展示
            if (count($this->_explaints) > 0) {
                $this->assign('explaints', $this->_explaints);
            }
            return parent::_fetch('view', $vars, $replace, $config);
        }

        /**
         * 插入配置分组
         * @param string $name 组名
         * @param array  $list 组内字段列表
         * @return $this
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/3/28 13:46
         */
        public function group($name, $list = [])
        {
            !is_array($list) && $list = explode(',', $list);
            $this->_group[$name] = $list;
            return $this;
        }

        public function groups($list = [])
        {
            foreach ($list as $key => $v) {
                $this->_group[$key] = is_array($v) ? $v : explode(',', $v);
            }
            return $this;
        }

        /**
         * 根据类型转换格式.
         */
        private function convertKey($key, $data)
        {
            if ($key['type'] instanceof \Closure) {
                return $key['type']($data, $key);
            } else {
                $method = 'convert' . Loader::parseName($key['type'], 1) . 'Value';
                if (false !== strpos($key['name'], '{$')) {
                    $display = $key['name'];
                    $view = $this->app['view'];
                    $value = $view->display($display, ['data' => $data]);
                    return $this->$method($value, $key, $data);
                } else {
                    $display = '{$data.' . $key['name'] . '}';
                    $view = $this->app['view'];
                    $value = $view->display($display, ['data' => $data]);
                    return $this->$method($value, $key, $data);
                }
            }
        }

        /**
         * text转换为html
         * @param $value
         * @param $key
         * @param $data
         * @return mixed
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/2/22 11:57
         */
        protected function convertLabelValue($value, $key, $data)
        {
            return $value;
        }
    }
