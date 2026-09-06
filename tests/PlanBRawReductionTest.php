<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;

/**
 * 方案B raw 收敛回归测试（2026-09-06）
 *
 * think-template 默认 default_filter=htmlentities，不带过滤器的输出已自动转义；
 * 所有 |raw 均为显式绕过点。本测试固化 2026-09-06 收敛结果：
 * - JSON 进 <script> 上下文必须带 JSON_HEX_* flags（防 </script> 逃逸）
 * - lay-verify 属性上下文不得使用 raw
 * - 开发者 HTML 直通通道（closure/ueditor/vditor/html、templets、tips、placeholder）保留 raw
 */
class PlanBRawReductionTest extends TestCase
{
    private function tplFiles(): array
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl';

        return array_merge(glob($dir . '/*.html'), glob($dir . '/key/*.html'));
    }

    public function testNoUnflaggedJsonEncodeRaw()
    {
        foreach ($this->tplFiles() as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('|json_encode|raw}', $content, basename($file) . ' 存在未加 HEX flags 的 JSON raw 输出');
        }
    }

    public function testJsonEncodeUsesHexFlags()
    {
        $count = 0;
        foreach ($this->tplFiles() as $file) {
            $count += substr_count(file_get_contents($file), '|json_encode=271|raw}');
        }
        $this->assertSame(11, $count, 'JSON HEX flags 输出点应为 11 处');
    }

    public function testNoRawVerifyAttribute()
    {
        foreach ($this->tplFiles() as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString("\$field['verify']|raw}", $content, basename($file) . ' lay-verify 属性不应使用 raw');
        }
    }

    public function testDeveloperHtmlChannelsKeepRaw()
    {
        $keyDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'key';
        foreach (['closure.html', 'ueditor.html', 'vditor.html', 'html.html'] as $file) {
            $this->assertStringContainsString('|raw}', file_get_contents($keyDir . DIRECTORY_SEPARATOR . $file), "{$file} 的开发者 HTML 通道应保留 raw");
        }
    }

    /**
     * JS 注释内绝不能出现字面量 </script>：HTML 解析器会无视 JS 注释提前闭合脚本标签，
     * 导致后续 JS 泄漏为页面文本（2026-09-06 线上事故：laydate.render 泄漏为文字）
     */
    public function testNoScriptCloseSequenceInsideJsComments()
    {
        foreach ($this->tplFiles() as $file) {
            foreach (file($file) as $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//')) {
                    $this->assertStringNotContainsString('</script>', $line, basename($file) . ' 的 JS 注释行包含 </script> 字面量');
                }
            }
        }
    }
}
