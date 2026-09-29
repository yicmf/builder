<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Edit;

/**
 * Edit 单元测试（无框架依赖）
 *
 * 通过反射创建 Edit 实例（跳过构造函数以避免 app() 容器依赖），
 * 覆盖 setTrigger 的 09-02 修复逻辑、button 系列、group、data、title 等链式 API。
 *
 * 不测依赖框架的方法：fetch（Db/View）、keyEditor/keyClosure（Pinyin/text()/url）、
 * key* 委托（已交由 FormItemBuilderTest 覆盖）。
 */
class EditTest extends TestCase
{
    // ==================== 测试基础设施 ====================

    /**
     * 创建跳过构造函数的 Edit 实例（Builder::__construct 依赖 app() 容器）
     */
    private function makeEdit(): Edit
    {
        $ref = new \ReflectionClass(Edit::class);

        return $ref->newInstanceWithoutConstructor();
    }

    /**
     * 通过反射设置 protected/private 属性
     */
    private function setProp(object $object, string $name, $value): void
    {
        $ref = new \ReflectionProperty($object, $name);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    /**
     * 通过反射读取 protected/private 属性（Edit 未提供对应 getter）
     */
    private function getProp(object $object, string $name)
    {
        $ref = new \ReflectionProperty($object, $name);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }

    // ==================== setTrigger（2026-09-02 修复） ====================

    /**
     * 修复点 1：字符串 value 含逗号应拆分为多条触发项
     */
    public function testSetTriggerSplitsCommaValue()
    {
        $edit = $this->makeEdit();

        $result = $edit->setTrigger('type', '0,1', 'reply');

        $this->assertSame($edit, $result);
        $triggers = $this->getProp($edit, '_triggers');
        $this->assertArrayHasKey('type', $triggers);
        $this->assertCount(2, $triggers['type']);
        $this->assertSame('0', $triggers['type'][0]['value']);
        $this->assertSame('1', $triggers['type'][1]['value']);
        $this->assertSame('reply', $triggers['type'][0]['show']);
        $this->assertSame('', $triggers['type'][0]['tpl']);
    }

    /**
     * 修复点 2：数字 value 应被包成单元素数组（避免被当作标量丢值）
     */
    public function testSetTriggerWrapsNumericValue()
    {
        $edit = $this->makeEdit();

        $edit->setTrigger('status', 0, 'enable');

        $triggers = $this->getProp($edit, '_triggers');
        $this->assertSame([['value' => 0, 'show' => 'enable', 'tpl' => '']], $triggers['status']);
    }

    /**
     * 修复点 3：普通字符串单值（不含逗号）应被包成单元素数组
     */
    public function testSetTriggerWrapsStringValue()
    {
        $edit = $this->makeEdit();

        $edit->setTrigger('type', 'open', 'panel');

        $triggers = $this->getProp($edit, '_triggers');
        $this->assertSame([['value' => 'open', 'show' => 'panel', 'tpl' => '']], $triggers['type']);
    }

    /**
     * 修复点 4：数组 value 为空时应回落为 [''], 不应整体丢弃
     */
    public function testSetTriggerEmptyArrayValueBecomesEmptyString()
    {
        $edit = $this->makeEdit();

        $edit->setTrigger('type', [], 'panel');

        $triggers = $this->getProp($edit, '_triggers');
        $this->assertSame([['value' => '', 'show' => 'panel', 'tpl' => '']], $triggers['type']);
    }

    /**
     * 数组 trigger 直接批量合并
     */
    public function testSetTriggerArrayTriggerMerges()
    {
        $edit = $this->makeEdit();
        $edit->setTrigger('a', 0, 'x');

        $result = $edit->setTrigger(['b' => [['value' => 1, 'show' => 'y', 'tpl' => '']]]);

        $this->assertSame($edit, $result);
        $triggers = $this->getProp($edit, '_triggers');
        $this->assertArrayHasKey('a', $triggers);
        $this->assertArrayHasKey('b', $triggers);
        $this->assertSame(1, $triggers['b'][0]['value']);
    }

    // ==================== group ====================

    public function testGroupWithStringList()
    {
        $edit = $this->makeEdit();

        $result = $edit->group('g1', 'a,b,c');

        $this->assertSame($edit, $result);
        $group = $this->getProp($edit, '_group');
        $this->assertSame(['g1' => ['a', 'b', 'c']], $group);
    }

    public function testGroupWithArrayList()
    {
        $edit = $this->makeEdit();

        $edit->group('g1', ['a', 'b']);

        $group = $this->getProp($edit, '_group');
        $this->assertSame(['g1' => ['a', 'b']], $group);
    }

    public function testGroupWithArrayArgumentMerges()
    {
        $edit = $this->makeEdit();
        $edit->group('g1', 'a,b');

        $edit->group(['g2' => ['m', 'n']]);

        $group = $this->getProp($edit, '_group');
        $this->assertSame(['g1' => ['a', 'b'], 'g2' => ['m', 'n']], $group);
    }

    // ==================== button 系列 ====================

    /**
     * buttonSubmit(null) 应使用 url() 桩默认值；不触发 /Admin 替换
     */
    public function testButtonSubmitWithNullUrlUsesDefault()
    {
        $edit = $this->makeEdit();

        $result = $edit->buttonSubmit(null, '保存');

        $this->assertSame($edit, $result);
        $buttons = $this->getProp($edit, '_form_buttons');
        $this->assertCount(1, $buttons);
        $this->assertSame('submit', $buttons[0]['type']);
        $this->assertSame('保存', $buttons[0]['title']);
        $this->assertSame('save', $buttons[0]['icon']);
        // /Admin 替换逻辑：桩 url() 返回 '/'，不含 /Admin，故保持
        $this->assertSame('/', $buttons[0]['url']);
        $this->assertArrayHasKey('lay-submit', $buttons[0]['attr']);
    }

    /**
     * 锁定 /Admin -> /admin 大小写修正（ThinkPHP 约定小写模块名）
     */
    public function testButtonSubmitLowercasesAdminSegment()
    {
        $edit = $this->makeEdit();

        $edit->buttonSubmit('/Admin/Seo/import', '导入');

        $buttons = $this->getProp($edit, '_form_buttons');
        $this->assertSame('/admin/Seo/import', $buttons[0]['url']);
    }

    public function testButtonResetAndClose()
    {
        $edit = $this->makeEdit();

        $edit->buttonReset('重新填写')->buttonClose('关闭');

        $buttons = $this->getProp($edit, '_form_buttons');
        $this->assertCount(2, $buttons);
        $this->assertSame('reset', $buttons[0]['type']);
        $this->assertSame('重新填写', $buttons[0]['title']);
        $this->assertSame('close', $buttons[1]['type']);
        $this->assertSame('关闭', $buttons[1]['title']);
    }

    public function testButtonClickStoresClickHandler()
    {
        $edit = $this->makeEdit();

        $edit->buttonClick('doSomething()', '执行');

        $buttons = $this->getProp($edit, '_form_buttons');
        $this->assertSame('button', $buttons[0]['type']);
        $this->assertSame('doSomething()', $buttons[0]['click']);
        $this->assertSame('执行', $buttons[0]['title']);
    }

    // ==================== data / title / explain / form 链式 ====================

    public function testDataSetsAndReturnsSelf()
    {
        $edit = $this->makeEdit();

        $result = $edit->data(['id' => 1, 'name' => 'x']);

        $this->assertSame($edit, $result);
        $this->assertSame(['id' => 1, 'name' => 'x'], $this->getProp($edit, '_data'));
    }

    public function testTitleSetsAndReturnsSelf()
    {
        $edit = $this->makeEdit();

        $result = $edit->title('编辑用户');

        $this->assertSame($edit, $result);
        $this->assertSame('编辑用户', $this->getProp($edit, '_title'));
    }

    public function testExplainStringAndArray()
    {
        $edit = $this->makeEdit();

        $edit->explain('提示一');
        $result = $edit->explain(['提示二', '提示三']);

        $this->assertSame($edit, $result);
        $this->assertSame(['提示一', '提示二', '提示三'], $this->getProp($edit, '_explaints'));
    }

    public function testFormSetsReloadAndMask()
    {
        $edit = $this->makeEdit();

        $result = $edit->form(false, false);

        $this->assertSame($edit, $result);
        $this->assertFalse($this->getProp($edit, '_reload'));
        $this->assertFalse($this->getProp($edit, '_mask'));
    }
}
