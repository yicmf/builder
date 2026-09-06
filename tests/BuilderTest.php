<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;

/**
 * yicmf/builder 纯逻辑单元测试（无框架依赖）
 *
 * 通过反射创建 Table 实例（跳过构造函数以避免框架容器依赖），
 * 注入模拟 Request 桩对象，覆盖查询条件组装、排序解析与 HTML 属性编译。
 *
 * 注意：被测代码位于 vendor/yicmf/builder/src，composer update 后这些测试
 * 的行为取决于包内实现是否变动（见项目改进清单第 2 项：锁版本）。
 */
class BuilderTest extends TestCase
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

    /**
     * 构造模拟 Request 桩：实现 _searchWhere/_searchOrder 用到的
     * has() / param() / except() 三个方法
     */
    private function fakeRequest(array $params = [], array $exceptReserved = []): object
    {
        return new class($params, $exceptReserved) {
            private $params;
            private $exceptReserved;

            public function __construct(array $params, array $exceptReserved)
            {
                $this->params = $params;
                $this->exceptReserved = $exceptReserved;
            }

            public function has($name)
            {
                return array_key_exists($name, $this->params);
            }

            public function param($name = null)
            {
                // 模拟 think\Request 的 'name/a'、'name/s' 后缀写法，直接按基础名取值
                $base = explode('/', $name)[0] ?? $name;

                return $this->params[$base] ?? null;
            }

            public function except($names)
            {
                $exclude = is_array($names) ? $names : explode(',', $names);

                return array_diff_key($this->params, array_flip($exclude));
            }
        };
    }

    // ==================== Builder::compileHtmlAttr（XSS 转义） ====================

    public function testCompileHtmlAttrEscapesHtml()
    {
        $table = $this->makeTable();

        $html = $this->invoke($table, 'compileHtmlAttr', [
            ['url' => '/admin/Seo/index?id=1&x=<b>', 'title' => 'He said "hi"'],
        ]);

        $this->assertStringContainsString('url="/admin/Seo/index?id=1&amp;x=&lt;b&gt;"', $html);
        $this->assertStringContainsString('title="He said &quot;hi&quot;"', $html);
        // 转义后不允许出现未转义的可注入内容
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testCompileHtmlAttrSkipsEmptyValues()
    {
        $table = $this->makeTable();

        $html = $this->invoke($table, 'compileHtmlAttr', [
            ['class' => '', 'target' => '_blank'],
        ]);

        $this->assertStringNotContainsString('class=', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function testCompileHtmlAttrSupportsPrefix()
    {
        $table = $this->makeTable();

        $html = $this->invoke($table, '_compileHtmlAttr', [
            ['url' => '/a/b'], 'data-',
        ]);

        $this->assertSame('data-url="/a/b"', $html);
    }

    public function testCompileHtmlAttrAcceptsNumericValues()
    {
        // PHP 8 下 htmlspecialchars 传非字符串会 TypeError，此用例验证 (string) 强转有效
        $table = $this->makeTable();

        $html = $this->invoke($table, 'compileHtmlAttr', [
            ['data-id' => 123, 'data-flag' => true],
        ]);

        $this->assertStringContainsString('data-id="123"', $html);
        $this->assertStringContainsString('data-flag="1"', $html);
    }

    // ==================== Table::_searchOrder ====================

    public function testSearchOrderFromRequest()
    {
        $table = $this->makeTable();
        $this->setProp($table, 'request', $this->fakeRequest([
            'order_field' => 'sort',
            'order' => 'asc',
        ]));

        $this->assertSame('sort asc', $this->invoke($table, '_searchOrder'));
    }

    public function testSearchOrderFallsBackToIdDesc()
    {
        $table = $this->makeTable();
        $this->setProp($table, 'request', $this->fakeRequest([]));

        $this->assertSame('id DESC', $this->invoke($table, '_searchOrder'));
    }

    public function testSearchOrderUsesConfiguredOrder()
    {
        $table = $this->makeTable();
        $this->setProp($table, 'request', $this->fakeRequest([]));
        // 2026-09-06 拆分重构：order 状态迁至 QueryResolver，改为向其属性赋值
        $this->invoke($table, 'queryResolver')->order = 'create_time DESC';

        $this->assertSame('create_time DESC', $this->invoke($table, '_searchOrder'));
    }

    // ==================== Table::_searchWhere ====================

    /**
     * 基础环境：model 为 null（QueryResolver 属性默认值），搜索字段以 field 为白名单
     * 2026-09-06 拆分重构：搜索配置由 Table 内的 SearchBuilder 持有，此处注入其 search 属性
     * 2026-09-06 拆分重构：查询状态迁至 QueryResolver，field 注入其属性
     */
    private function makeWhereTable(array $params, array $search = [], array $field = ['title', 'status']): Table
    {
        $table = $this->makeTable();
        $this->setProp($table, 'request', $this->fakeRequest($params));
        $searchBuilder = $this->invoke($table, 'searchBuilder');
        $searchBuilder->search = $search;
        $this->invoke($table, 'queryResolver')->field = $field;

        return $table;
    }

    public function testSearchWhereLikeCondition()
    {
        $table = $this->makeWhereTable(
            ['field' => ['title' => 'abc']],
            [['field' => 'title', 'condition' => 'like', 'type' => 'text']]
        );

        $where = $this->invoke($table, '_searchWhere');

        $this->assertSame([['title', 'like', '%abc%']], $where);
    }

    public function testSearchWhereEqualityCondition()
    {
        $table = $this->makeWhereTable(
            ['field' => ['status' => '1']],
            [['field' => 'status', 'condition' => 'eq', 'type' => 'text']]
        );

        $this->assertSame([['status', '=', '1']], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereInCondition()
    {
        $table = $this->makeWhereTable(
            ['field' => ['status' => [1, 2]]],
            [['field' => 'status', 'condition' => 'in', 'type' => 'text']]
        );

        $this->assertSame([['status', 'in', [1, 2]]], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereDatepickerBetweenCondition()
    {
        $table = $this->makeWhereTable(
            ['field' => ['create_time' => '2026-01-01 - 2026-01-31']],
            [['field' => 'create_time', 'condition' => 'between', 'type' => 'datepicker']],
            ['title', 'status', 'create_time']
        );

        $this->assertSame(
            [['create_time', 'between time', ['2026-01-01', '2026-01-31']]],
            $this->invoke($table, '_searchWhere')
        );
    }

    public function testSearchWhereSkipsFieldNotInDbFields()
    {
        // 搜索项字段不在字段白名单内，不应产生条件
        $table = $this->makeWhereTable(
            ['field' => ['hacker' => 'x']],
            [['field' => 'hacker', 'condition' => 'eq', 'type' => 'text']]
        );

        $this->assertSame([], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereSkipsEmptySearchValue()
    {
        $table = $this->makeWhereTable(
            ['field' => ['title' => '']],
            [['field' => 'title', 'condition' => 'eq', 'type' => 'text']]
        );

        $this->assertSame([], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereAppendsUrlQueryField()
    {
        // URL 上直接带字段名参数（非 field/a、非保留参数）且在白名单内时，追加等值条件
        $table = $this->makeWhereTable(['status' => '1']);

        $this->assertSame([['status', '=', '1']], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereIgnoresReservedUrlParams()
    {
        // 保留参数（page 等）不在白名单内，应被跳过；白名单外且非保留的不产生条件
        $table = $this->makeWhereTable(['page' => '2', 'v' => 'list', 'title' => 'x']);

        $this->assertSame([['title', '=', 'x']], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereFilterSosInMode()
    {
        $filterSos = json_encode([['mode' => 'in', 'field' => 'status', 'values' => [1, 2]]]);
        $table = $this->makeWhereTable(['filterSos' => $filterSos]);

        $this->assertSame([['status', 'in', [1, 2]]], $this->invoke($table, '_searchWhere'));
    }

    public function testSearchWhereWithNoParamsReturnsEmpty()
    {
        $table = $this->makeWhereTable([]);

        $this->assertSame([], $this->invoke($table, '_searchWhere'));
    }
}
