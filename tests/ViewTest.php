<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\View;

/**
 * View 单元测试（无框架依赖）
 *
 * 通过反射创建 View 实例（跳过构造函数以避免 app() 容器依赖），
 * 覆盖 group/groups、button/buttonBack、key/keyLabel 结构、title/tip/suggest/warning
 * 链式、data 以及 compileHtmlAttr 的 XSS 转义。
 *
 * 不测依赖框架的方法：fetch（Db/View）、convertKey（app['view']）、
 * keyClosure（Pinyin/text()）。
 */
class ViewTest extends TestCase
{
    // ==================== 测试基础设施 ====================

    private function makeView(): View
    {
        $ref = new \ReflectionClass(View::class);

        return $ref->newInstanceWithoutConstructor();
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

    // ==================== group / groups ====================

    public function testGroupWithStringList()
    {
        $view = $this->makeView();

        $result = $view->group('base', 'a,b,c');

        $this->assertSame($view, $result);
        $this->assertSame(['base' => ['a', 'b', 'c']], $this->getProp($view, '_group'));
    }

    public function testGroupWithArrayList()
    {
        $view = $this->makeView();

        $view->group('base', ['a', 'b']);

        $this->assertSame(['base' => ['a', 'b']], $this->getProp($view, '_group'));
    }

    public function testGroupsBulk()
    {
        $view = $this->makeView();

        $result = $view->groups(['g1' => ['a', 'b'], 'g2' => 'c,d']);

        $this->assertSame($view, $result);
        $this->assertSame(['g1' => ['a', 'b'], 'g2' => ['c', 'd']], $this->getProp($view, '_group'));
    }

    // ==================== button / buttonBack ====================

    public function testButtonAppendsToList()
    {
        $view = $this->makeView();

        $result = $view->button('导出', ['url' => '/admin/x/export']);

        $this->assertSame($view, $result);
        $list = $this->getProp($view, '_buttonList');
        $this->assertCount(1, $list);
        $this->assertSame('导出', $list[0]['title']);
        $this->assertSame(['url' => '/admin/x/export'], $list[0]['attr']);
    }

    public function testButtonBackHasCloseAttr()
    {
        $view = $this->makeView();

        $view->buttonBack('关闭');

        $list = $this->getProp($view, '_buttonList');
        $this->assertSame('关闭', $list[0]['title']);
        $this->assertSame('button', $list[0]['attr']['type']);
        $this->assertSame('close', $list[0]['attr']['data-icon']);
        $this->assertSame('btn-close', $list[0]['attr']['class']);
    }

    // ==================== key / keyLabel ====================

    public function testKeyBuildsKeyListEntry()
    {
        $view = $this->makeView();

        $result = $view->key('name', '名称', '副标题', 'label', ['opt' => 1]);

        $this->assertSame($view, $result);
        $list = $this->getProp($view, '_keyList');
        $this->assertCount(1, $list);
        $this->assertSame('name', $list[0]['name']);
        $this->assertSame('名称', $list[0]['title']);
        $this->assertSame('副标题', $list[0]['subtitle']);
        $this->assertSame('label', $list[0]['type']);
        $this->assertSame(['opt' => 1], $list[0]['opt']);
    }

    public function testKeyLabelDelegatesToKeyWithLabelType()
    {
        $view = $this->makeView();

        $view->keyLabel('status', '状态');

        $list = $this->getProp($view, '_keyList');
        $this->assertSame('status', $list[0]['name']);
        $this->assertSame('状态', $list[0]['title']);
        $this->assertSame('label', $list[0]['type']);
    }

    // ==================== 标题区链式 ====================

    public function testTitleTipSuggestWarning()
    {
        $view = $this->makeView();

        $result = $view->title('详情')
            ->tip('提示A')
            ->suggest('建议B')
            ->warning('警告C');

        $this->assertSame($view, $result);
        $this->assertSame('详情', $this->getProp($view, '_title'));
        $this->assertSame(['提示A'], $this->getProp($view, '_explaints'));
        $this->assertSame('建议B', $this->getProp($view, '_suggest'));
        $this->assertSame('警告C', $this->getProp($view, '_warning'));
    }

    public function testDataSetsAndReturnsSelf()
    {
        $view = $this->makeView();

        $result = $view->data(['id' => 5]);

        $this->assertSame($view, $result);
        $this->assertSame(['id' => 5], $this->getProp($view, '_data'));
    }

    // ==================== compileHtmlAttr（XSS 转义，View 上下文） ====================

    public function testCompileHtmlAttrEscapesInViewContext()
    {
        $view = $this->makeView();

        $html = $this->invoke($view, 'compileHtmlAttr', [
            ['class' => 'btn', 'title' => 'He said "hi" & <b>'],
        ]);

        // button attr 经此编译，必须转义双引号与标签，杜绝 XSS
        $this->assertStringContainsString('class="btn"', $html);
        $this->assertStringContainsString('title="He said &quot;hi&quot; &amp; &lt;b&gt;"', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }
}
