<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;

/**
 * 模板驱动（2026-09-06）单元测试
 *
 * 验证 builder.view_path 自定义模板根目录机制：
 * 自定义目录命中优先、未命中回落扩展内置 tpl/。
 */
class TemplateDriverTest extends TestCase
{
    /**
     * 创建跳过构造函数的 Table 实例（Builder::__construct 依赖 app() 容器）
     */
    private function makeTable(): Table
    {
        $ref = new \ReflectionClass(Table::class);

        return $ref->newInstanceWithoutConstructor();
    }

    /**
     * 反射调用 protected templatePath()
     */
    private function resolvePath(Table $table, string $template): string
    {
        $ref = new \ReflectionMethod($table, 'templatePath');
        $ref->setAccessible(true);

        return $ref->invoke($table, $template);
    }

    /**
     * 反射设置 protected viewPath 属性（构造函数外的注入口）
     */
    private function setViewPath(Table $table, string $path): void
    {
        $ref = new \ReflectionProperty(get_parent_class(Table::class), 'viewPath');
        $ref->setAccessible(true);
        $ref->setValue($table, $path);
    }

    public function testDefaultFallsBackToPackageTpl()
    {
        $table = $this->makeTable();

        $path = $this->resolvePath($table, 'table');

        $expected = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'table.html';
        $this->assertSame($expected, $path);
        $this->assertFileExists($path);
    }

    public function testCustomDirHitTakesPriority()
    {
        // 临时自定义模板目录，放一个 table.html
        $customDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'builder_tpl_' . uniqid();
        mkdir($customDir, 0777, true);
        file_put_contents($customDir . DIRECTORY_SEPARATOR . 'table.html', '<div>custom</div>');

        $table = $this->makeTable();
        $this->setViewPath($table, $customDir);

        $path = $this->resolvePath($table, 'table');

        $this->assertSame($customDir . DIRECTORY_SEPARATOR . 'table.html', $path);

        unlink($customDir . DIRECTORY_SEPARATOR . 'table.html');
        rmdir($customDir);
    }

    public function testCustomDirMissFallsBackToPackageTpl()
    {
        // 自定义目录存在但没有该模板，应回落扩展内置
        $customDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'builder_tpl_' . uniqid();
        mkdir($customDir, 0777, true);

        $table = $this->makeTable();
        $this->setViewPath($table, $customDir);

        $path = $this->resolvePath($table, 'table');

        $expected = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'table.html';
        $this->assertSame($expected, $path);

        rmdir($customDir);
    }

    public function testEmptyViewPathUsesPackageTpl()
    {
        $table = $this->makeTable();
        $this->setViewPath($table, '');

        $this->assertStringContainsString(
            DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . '_key.html',
            $this->resolvePath($table, '_key')
        );
    }

    public function testPackageConfigContainsViewPath()
    {
        $config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config.php';

        $this->assertArrayHasKey('view_path', $config);
        $this->assertSame('', $config['view_path']);
    }
}
