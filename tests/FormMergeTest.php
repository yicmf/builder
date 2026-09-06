<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;

/**
 * 方案D：edit.html / dialog.html 合并结构测试（2026-09-06）
 *
 * 公共表单主体在 _form.html，edit/dialog 仅保留各自差异壳；
 * JS 行为差异（req.reload）由 form_mode 条件区分；
 * 合并前完整原始文件保留为 *.bak。
 */
class FormMergeTest extends TestCase
{
    private string $tplDir;

    protected function setUp(): void
    {
        $this->tplDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'tpl';
    }

    public function testFormCommonBodyExistsAndReferencedByBothShells()
    {
        $this->assertFileExists($this->tplDir . DIRECTORY_SEPARATOR . '_form.html');

        foreach (['edit.html', 'dialog.html'] as $shell) {
            $content = file_get_contents($this->tplDir . DIRECTORY_SEPARATOR . $shell);
            $this->assertStringContainsString('{include file="$form_path"/}', $content, "{$shell} 未引入公共主体 _form.html");
        }
    }

    public function testPageShellKeepsBreadcrumbAndCard()
    {
        $content = file_get_contents($this->tplDir . DIRECTORY_SEPARATOR . 'edit.html');

        $this->assertStringContainsString('layadmin-header', $content, '页面壳应保留面包屑头部');
        $this->assertStringContainsString('layui-card-header', $content, '页面壳应保留卡片标题');
        $this->assertStringContainsString('layui-card-body', $content, '页面壳应保留卡片容器');
    }

    public function testDialogShellHasNoPageContainer()
    {
        $content = file_get_contents($this->tplDir . DIRECTORY_SEPARATOR . 'dialog.html');

        $this->assertStringNotContainsString('layadmin-header', $content, '弹窗壳不应有面包屑头部');
        $this->assertStringNotContainsString('layui-card', $content, '弹窗壳不应有页面卡片容器');
    }

    public function testReloadBehaviorConditionedByFormMode()
    {
        $form = file_get_contents($this->tplDir . DIRECTORY_SEPARATOR . '_form.html');

        $this->assertStringContainsString("{eq name='form_mode' value='dialog'}", $form, 'reload 行为必须由 form_mode 条件包裹');
        // 激活的重载代码必须在 dialog 条件内
        $pos = strpos($form, "{eq name='form_mode' value='dialog'}");
        $active = substr($form, $pos, (int) strpos($form, '{/eq}', $pos) - $pos);
        $this->assertStringContainsString('layui.table.reload', $active, 'dialog 条件内应有激活的表格重载');
    }

    public function testCommonBodyContainsFormAndScript()
    {
        $form = file_get_contents($this->tplDir . DIRECTORY_SEPARATOR . '_form.html');

        $this->assertStringContainsString('id="form-{$name_space}"', $form, '公共主体应包含表单容器');
        $this->assertStringContainsString('{include file="$key_path"/}', $form, '公共主体应渲染表单项');
        $this->assertStringContainsString('form.verify', $form, '公共主体应包含校验规则');
        $this->assertStringContainsString('form.on(\'submit({$filter})\'', $form, '公共主体应包含提交处理');
        $this->assertStringContainsString('{volist name=\'triggers\'', $form, '公共主体应包含触发器绑定');
    }

    public function testOriginalTemplatesPreservedAsBak()
    {
        foreach (['edit.html.bak', 'dialog.html.bak'] as $bak) {
            $this->assertFileExists($this->tplDir . DIRECTORY_SEPARATOR . $bak, "合并前的原始文件应保留: {$bak}");
            $content = file_get_contents($this->tplDir . DIRECTORY_SEPARATOR . $bak);
            // 原始文件应包含完整表单主体特征（而非合并后的壳）
            $this->assertStringContainsString('id="form-{$name_space}"', $content);
        }
    }
}
