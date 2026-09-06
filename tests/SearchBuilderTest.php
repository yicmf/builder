<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\table\SearchBuilder;

/**
 * SearchBuilder（2026-09-06 拆分重构自 Table 的 search* 系列搜索构建器）单元测试
 *
 * 直接实例化 SearchBuilder（无框架依赖），覆盖搜索配置写入、
 * 查询条件组装（searchWhere）、排序解析（searchOrder）与 userSearch 回调解耦。
 */
class SearchBuilderTest extends TestCase
{
    /**
     * 构造模拟 Request 桩：实现 searchWhere/searchOrder 用到的
     * has() / param() / except() 三个方法（与 BuilderTest 的 fakeRequest 一致）
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

    // ==================== 搜索配置写入 ====================

    public function testSearchTextAddsEqualityTextSearch()
    {
        $builder = new SearchBuilder();
        $ret = $builder->searchText('title', '标题', '请输入标题', '默认值', ['class' => 'w']);

        $this->assertInstanceOf(SearchBuilder::class, $ret);
        $search = $builder->getSearch();
        $this->assertCount(1, $search);
        $this->assertSame('title', $search[0]['field']);
        $this->assertSame('text', $search[0]['type']);
        $this->assertSame('=', $search[0]['condition']);
        $this->assertSame('标题', $search[0]['title']);
        $this->assertSame('请输入标题', $search[0]['placeholder']);
        $this->assertSame('默认值', $search[0]['value']);
    }

    public function testSearchTextLikeUsesLikeCondition()
    {
        $builder = new SearchBuilder();
        $builder->searchTextLike('nickname', '昵称');

        $search = $builder->getSearch();
        $this->assertSame('like', $search[0]['condition']);
        $this->assertSame('支持模糊搜索', $search[0]['placeholder']);
    }

    public function testSearchTextInUsesInCondition()
    {
        $builder = new SearchBuilder();
        $builder->searchTextIn('ids', '编号');

        $search = $builder->getSearch();
        $this->assertSame('in', $search[0]['condition']);
    }

    public function testSearchUserUsesSearchUserCondition()
    {
        $builder = new SearchBuilder();
        $builder->searchUser('用户');

        $search = $builder->getSearch();
        $this->assertSame('search_user', $search[0]['condition']);
        $this->assertSame('user_id', $search[0]['field']);
    }

    public function testSearchSelectAddsSelectSearch()
    {
        $builder = new SearchBuilder();
        $options = [['id' => 1, 'value' => '启用']];
        $builder->searchSelect('status', '状态', $options);

        $search = $builder->getSearch();
        $this->assertSame('status', $search[0]['field']);
        $this->assertSame('select', $search[0]['type']);
        $this->assertSame('=', $search[0]['condition']);
        $this->assertSame('状态', $search[0]['title']);
        $this->assertSame($options, $search[0]['options']);
    }

    public function testSearchDateAddsDatepickerBetweenSearch()
    {
        $builder = new SearchBuilder();
        $ret = $builder->searchDate('create_time', '创建时间');

        $this->assertInstanceOf(SearchBuilder::class, $ret);
        $search = $builder->getSearch();
        $this->assertSame('create_time', $search[0]['field']);
        $this->assertSame('datepicker', $search[0]['type']);
        $this->assertSame('between', $search[0]['condition']);
        $this->assertSame('创建时间', $search[0]['title']);
        $this->assertSame('yyyy-MM-dd', $search[0]['options']['format']);
    }

    public function testSearchDateTimeRangeDelegatesToSearchDateWithDatetime()
    {
        $builder = new SearchBuilder();
        $builder->searchDateTimeRange('create_time', '创建时间');

        $search = $builder->getSearch();
        $this->assertSame('between', $search[0]['condition']);
        $this->assertSame('yyyy-MM-dd HH:mm:ss', $search[0]['options']['format']);
        $this->assertTrue($search[0]['options']['range']);
    }

    public function testSearchBoolDelegatesToSearchSelect()
    {
        $builder = new SearchBuilder();
        $builder->searchBool('status', '是否');

        $search = $builder->getSearch();
        $this->assertSame('select', $search[0]['type']);
        $this->assertSame('=', $search[0]['condition']);
        $this->assertSame([['id' => 0, 'value' => '否'], ['id' => 1, 'value' => '是']], $search[0]['options']);
    }

    public function testSearchAddsGenericSearch()
    {
        $builder = new SearchBuilder();
        $builder->search();

        $search = $builder->getSearch();
        $this->assertSame('搜索', $search[0]['title']);
        $this->assertSame('key', $search[0]['field']);
        $this->assertSame('text', $search[0]['type']);
        $this->assertSame('=', $search[0]['condition']);
    }

    public function testPushSearchAppendsCustomItem()
    {
        $builder = new SearchBuilder();
        $builder->searchText('title', '标题');
        $ret = $builder->pushSearch([
            'field' => 'tag_id',
            'type' => 'hidden',
            'condition' => 'in',
            'value' => '',
        ]);

        $this->assertInstanceOf(SearchBuilder::class, $ret);
        $search = $builder->getSearch();
        $this->assertCount(2, $search);
        $this->assertSame('tag_id', $search[1]['field']);
        $this->assertSame('hidden', $search[1]['type']);
        $this->assertSame('in', $search[1]['condition']);
    }

    // ==================== URL 配置 ====================

    public function testUrlGettersAndSetters()
    {
        $builder = new SearchBuilder();
        $this->assertSame('', $builder->getSearchPostUrl());
        $this->assertSame('', $builder->getSelectPostUrl());
        $this->assertSame('', $builder->getSetClearUrl());

        $builder->setSearchPostUrlValue('/admin/list');
        $this->assertSame('/admin/list', $builder->getSearchPostUrl());

        $builder->setClearUrl('/admin/recycle/clear');
        $this->assertSame('/admin/recycle/clear', $builder->getSetClearUrl());
    }

    // ==================== searchWhere ====================

    public function testSearchWhereLikeCondition()
    {
        $builder = new SearchBuilder();
        $builder->request = $this->fakeRequest(['field' => ['title' => 'abc']]);
        $builder->searchTextLike('title', '标题');

        $this->assertSame([['title', 'like', '%abc%']], $builder->searchWhere(['title', 'status']));
    }

    public function testSearchWhereEqualityCondition()
    {
        $builder = new SearchBuilder();
        $builder->request = $this->fakeRequest(['field' => ['status' => '1']]);
        $builder->searchSelect('status', '状态');

        $this->assertSame([['status', '=', '1']], $builder->searchWhere(['title', 'status']));
    }

    public function testSearchWhereInCondition()
    {
        $builder = new SearchBuilder();
        $builder->request = $this->fakeRequest(['field' => ['status' => [1, 2]]]);
        $builder->searchTextIn('status', '状态');

        $this->assertSame([['status', 'in', [1, 2]]], $builder->searchWhere(['title', 'status']));
    }

    public function testSearchWhereSkipsFieldNotInDbFields()
    {
        $builder = new SearchBuilder();
        $builder->request = $this->fakeRequest(['field' => ['hacker' => 'x']]);
        $builder->searchText('hacker', '注入');

        $this->assertSame([], $builder->searchWhere(['title', 'status']));
    }

    public function testSearchWhereAppendsUrlQueryField()
    {
        $builder = new SearchBuilder();
        $builder->request = $this->fakeRequest(['status' => '1']);

        $this->assertSame([['status', '=', '1']], $builder->searchWhere(['title', 'status']));
    }

    public function testSearchWhereFilterSosInMode()
    {
        $filterSos = json_encode([['mode' => 'in', 'field' => 'status', 'values' => [1, 2]]]);
        $builder = new SearchBuilder();
        $builder->request = $this->fakeRequest(['filterSos' => $filterSos]);

        $this->assertSame([['status', 'in', [1, 2]]], $builder->searchWhere(['title', 'status']));
    }

    public function testSearchWhereUserSearchCallbackInjection()
    {
        $builder = new SearchBuilder(function ($keyword) {
            $this->assertSame('张三', $keyword);

            return [5, 6];
        });
        $builder->request = $this->fakeRequest(['field' => ['user_id' => '张三']]);
        $builder->searchUser('用户');

        $this->assertSame([['user_id', 'in', [5, 6]]], $builder->searchWhere(['user_id']));
    }

    // ==================== searchOrder ====================

    public function testSearchOrderFromRequest()
    {
        $builder = new SearchBuilder();
        $request = $this->fakeRequest(['order_field' => 'sort', 'order' => 'asc']);

        $this->assertSame('sort asc', $builder->searchOrder($request, null));
    }

    public function testSearchOrderFallsBackToIdDesc()
    {
        $builder = new SearchBuilder();
        $request = $this->fakeRequest([]);

        $this->assertSame('id DESC', $builder->searchOrder($request, null));
    }

    public function testSearchOrderUsesConfiguredOrder()
    {
        $builder = new SearchBuilder();
        $request = $this->fakeRequest([]);

        $this->assertSame('create_time DESC', $builder->searchOrder($request, 'create_time DESC'));
    }
}
