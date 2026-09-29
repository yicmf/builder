<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;
use yicmf\builder\table\ButtonBuilder;

/**
 * ButtonBuilder 单元测试（无框架依赖）
 *
 * 2026-09-06 拆分重构：button/action 系列自 Table 迁至 ButtonBuilder 后的回归验证，
 * 覆盖按钮/批量操作写入、门面链式语义与 authCheck 回调解耦。
 */
class ButtonBuilderTest extends TestCase
{
    // ==================== 测试基础设施 ====================

    /**
     * 创建跳过构造函数的 Table 实例（Builder::__construct 依赖 app() 容器）
     */
    private function makeTable(): Table
    {
        $ref = new \ReflectionClass(Table::class);

        return $ref->newInstanceWithoutConstructor();
    }

    /**
     * 通过反射调用 protected/private 方法
     */
    private function invoke(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
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

    // ==================== ButtonBuilder 直调用例 ====================

    public function testButtonAppendsToButtonList()
    {
        $table = $this->makeTable();
        $builder = new ButtonBuilder($table);

        $result = $builder->button('删除', ['url' => '/admin/x/delete']);

        $this->assertSame($builder, $result);
        $list = $builder->getButtonList();
        $this->assertCount(1, $list);
        $this->assertSame('删除', $list[0]['title']);
        $this->assertSame('/admin/x/delete', $list[0]['attr']['url']);
    }

    public function testGroupActionAppendsToGroup()
    {
        $table = $this->makeTable();
        $builder = new ButtonBuilder($table);

        $result = $builder->groupAction('操作', '/admin/x/g', '确认', 'doajax');

        $this->assertSame($builder, $result);
        $group = $builder->getGroup();
        $this->assertCount(1, $group);
        $this->assertSame('操作', $group[0]['title']);
        $this->assertSame('/admin/x/g', $group[0]['url']);
        $this->assertSame('确认', $group[0]['msg']);
        $this->assertSame('doajax', $group[0]['toggle']);
    }

    /**
     * 回归 2026-09 修复：导入按钮必须使用 id 键（而非 data-id），
     * 否则模板 {case import} 中 {$button.attr.id} 取不到值，按钮失效。
     * 同时锁定 event=import 与 module/controller 补全逻辑。
     */
    public function testButtonExcelImportUsesIdKeyNotDataId()
    {
        $table = $this->makeTable();
        // buttonExcelImport 内部依赖 request->controller()/time() 与 module，构造假桩
        $request = new class {
            public function controller()
            {
                return 'demo';
            }

            public function time()
            {
                return 12345;
            }
        };
        $this->setProp($table, 'request', $request);
        $this->setProp($table, 'module', 'admin');

        $builder = new ButtonBuilder($table);
        $result = $builder->buttonExcelImport('import', '导入');

        // 链式返回自身
        $this->assertSame($builder, $result);

        $list = $builder->getButtonList();
        $this->assertCount(1, $list, '导入按钮应写入 buttonList');
        $attr = $list[0]['attr'];

        // 关键修复点：attr 键名为 id（修复前误写为 data-id）
        $this->assertArrayHasKey('id', $attr, 'attr 必须包含 id 键（修复点）');
        $this->assertArrayNotHasKey('data-id', $attr, 'attr 不应包含 data-id 键（修复前的 bug）');
        // event 必须为 import，模板 switch 才能命中 {case import}
        $this->assertSame('import', $attr['event'], 'event 必须为 import');
        // 未带斜杠的 url 应被补全为 module/controller/action
        $this->assertSame('admin/demo/import', $attr['url'], 'url 应补全为 admin/demo/import');
    }

    // ==================== Table 门面链式语义 ====================

    public function testFacadeChainReturnsTable()
    {
        $table = $this->makeTable();
        // _user 为未初始化（false），authCheck 直接返回 true，不会触发 request

        $result = $table->button('删除', ['url' => '/admin/x/delete']);
        $this->assertInstanceOf(Table::class, $result);
        $this->assertSame($table, $result);

        $result = $table->keyId();
        $this->assertInstanceOf(Table::class, $result);
        $this->assertSame($table, $result);

        $result = $table->buttonAjax('/admin/x/toggle', '启用', 'doajax', ['icon' => 'check']);
        $this->assertInstanceOf(Table::class, $result);
        $this->assertSame($table, $result);

        // 门面调用后按钮写入 ButtonBuilder，可通过反射读取验证
        $builder = $this->invoke($table, 'buttonBuilder');
        $this->assertInstanceOf(ButtonBuilder::class, $builder);
        $this->assertCount(2, $builder->getButtonList());
    }

    // ==================== authCheck 解耦 ====================

    public function testSetAuthCheckerOverridesCheck()
    {
        $table = $this->makeTable();
        $received = [];
        $table->setAuthChecker(function ($url, $user) use (&$received) {
            $received = ['url' => $url, 'user' => $user];

            return false;
        });

        $this->assertFalse($table->authCheck('/admin/x/index'));
        $this->assertSame('/admin/x/index', $received['url']);
        // 未初始化的 _user 读取为 null（跳过构造函数的实例），回传给回调即可
        $this->assertEmpty($received['user']);
    }

    public function testAuthCheckDefaultsTrueWithoutUser()
    {
        $table = $this->makeTable();
        // 未设置 _user、未注入回调：原逻辑直接放行
        $this->assertTrue($table->authCheck('/admin/x/index'));
    }
}
