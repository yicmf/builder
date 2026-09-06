<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;
use yicmf\builder\table\ColumnBuilder;

/**
 * ColumnBuilder（2026-09-06 拆分重构自 Table 的 key* 系列列定义构建器）单元测试
 *
 * 直接实例化 ColumnBuilder（无框架依赖），覆盖列定义、关联预载入、
 * 统计字段与附加查询字段等核心状态写入。
 */
class ColumnBuilderTest extends TestCase
{
    // ==================== 基础列定义 ====================

    public function testKeyTextAddsNormalColumn()
    {
        $builder = new ColumnBuilder();
        $ret = $builder->keyText('title', '标题');

        $this->assertInstanceOf(ColumnBuilder::class, $ret);
        $keyList = $builder->getKeyList();
        $this->assertCount(1, $keyList);

        $found = false;
        foreach ($keyList as $key) {
            if (($key['field'] ?? null) === 'title') {
                $found = true;
                $this->assertSame('normal', $key['type']);
                $this->assertSame('标题', $key['title']);
            }
        }
        $this->assertTrue($found, 'keyList 中应包含 field=title 的列');
    }

    public function testKeyIdUsesDefaultPk()
    {
        $builder = new ColumnBuilder();
        $builder->keyId();

        $keyList = $builder->getKeyList();
        $this->assertCount(1, $keyList);
        $this->assertSame('id', $keyList[0]['field']);
        $this->assertSame('ID', $keyList[0]['title']);
    }

    public function testConstructorAcceptsCustomDefaultPk()
    {
        $builder = new ColumnBuilder('uid');
        $this->assertSame('uid', $builder->defaultPk);

        $builder->keyId();
        $this->assertSame('uid', $builder->getKeyList()[0]['field']);
    }

    // ==================== 统计字段 ====================

    public function testKeyCountRegistersCountField()
    {
        $builder = new ColumnBuilder();
        $builder->keyCount('comments', '评论数');

        $this->assertContains('comments', $builder->getCount());

        $keyList = $builder->getKeyList();
        $this->assertCount(1, $keyList);
        $this->assertSame('comments_count', $keyList[0]['field']);
    }

    // ==================== 关联字段（带点） ====================

    public function testKeyWithRelationRegistersWithAndExtraFields()
    {
        $builder = new ColumnBuilder();
        $builder->key('sub.name', '名称');

        // 关联预载入
        $with = $builder->getWith();
        $this->assertArrayHasKey('sub', $with);
        $this->assertContains('name', $with['sub']);

        // 关联外键自动追加为附加查询字段
        $extraFields = $builder->getExtraFields();
        $this->assertContains('sub_id', $extraFields);

        // 自动生成关联模板
        $templets = $builder->getTemplets();
        $this->assertNotEmpty($templets);
        $this->assertStringContainsString('sub.name', implode("\n", $templets));

        // 列定义
        $keyList = $builder->getKeyList();
        $this->assertCount(1, $keyList);
        $this->assertSame('sub.name', $keyList[0]['field']);
        $this->assertArrayHasKey('templet', $keyList[0]);
    }

    // ==================== 状态重置与读取接口 ====================

    public function testKeyLeftLeaderTogglesLeftLeader()
    {
        $builder = new ColumnBuilder();
        $builder->keyLeftLeader('checkbox');
        $this->assertSame(['type' => 'checkbox', 'fixed' => 'left'], $builder->getLeftLeader());

        $builder->keyLeftLeader(false);
        $this->assertSame([], $builder->getLeftLeader());
    }

    public function testGetKeyListRefIsSameArray()
    {
        $builder = new ColumnBuilder();
        $builder->keyText('title', '标题');

        $ref = &$builder->getKeyListRef();
        $ref[] = ['field' => 'manual', 'type' => 'normal'];

        $this->assertCount(2, $builder->getKeyList());
        $this->assertSame('manual', $builder->getKeyList()[1]['field']);
    }

    /**
     * 创建跳过构造函数的 Table 实例
     */
    private function makeTable(): Table
    {
        $ref = new \ReflectionClass(Table::class);

        return $ref->newInstanceWithoutConstructor();
    }

    // ==================== 门面链式语义回归（2026-09-06 拆分重构） ====================

    /**
     * key* 与 search*、button* 等未迁方法混接链式调用必须仍返回 Table 实例
     * （项目真实写法：->model()->where()->searchText()->keyId()->keyStatus()->actionUpdate()）
     */
    public function testFacadeChainKeepsReturningTableInstance()
    {
        $table = $this->makeTable();

        $result = $table->keyText('title', '标题')->keyId()->keyStatus();

        $this->assertSame($table, $result);
        $this->assertInstanceOf(Table::class, $result);
        // 列定义确实写入了 ColumnBuilder
        $this->assertCount(3, $this->columnsOf($table));
    }

    public function testFacadeInterleavedChain()
    {
        $table = $this->makeTable();

        $result = $table->keyId()->title('测试')->keyText('name', '名称')->setAutoRefresh(0);

        $this->assertSame($table, $result);
        $this->assertCount(2, $this->columnsOf($table));
    }

    /**
     * 通过反射取 Table 内部 columnBuilder 的 keyList
     */
    private function columnsOf(Table $table): array
    {
        $ref = new \ReflectionMethod($table, 'columnBuilder');
        $ref->setAccessible(true);

        return $ref->invoke($table)->getKeyList();
    }
}
