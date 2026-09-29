<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;

/**
 * Builder 基类单元测试（无框架依赖）
 *
 * 覆盖模板驱动相关方法：templatePath（自定义 viewPath 命中优先、未命中回落内置 tpl）、
 * assign（单值 / 数组合并）、assignKeyTemplates（内置 key 子模板注入）。
 * 这些方法是 Builder 的 protected 成员，Table 继承可见，故复用 makeTable 造实例。
 */
class BaseBuilderTest extends TestCase
{
    // ==================== 测试基础设施 ====================

    private function makeTable(): Table
    {
        $ref = new \ReflectionClass(Table::class);

        return $ref->newInstanceWithoutConstructor();
    }

    private function setProp(object $object, string $name, $value): void
    {
        $ref = new \ReflectionProperty($object, $name);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    private function getProp(object $object, string $name)
    {
        $ref = new \ReflectionProperty($object, $name);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }

    private function invoke(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }

    // ==================== templatePath ====================

    public function testTemplatePathFallsBackToBuiltinWhenEmptyViewPath()
    {
        $table = $this->makeTable();
        $this->setProp($table, 'viewPath', '');

        $path = $this->invoke($table, 'templatePath', ['table']);

        $this->assertStringEndsWith('tpl' . DIRECTORY_SEPARATOR . 'table.html', $path);
        $this->assertFileExists($path);
    }

    /**
     * 自定义 viewPath 命中时优先返回自定义路径（支持覆盖内置模板接入不同 UI）
     */
    public function testTemplatePathCustomOverrideWins()
    {
        $table = $this->makeTable();
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'builder_test_tpl_' . uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'table.html', '<!-- custom -->');

        $this->setProp($table, 'viewPath', $dir);
        $path = $this->invoke($table, 'templatePath', ['table']);

        $this->assertSame($dir . DIRECTORY_SEPARATOR . 'table.html', $path);

        // 清理临时目录
        unlink($dir . DIRECTORY_SEPARATOR . 'table.html');
        rmdir($dir);
    }

    /**
     * 自定义 viewPath 存在但缺该模板时回落内置实现
     */
    public function testTemplatePathFallsBackWhenCustomMissing()
    {
        $table = $this->makeTable();
        // src/tpl/key 下没有 table.html，作为"自定义但缺失"场景
        $this->setProp($table, 'viewPath', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'key');

        $path = $this->invoke($table, 'templatePath', ['table']);

        // 回落到内置 tpl/table.html（而非 key/table.html）
        $this->assertStringEndsWith('tpl' . DIRECTORY_SEPARATOR . 'table.html', $path);
        $this->assertFileExists($path);
    }

    // ==================== assign ====================

    public function testAssignSingleValue()
    {
        $table = $this->makeTable();

        $result = $this->invoke($table, 'assign', ['menu_title', '用户管理']);

        $this->assertSame($table, $result);
        $this->assertSame('用户管理', $this->getProp($table, 'view_data')['menu_title']);
    }

    public function testAssignArrayMerges()
    {
        $table = $this->makeTable();
        $this->invoke($table, 'assign', ['a', 1]);

        $result = $this->invoke($table, 'assign', [['b' => 2, 'c' => 3]]);

        $this->assertSame($table, $result);
        $data = $this->getProp($table, 'view_data');
        $this->assertSame(1, $data['a']);
        $this->assertSame(2, $data['b']);
        $this->assertSame(3, $data['c']);
    }

    // ==================== assignKeyTemplates ====================

    /**
     * 内置 tpl/key 下存在默认子模板时，应注入 key_tpl_<type> 变量供 _key.html 分发
     */
    public function testAssignKeyTemplatesInjectsBuiltinKeyTpls()
    {
        $table = $this->makeTable();
        $this->setProp($table, 'viewPath', '');

        $this->invoke($table, 'assignKeyTemplates');

        $data = $this->getProp($table, 'view_data');
        $this->assertArrayHasKey('key_tpl_label', $data);
        $this->assertStringEndsWith('tpl' . DIRECTORY_SEPARATOR . 'key' . DIRECTORY_SEPARATOR . 'label.html', $data['key_tpl_label']);
        $this->assertFileExists($data['key_tpl_label']);
        // 内置 key 目录确实存在大量子模板，应至少注入 10 个
        $injected = array_filter(array_keys($data), fn ($k) => strpos($k, 'key_tpl_') === 0);
        $this->assertGreaterThanOrEqual(10, count($injected));
    }
}
