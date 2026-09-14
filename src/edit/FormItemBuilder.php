<?php

// +----------------------------------------------------------------------
// | builder
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2026 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

namespace yicmf\builder\edit;

use Overtrue\Pinyin\Pinyin;

/**
 * 表单项构建器
 * 2026-09-06 拆分重构：自 Edit 迁出的 key* 系列表单项方法与表单项状态持有者
 * @package yicmf\builder\edit
 */
class FormItemBuilder
{
    /** @var array 表单项配置清单（原 Edit::_keyList） */
    public $keyList = [];

    /** @var string 默认主键字段（原 Edit::_default_pk） */
    public $defaultPk = 'id';

    /**
     * @param string $defaultPk 默认主键字段
     */
    public function __construct(string $defaultPk = 'id')
    {
        $this->defaultPk = $defaultPk;
    }

    /**
     * @return array 表单项配置清单
     */
    public function getKeyList()
    {
        return $this->keyList;
    }

    /**
     * @return array 表单项配置清单（引用，供外部原地修改）
     */
    public function &getKeyListRef()
    {
        return $this->keyList;
    }

    /**
     * @return string 默认主键字段
     */
    public function getDefaultPk()
    {
        return $this->defaultPk;
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
        return $this->key($name, $title, $tips, 'html', $html);
    }

    /**
     * 隐藏表单
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keyHidden($name, $default = '')
    {
        return $this->key($name, null, null, 'hidden', [], $default);
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
        return $this->key($name, $title, $tips, 'readonly');
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
        return $this->key($name, $title, $tips, 'copy');
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
        return $this->key($name, $title, $tips, 'string', null, $default, $verify);
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
        return $this->key($field, $title, $tips, 'icp', null, $default, $verify);
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
        return $this->key($field, $title, $tips, 'inline', null, $default, $verify);
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
        $this->key($field, $title, $tips, 'safe_check', ['wait_time' => $wait_time, 'obj_id' => uniqid()]);
        return $this->keyTextInline('check_code', '验证码', '请输入收到的验证码', '', 'required');
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
        return $this->key($field, $title, $tips, 'string', null, $size, $verify);
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
        return $this->keyText($field, $title, $tips, null, $size, $verify);
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
        return $this->key($field, $title, $tips, 'amap', null, $default, $verify);
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
        return $this->key($field, $title, $tips, 'radio', $options, $default, $verify, 30, $disabled);
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
        return $this->keyRadio($field, $title, $options, $tips, $default, $verify, $disabled);
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
        return $this->key($field, $title, $tips, 'switch', $options, $default, $verify, 30, $disabled);
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
        $options = [
            2 => '女',
            1 => '男',
            0 => '保密',
        ];
        return $this->keyRadio($field, $title, $options, $tips, $default);
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
        return $this->key($field, $title, $tips, 'belongsToMany', ['column' => $column, 'field' => $field, 'limit' => 0], $default, $verify, $size);
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
        $old_filed = $field;
        if (strpos($field, '|')) {
            // 获取field
            $temp = explode('|', $field);
            $v_field = $temp[1];
            $field = $temp[0];
        }
        if (!strpos($url, '?')) {
            // 获取field
            $url = $url.'?';
        }
        if (strpos($field, '.')) {
            $temp = explode('.', $field);
            $show_field = $temp[1];
            $model_name = $temp[0];
            if (!strpos($old_filed, '|')) {
                $v_field = $temp[0] . '_id';
            }
        } else {
            $show_field = 'id';
        }
        return $this->key($v_field, $title, $tips, 'belongTo', ['model_name'=>$model_name,'old_field'=>$old_filed,'show_field_value'=>'','url' => $url, 'show_field' => $show_field, 'field' => $field, 'limit' => 0], $default, $verify, $size);
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
        return $this->key($field, $title, $tips, 'select', $options, $default, $verify);
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
        return $this->key($field, $title, $tips, 'select_multiple', $options, $default, $verify, $size);
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
        return $this->key($field, $title, $tips, 'select_multistage', $options, $default, $verify);
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
        $options = $options ?: [
            -2 => '删除',
            -1 => '禁用',
            1 => '启用',
            0 => '未审核',
            2 => '推荐',
        ];
        return $this->keySelect('status', '状态', $options, $tips, $default, $verify);
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
        return $this->key($field, $title, $tips, 'checkbox', $options, $default, $verify);
    }

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
        return $this->key($field, $title, $tips, 'textarea', null, $default, $verify, [$cols, $rows]);
    }

    /**
     * 意图配置可视化编辑器（intent_config）
     * [Buddy 2026-09-12] 新增：将 fa_ai_flow_node.config 的 JSON（keywords/llm_labels/llm_enabled）以结构化表单编辑，
     * 提交前由模板 JS 实时序列化回隐藏 input（name=字段名），存储/读取端零侵入。
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
        return $this->keyJson($field, $title, $tips, $default, $verify);
    }

    /**
     * 任意 JSON 结构可视化编辑器（json）
     * [ZCode 2026-09-14] 新增：支持 对象(分类子项)/数组(逗号分隔)/开关(is_ 前缀按 0/1 存储，已定义为非数字则按字符串)/字符串/数字 任意组合；
     * 深层嵌套等复杂结构自动回退为该键独立「原始 JSON」编辑，保存无损。$title 缺省直接用字段名，也可传入自定义标题。
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
        return $this->key($field, (null === $title || '' === $title) ? $field : $title, $tips, 'json', ['titles' => (array)$titles], $default, $verify);
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
        return $this->key($field, $title, $tips, 'label');
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
        $pinyin = new Pinyin();
        return $this->key($pinyin->permalink($title, '_'), text($title), $tips, $closure);
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
        return $this->key($field, $title, $tips, 'password', null, $default);
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
        return $this->key($field, $title, $tips, 'url', null, $default, $verify ? ($verify . '|url') : 'url', $size);
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
        return $this->key($field, $title, $tips, 'color', null, $default);
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
        return $this->key($field, $title, $tips, 'dragsort_li', $options);
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
        return $this->key($field, $title, $tips, 'tags', null, $default, $verify);
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
        $option_default = [
            'min' => 0,
            'max' => 255,
            'step' => 1,
            'decimal-place' => 0,
        ];
        $option = is_array($option) ? array_merge($option_default, $option) : $option_default;
        return $this->key($field, $title, $tips, 'slider', $option, $default, $verify);
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
        $verify_default = [
            'rule' => 'money',
            'rule-money' => '[/^(?!0+(?:\.0+)?$)(?:[1-9]\d*|0)(?:\.\d{1,2})?$/, \'金额必须大于0并且只能精确到分\']',
            'tip' => '请填写金额',
            'ok' => '',
        ];
        $verify = is_array($verify) ? array_merge($verify_default, $verify) : $verify_default;
        return $this->key($field, $title, $tips, 'number', null, $default, $verify, $size, null, '￥');
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
        return $this->key($field, $title, $tips, 'number', null, $default, 'require|number|sort');
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
        return $this->key($field, $title, $tips, 'number', null, $default, $verify);
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
        return $this->key($field, $title, $tips, 'time_cycle', null, $default, $verify);
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
        return $this->key($field, $title, $tips, 'email', null, $default, $verify);
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
        return $this->key($field, $title, $tips, 'inline', null, $default, 'mobile');
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
        return $this->key($field, $title, $tips, 'rate', null, $default);
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
        $default_config = [
            'mode'   => 'wysiwyg', // 所见即所得，普通用户友好
            'cdn'    => '/static/vditor',
            'upload' => [
                'url'          => '/file/vditor/upload',
                'fieldName'    => 'file',
                'linkToImgUrl' => '/file/vditor/link',
                'accept'       => 'image/*',
                'max'          => 10485760,
                'multiple'     => true,
                'handler'      => 'form-data',
            ],
        ];
        $config = array_merge($default_config, $config);
        $key = [
            'id_name' => uniqid(),
            'field'   => $field,
            'title'   => $title,
            'tips'    => $tips,
            'type'    => 'vditor',
            'config'  => $config,
            'style'   => $style,
            'default' => $default,
        ];
        $this->keyList[] = $key;
        return $this;
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
        return $this->keyDate($field, $title, $tips, $value, $min, $max, 'time');
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
        return $this->keyDate($field, $title, $tips, $value, $min, $max, 'datetime');
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
        $formats = [
            'year' => 'yyyy',
            'month' => 'MM',
            'date' => 'MM-dd',
            'time' => 'HH:mm:ss',
            'datetime' => 'yyyy-MM-dd HH:mm:ss',
        ];
        $opiton = [
            'elem' => '#j_builder_' . (strpos($field, '|') ? md5($field) : $field),
            'type' => $type,
            'range' => $range,
            'format ' => $formats[$type],
            'mark ' => [],
            'min' => $min,
            'max' => $max,
            'done' => $done
        ];
        foreach ($opiton as $key => $item) {
            if (!$item) {
                unset($opiton[$key]);
            }
        }
        return $this->key($field, $title, $tips, 'date', $opiton, $default, null, 50);
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
        return $this->keyDate($field, $title, $tips, $default, $min, $max, 'datetime', true);
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
        //            $default = '2020-05-01 - 2020-06-30';
        if (is_null($default)) {
            $default = time_format(time(), 'Y-m-d') . ' - ' . time_format('1 month', 'Y-m-d');
        } else if (!is_null($default) && false === strpos($default, '-')) {
            $default = time_format(time(), 'Y-m-d') . ' - ' . time_format($default, 'Y-m-d');
        }
        return $this->keyDate($field, $title, $tips, $default, $min, $max, 'date', true);
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
        return $this->keyDate($field, $title, $tips, $default, $min, $max, 'time', true);
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
        return $this->key($field, $title, $tips, 'showImg');
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
        $extensions = 'mp3,wav,wma,amr';
        return $this->key($field, $title, $remark, 'attachment', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions, 'size' => $size], 30, $verify);
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
        $extensions = 'mp4,avi,rmvb,rm,wmv';
        return $this->key($field, $title, $remark, 'attachment', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions, 'size' => $size], 30, $verify);
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
        $max_size = 0;
        $exts = '';
        $mimes = '';
        return $this->key($field, $title, $tips, 'image',
            ['button' => $button, 'limit' => 1, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
            , $default, $verify);
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
        $max_size = 0;
        $exts = '';
        $mimes = '';
        return $this->key($field, $title, $tips, 'image_model',
            ['button' => $button, 'limit' => 1, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
            , $default, $verify);
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

        $max_size = 0;
        $exts = '';
        $mimes = '';
        return $this->key($field, $title, $tips, 'ImageMultiple',
            ['limit' => $limit, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
            , $default, $verify);
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

        $max_size = 0;
        $exts = '';
        $mimes = '';
        return $this->key($field, $title, $tips, 'ImageMultiple',
            ['limit' => $limit, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
            , $default, $verify);
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
        $max_size = 0;
        $exts = '';
        $mimes = '';
        return $this->key($field, $title, $tips, 'ImageShowMultiple',
            ['limit' => 5, 'max_size' => $max_size, 'mimes' => $mimes, 'exts' => $exts]
            , null, null);
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
        $options['need_hand'] = $need_hand ?: 0;
        return $this->key('_auth', $title, $tips, 'auth', $options);
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
        $extensions = '*';
        $remark = '';
        return $this->key($field, $title, $tips, 'attachment', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions], $default, $verify);
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
        $extensions = '*';
        $remark = '';
        return $this->key($field, $title, $tips, 'attachment_model', ['remark' => $remark, 'limit' => 1, 'extensions' => $extensions], $default, $verify);
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
        $extensions = '*';
        $remark = '';
        return $this->key($field, $title, $tips, 'attachmentMultiple', ['remark' => $remark, 'limit' => $limit, 'extensions' => $extensions], 0, $verify);
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
        // 修正在编辑信息时无法正常显示已经保存的地区信息
        return $this->key($field, $title, $tips, 'city', null, $default, null);
    }

    /**
     * 批量添加字段信息.
     * @param array $fields
     * @return $this
     * @author 微尘 <yicmf@qq.com>
     */
    public function setKeys($fields = [])
    {
        $this->keyList = empty($this->keyList) ? $fields : array_merge($this->keyList, $fields);
        return $this;
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
    // 2026-09-06 拆分重构：自 Edit 迁出；供 Edit 门面跨类转发，可见性由 protected 调整为 public（同 ColumnBuilder::key 先例），参数签名未改动
    public function key($field, $title, $tips, $type, $options = null, $default = '', $verify = null, $size = null, $disabled = null, $placeholder = '')
    {
    if (is_array($verify)) {
        $verify = implode('|', $verify);
    }
    // [Buddy 2026-09-02] 调整：null 安全 + 修正 strpos 位置 0 被判为 falsy 的逻辑缺陷
    if (is_string($verify) && strpos($verify, ',') !== false) {
        $verify = str_replace(',', '|', $verify);
    }
    if (is_string($verify) && false !== strpos($verify, 'require')) {
            $verify = str_replace('require', 'required', $verify);
        }
        $key = [
            'field' => $field,
            'title' => $title,
            'tips' => (string)$tips,
            'type' => $type,
            'default' => $default,
            'disabled' => $disabled,
            'placeholder' => $placeholder,
            'size' => $size,
            'verify' => $verify,
            'options' => $options,
        ];
        $this->keyList[] = $key;
        return $this;
    }

    /**
     * 批量配置key
     * @param array $keyList
     * @return $this
     * @author  : 微尘 <yicmf@qq.com>
     */
    public function keys($keyList)
    {
        foreach ($keyList as $index => $item) {
            $this->key($item['field']
                , isset($item['title']) ? $item['title'] : ''
                , isset($item['tips']) ? $item['tips'] : ''
                , $item['type']
                , isset($item['options']) ? $item['options'] : null
                , isset($item['default']) ? $item['default'] : ''
                , isset($item['verify']) ? $item['verify'] : null
                , isset($item['size']) ? $item['size'] : 30
                , isset($item['disabled']) ? $item['disabled'] : null
            );
        }
        return $this;
    }
}
