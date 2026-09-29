<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;

/**
 * table.html 模板契约测试（无框架依赖）
 *
 * 覆盖 2026-09 修复的导入按钮渲染回归：
 *  - 工具栏 switch 必须存在独立的 {case import} 分支；
 *  - 该分支必须渲染 <button>（而非落入 {default /} 渲染成 <a> 链接）；
 *  - 该分支必须以 {$button.attr.id} 作为 data-id（与 ButtonBuilder 修复后的键名一致）；
 *  - 配套的 JS 工具栏事件处理 case 'import': 必须存在（upload.render 上传逻辑）。
 *
 * 设计原则：不依赖 ThinkPHP 模板引擎，直接对模板文件做结构断言，
 * 抓住“分支被删/写错/键名漂移”这类回归。
 */
class TableTemplateContractTest extends TestCase
{
    /**
     * 读取模板文件内容，顺便断言文件存在
     */
    private function tpl(): string
    {
        $path = __DIR__ . '/../src/tpl/table.html';
        $this->assertFileExists($path, 'table.html 模板文件必须存在');

        $content = file_get_contents($path);
        $this->assertIsString($content);

        return $content;
    }

    /**
     * 抽取 {case import} ... 到下一个 {/case} 或 {default 之间的内容
     */
    private function importCaseBlock(string $tpl): string
    {
        if (preg_match('/\{case\s+import\s*\}(.*?)(\{\/case\}|\{default)/s', $tpl, $m)) {
            return $m[1];
        }

        return '';
    }

    public function testImportCaseExists()
    {
        $tpl = $this->tpl();
        $this->assertMatchesRegularExpression(
            '/\{case\s+import\s*\}/',
            $tpl,
            '工具栏 switch 必须存在 {case import} 分支'
        );
    }

    public function testImportCaseRendersButtonNotAnchor()
    {
        $tpl = $this->tpl();
        $block = $this->importCaseBlock($tpl);

        $this->assertNotEmpty($block, '应能抽取到 {case import} 分支内容');
        $this->assertStringContainsString(
            '<button',
            $block,
            'import 分支必须渲染 <button>（lay-event 由工具栏事件接管），而非落入 default 渲染 <a>'
        );
        $this->assertStringNotContainsString(
            '<a ',
            $block,
            'import 分支不应渲染 <a> 链接'
        );
    }

    public function testImportCaseUsesAttrIdForKey()
    {
        $tpl = $this->tpl();
        // 锚定到 {case import} 之后，确认其 data-id 取自 {$button.attr.id}
        $this->assertMatchesRegularExpression(
            '/\{case\s+import\s*\}.*?data-id="\{\$button\.attr\.id[^\}]*\}"/s',
            $tpl,
            'import 分支应以 {$button.attr.id} 作为 data-id（与 ButtonBuilder 修复后的键名一致）'
        );
    }

    public function testImportToolbarHandlerExists()
    {
        $tpl = $this->tpl();
        $this->assertMatchesRegularExpression(
            "/case\s+'import'\s*:/",
            $tpl,
            'JS 工具栏事件必须存在 case \'import\': 处理（upload.render 上传逻辑）'
        );
    }
}
