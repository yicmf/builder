<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;

/**
 * _key.html 拆分结构测试（2026-09-06 方案 C）
 *
 * _key.html 已改为分发器：{switch} 各 case 通过编译期内联 include
 * 引用 tpl/key/<type>.html（路径经 key_tpl_<type> 变量注入）。
 */
class KeyTplSplitTest extends TestCase
{
    private string $tplDir;
    private string $keyDir;
    private string $dispatcher;

    protected function setUp(): void
    {
        $this->tplDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl';
        $this->keyDir = $this->tplDir . DIRECTORY_SEPARATOR . 'key';
        $this->dispatcher = $this->tplDir . DIRECTORY_SEPARATOR . '_key.html';
    }

    /**
     * 分发器中声明的每个 case 都必须有对应的 key/<type>.html 文件
     */
    public function testEveryCaseHasTemplateFile()
    {
        $content = file_get_contents($this->dispatcher);
        preg_match_all('/\{case value="([A-Za-z0-9_]+)"\}\{include file="\$key_tpl_[A-Za-z0-9_]+" \/\}\{\/case\}/', $content, $m);

        $this->assertSame(46, count($m[1]), '分发器 case 数量应为 46');

        foreach ($m[1] as $type) {
            $this->assertFileExists($this->keyDir . DIRECTORY_SEPARATOR . $type . '.html', "缺少子模板: {$type}");
        }
    }

    /**
     * key/ 目录的每个子模板都被分发器引用（无孤儿文件）
     */
    public function testNoOrphanTemplateFiles()
    {
        $content = file_get_contents($this->dispatcher);
        $files = glob($this->keyDir . DIRECTORY_SEPARATOR . '*.html');

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $type = basename($file, '.html');
            $this->assertStringContainsString('{case value="' . $type . '"}', $content, "未被分发器引用的孤儿文件: {$type}");
        }
    }

    /**
     * 分发器必须保持运行时分发结构：单一 switch、default 回退、表单项外壳
     */
    public function testDispatcherStructure()
    {
        $content = file_get_contents($this->dispatcher);

        $this->assertSame(1, substr_count($content, '{switch name="field.type"}'), 'switch 开标签应恰为 1 个');
        $this->assertSame(1, substr_count($content, '{/switch}'), 'switch 闭标签应恰为 1 个');
        $this->assertStringContainsString('{default/}', $content, '必须保留未知类型回退块');
        $this->assertStringContainsString('错误：未知字段类型', $content);
        $this->assertStringContainsString('layui-form-item', $content, '表单项外壳必须保留');
        $this->assertStringContainsString("{neq name='field.type' value='hidden'}", $content, 'hidden 排除逻辑必须保留');
        // 分发器应为轻量文件（所有重实现已迁入子模板）
        $this->assertLessThan(100, substr_count($content, "\n"), '分发器应保持轻量（<100 行）');
    }

    /**
     * switch 标签内部只允许 case/default 与空白行——任何非空白 inline HTML（含 <!-- 注释 -->）
     * 都会导致编译错误 T_INLINE_HTML（2026-09-06 线上事故：表单页全站 500）
     */
    public function testSwitchContainsOnlyCaseDefaultAndWhitespace()
    {
        $content = file_get_contents($this->dispatcher);
        $start = strpos($content, '{switch name="field.type"}');
        $end = strpos($content, '{/switch}');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $body = substr($content, $start + strlen('{switch name="field.type"}'), $end - $start - strlen('{switch name="field.type"}'));
        // {default/} 之后的回退 HTML 是合法内容；校验范围仅限 case 之间的行
        $body = substr($body, 0, strpos($body, '{default/}'));
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $this->assertMatchesRegularExpression('/^\{(case value=|default\/?)/', $trimmed, "switch 内出现非法内容: {$trimmed}");
        }
    }

    /**
     * 子模板必须包含原实现的渲染内容（抽查关键类型的特征标记）
     */
    public function testSubTemplatesKeepOriginalContent()
    {
        $marks = [
            'string' => 'layui-input-block',
            'ueditor' => 'ueditor',
            'vditor' => 'vditor',
            'image' => 'layui-upload',
            'select_multiple' => 'xm-select',
            'city' => 'city-picker',
        ];
        foreach ($marks as $type => $mark) {
            $file = $this->keyDir . DIRECTORY_SEPARATOR . $type . '.html';
            if (is_file($file)) {
                $this->assertStringContainsString($mark, file_get_contents($file), "{$type}.html 缺少特征标记");
            }
        }
    }

    /**
     * assignKeyTemplates 应为每个 key/<type>.html 注入对应的 key_tpl_<type> 变量，
     * 自定义主题目录命中优先
     */
    public function testAssignKeyTemplatesInjectsVars()
    {
        $table = $this->makeTable();
        $ref = new \ReflectionMethod($table, 'assignKeyTemplates');
        $ref->setAccessible(true);
        $ref->invoke($table);

        $viewData = $this->viewDataOf($table);
        $files = glob($this->keyDir . DIRECTORY_SEPARATOR . '*.html');
        foreach ($files as $file) {
            $type = basename($file, '.html');
            $this->assertArrayHasKey('key_tpl_' . $type, $viewData, "缺少变量 key_tpl_{$type}");
            $this->assertSame($file, $viewData['key_tpl_' . $type]);
        }
    }

    public function testAssignKeyTemplatesCustomDirPriority()
    {
        // 主题目录只覆盖 string 一个类型
        $customRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'builder_tpl_' . uniqid();
        mkdir($customRoot . DIRECTORY_SEPARATOR . 'key', 0777, true);
        file_put_contents($customRoot . DIRECTORY_SEPARATOR . 'key' . DIRECTORY_SEPARATOR . 'string.html', '<div>custom-string</div>');

        $table = $this->makeTable();
        $refProp = new \ReflectionProperty(get_parent_class(Table::class), 'viewPath');
        $refProp->setAccessible(true);
        $refProp->setValue($table, $customRoot);

        $ref = new \ReflectionMethod($table, 'assignKeyTemplates');
        $ref->setAccessible(true);
        $ref->invoke($table);

        $viewData = $this->viewDataOf($table);
        $this->assertSame(
            $customRoot . DIRECTORY_SEPARATOR . 'key' . DIRECTORY_SEPARATOR . 'string.html',
            $viewData['key_tpl_string'],
            '自定义主题的子模板应优先'
        );
        // 未覆盖的类型回落扩展内置
        $this->assertSame(
            $this->keyDir . DIRECTORY_SEPARATOR . 'ueditor.html',
            $viewData['key_tpl_ueditor']
        );

        unlink($customRoot . DIRECTORY_SEPARATOR . 'key' . DIRECTORY_SEPARATOR . 'string.html');
        rmdir($customRoot . DIRECTORY_SEPARATOR . 'key');
        rmdir($customRoot);
    }

    /**
     * 创建跳过构造函数的 Table 实例（Builder::__construct 依赖 app() 容器）
     */
    private function makeTable(): Table
    {
        $ref = new \ReflectionClass(Table::class);

        return $ref->newInstanceWithoutConstructor();
    }

    /**
     * 反射读取 view_data（assign 的存储区）
     */
    private function viewDataOf(Table $table): array
    {
        $ref = new \ReflectionProperty(get_parent_class(Table::class), 'view_data');
        $ref->setAccessible(true);

        return $ref->getValue($table);
    }
}
