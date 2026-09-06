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
 * 表格按钮构建器
 * 2026-09-06 拆分重构：自 Table 迁出的 button/action 系列按钮配置方法与按钮状态持有者
 * 权限检查与行操作写入通过 Table 门面回调（authCheck/keyDoAction）完成，保持原语义
 * @package yicmf\builder\table
 */
class ButtonBuilder
{
    /** @var array 按钮配置清单（原 Table::_buttonList） */
    public $buttonList = [];
    /** @var array 复选框批量操作清单（原 Table::_group） */
    public $group = [];
    /** @var object Table 门面引用（访问 request/module/toggle/弹窗配置/user/authCheck/keyDoAction） */
    private $table;

    /**
     * @param object $table Table 门面实例
     */
    public function __construct($table)
    {
        $this->table = $table;
    }

    /**
     * @return array 按钮配置清单
     */
    public function getButtonList()
    {
        return $this->buttonList;
    }

    /**
     * @return array 复选框批量操作清单
     */
    public function getGroup()
    {
        return $this->group;
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
        if (false === $this->table->authCheck($attr['url'])) {
            return $this;
        }
        $this->buttonList[] = [
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
     * @return $this
     */
    public function buttonUpdate($url = 'update', $title = '新增', $width = '', $height = '', $attr = [])
    {
        $default['url'] = $url;
        $default['class'] = 'layui-bg-green';
        $default['icon'] = 'plus';
        $default['width'] = $width ?: $this->table->getDialogWidth();
        $default['height'] = $height ?: $this->table->getDialogHeight();
        $default['data-title'] = $title != '新增' ? $title : $this->table->getRequest()->controller() . '新增';
        $default['data-id'] = 'id' . md5('dialog-' . $this->table->getRequest()->controller() . '-add-' . $this->table->getRequest()->time());
        return $this->buttonDialog($title, array_merge($default, $attr));
    }

    /**
     * 导入表格
     * @param string $url
     * @param string $title
     * @return $this
     */
    public function buttonExcelImport($url = 'import', $title = '导入',$attr = [])
    {
        if (false === strpos($url, '/')) {
            // 补充
            if ($this->table->getModule())
            {
                $url = $this->table->getModule() . '/' . $this->table->getRequest()->controller() . '/' . $url;
            }else{
                $url =  $this->table->getRequest()->controller() . '/' . $url;
            }
        }
        $default['url'] = $url;
        $default['class'] = 'layui-bg-green';
        $default['icon'] = 'plus';
        $default['event'] = 'import';
        $default['data-id'] = 'id' . md5('dialog-' . $this->table->getRequest()->controller() . '-add-' . $this->table->getRequest()->time());

        return $this->button($title, array_merge($default, $attr));
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
        $default['url'] = $url;
        $default['class'] = 'layui-bg-green';
        if (is_string($icon)) {
            $default['icon'] = $icon;
        }
        $default['width'] = '100%';
        $default['height'] = '100%';
        $default['data-title'] = $title != '新增' ? $title : $this->table->getRequest()->controller() . '新增';
        $default['data-id'] = 'id' . md5('dialog-' . $this->table->getRequest()->controller() . '-add-' . $this->table->getRequest()->time());
        return $this->buttonDialog($title, array_merge($default, $attr));
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
        $attr['url'] = $url;
        $attr['class'] = isset($attr['class']) ? $attr['class'] : 'layui-bg-green';
        $attr['width'] = isset($attr['width']) ? $attr['width'] : $this->table->getDialogWidth();
        $attr['height'] = isset($attr['height']) ? $attr['height'] : $this->table->getDialogHeight();
        $attr['toggle'] = $this->table->getToggle();
        $attr['event'] = 'edit';
        $attr['title'] = $title ?: $this->table->getRequest()->controller();
        return $this->button($title, $attr);
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
        if (false === strpos($attr['url'], '/')) {
            // 补充
            if ($this->table->getModule())
            {
                $attr['url'] = $this->table->getModule() . '/' . $this->table->getRequest()->controller() . '/' . $attr['url'];
            }else{
                $attr['url'] =  $this->table->getRequest()->controller() . '/' . $attr['url'];
            }
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
            'toggle' => $this->table->getToggle(),
            'event' => 'popup',
        ]));
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
        $this->group[] = [
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
     * 不可操作
     * @param string $title
     * @param array $status
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function actionDisable($title = '不可操作', $status = [])
    {
        return $this->table->keyDoAction('', $title, empty($status) ? [0, 1, 2] : $status, 'no', '', 'layui-btn-orange', 'stop');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'tab', '', 'layui-btn-green', 'search');

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
        return $this->table->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'form', '', 'layui-btn-green', 'auz');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, 'tab', '', 'layui-bg-green', 'link');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [-1, 0, 1, 2] : $status, 'ajax', $message, $class, $icon);
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [-1, 0, 1, 2] : $status, 'ajax', $message, 'layui-btn-danger', 'delete');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [0, 1, 2] : $status, $dialog == false ? 'tab' : $dialog, '', 'layui-bg-green', 'edit');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [-2] : $status, 'ajax', $message, 'btn-red', 'trash-o');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [1, 2] : $status, 'ajax', $message, 'layui-btn-danger', 'close-fill');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [0] : $status, 'ajax', $message, 'layui-bg-green', 'ok');
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
        return $this->table->keyDoAction($url, $title, empty($status) ? [-1, -2] : $status, 'ajax', $message, 'btn-red', 'ok-circle');
    }
}
