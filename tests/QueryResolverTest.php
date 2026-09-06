<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;
use yicmf\builder\table\QueryResolver;

/**
 * QueryResolver 单元测试
 * 2026-09-06 拆分重构：覆盖自 Table 迁出的查询配置状态与 setter
 */
class QueryResolverTest extends TestCase
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

    // ==================== 默认值 ====================

    public function testDefaultFieldIsIdAndStatus()
    {
        $resolver = new QueryResolver();

        $this->assertSame(['id', 'status'], $resolver->getField());
    }

    public function testDefaultPaginationIsTrue()
    {
        $resolver = new QueryResolver();

        $this->assertTrue($resolver->isPaginated());
    }

    // ==================== 各 setter ====================

    public function testFieldMerges()
    {
        $resolver = new QueryResolver();
        $resolver->field('x');

        $this->assertSame(['id', 'status', 'x'], $resolver->getField());
    }

    public function testWhereAccumulatesPacks()
    {
        $resolver = new QueryResolver();
        $resolver->where([['a', '=', 1]]);

        // 条件包存储整组透传参数：包1层 + 参数数组1层 + 元组
        $this->assertSame([[[['a', '=', 1]]]], $resolver->getWhere());
    }

    public function testOrder()
    {
        $resolver = new QueryResolver();
        $resolver->order('id DESC');

        $this->assertSame('id DESC', $resolver->getOrder());
    }

    public function testModel()
    {
        $resolver = new QueryResolver();
        $resolver->model('X');

        $this->assertSame('X', $resolver->getModel());
    }

    public function testTotalRowField()
    {
        $resolver = new QueryResolver();
        $resolver->totalRowField('score', '{{= d.score }}');

        $this->assertSame([['field' => 'score', 'templet' => '{{= d.score }}']], $resolver->getTotalRow());
    }

    public function testHiddenFieldMerges()
    {
        $resolver = new QueryResolver();
        $resolver->hiddenField('z');

        $this->assertSame(['z'], $resolver->getHiddenField());
    }

    public function testDataDisablesPagination()
    {
        $resolver = new QueryResolver();
        $resolver->data([], false);

        $this->assertFalse($resolver->isPaginated());
        $this->assertSame([], $resolver->getData());
    }

    public function testQuickUpdate()
    {
        $resolver = new QueryResolver();
        $resolver->quickUpdate(['title']);

        $this->assertArrayHasKey('title', $resolver->getQuickUpdate());
        $this->assertSame('text', $resolver->getQuickUpdate()['title']['option']['type']);
    }

    // ==================== Table 门面链 ====================

    public function testFacadeChainReturnsSameTableInstance()
    {
        $table = $this->makeTable();

        $result = $table
            ->model('X')
            ->where([['a', '=', 1]])
            ->order('id DESC')
            ->field('y')
            ->hiddenField('z');

        $this->assertSame($table, $result);
        $resolver = $this->invoke($table, 'queryResolver');
        $this->assertSame('X', $resolver->getModel());
        $this->assertSame([[[['a', '=', 1]]]], $resolver->getWhere());
        $this->assertSame('id DESC', $resolver->getOrder());
        $this->assertSame(['id', 'status', 'y'], $resolver->getField());
        $this->assertSame(['z'], $resolver->getHiddenField());
    }

    public function testFacadeSettersDelegateToSameResolver()
    {
        $table = $this->makeTable();
        $table->quickUpdate(['title']);
        $table->totalRowField('score', '{{= d.score }}');
        $table->data([], false);

        $resolver = $this->invoke($table, 'queryResolver');
        $this->assertArrayHasKey('title', $resolver->getQuickUpdate());
        $this->assertCount(1, $resolver->getTotalRow());
        $this->assertFalse($resolver->isPaginated());
    }

    public function testFacadeResolverIsLazilyReused()
    {
        $table = $this->makeTable();

        $this->assertSame(
            $this->invoke($table, 'queryResolver'),
            $this->invoke($table, 'queryResolver')
        );
    }
}
