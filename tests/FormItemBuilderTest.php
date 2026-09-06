<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Edit;
use yicmf\builder\edit\FormItemBuilder;

/**
 * FormItemBuilder 单元测试
 * 2026-09-06 拆分重构：覆盖自 Edit 迁出的 key* 系列表单项配置方法与 Edit 门面链
 */
class FormItemBuilderTest extends TestCase
{
    // ==================== 测试基础设施 ====================

    /**
     * 创建跳过构造函数的 Edit 实例（Builder::__construct 依赖 app() 容器）
     */
    private function makeEdit(): Edit
    {
        $ref = new \ReflectionClass(Edit::class);

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

    // ==================== 直测 FormItemBuilder ====================

    public function testDefaultPkIsIdByDefault()
    {
        $builder = new FormItemBuilder();

        $this->assertSame('id', $builder->getDefaultPk());
        $this->assertSame([], $builder->getKeyList());
    }

    public function testDefaultPkCanBeCustomized()
    {
        $builder = new FormItemBuilder('uid');

        $this->assertSame('uid', $builder->getDefaultPk());
        $this->assertSame('uid', $builder->defaultPk);
    }

    public function testKeyTextAppendsFieldEntry()
    {
        $builder = new FormItemBuilder();
        $result = $builder->keyText('title', '标题');

        $this->assertSame($builder, $result);
        $list = $builder->getKeyList();
        $this->assertCount(1, $list);
        $this->assertSame('title', $list[0]['field']);
        $this->assertSame('标题', $list[0]['title']);
        $this->assertSame('string', $list[0]['type']);
    }

    public function testKeySelectStoresOptions()
    {
        $builder = new FormItemBuilder();
        $options = [1 => '启用', 0 => '禁用'];
        $builder->keySelect('status', '状态', $options, '请选择', 1);

        $list = $builder->getKeyList();
        $this->assertSame('select', $list[0]['type']);
        $this->assertSame($options, $list[0]['options']);
        $this->assertSame(1, $list[0]['default']);
    }

    public function testKeyTextInlineNormalizesVerify()
    {
        $builder = new FormItemBuilder();
        $builder->keyTextInline('x', 'X', null, '', 'require,url');

        $list = $builder->getKeyList();
        $this->assertSame('inline', $list[0]['type']);
        // key() 内的 verify 规整：逗号转竖线 + require 转 required（原逻辑忠实迁移）
        $this->assertSame('required|url', $list[0]['verify']);
    }

    public function testSetKeysReplacesWhenEmptyAndMergesOtherwise()
    {
        $builder = new FormItemBuilder();
        $builder->setKeys([['field' => 'a', 'type' => 'string']]);

        $this->assertCount(1, $builder->getKeyList());

        // 非空时走 array_merge 保留原清单（原 Edit::setKeys 语义）
        $builder->setKeys([['field' => 'b', 'type' => 'string']]);
        $list = $builder->getKeyList();
        $this->assertCount(2, $list);
        $this->assertSame('a', $list[0]['field']);
        $this->assertSame('b', $list[1]['field']);
    }

    public function testKeysBuildsFromSpecList()
    {
        $builder = new FormItemBuilder();
        $builder->keys([
            ['field' => 'name', 'title' => '名称', 'type' => 'string'],
            ['field' => 'age', 'type' => 'number', 'default' => 18],
        ]);

        $list = $builder->getKeyList();
        $this->assertCount(2, $list);
        $this->assertSame('名称', $list[0]['title']);
        $this->assertSame('string', $list[0]['type']);
        $this->assertSame('age', $list[1]['field']);
        $this->assertSame(18, $list[1]['default']);
        // 未指定 size 时的默认值（原 keys() 语义）
        $this->assertSame(30, $list[1]['size']);
    }

    public function testKeyStatusDelegatesToKeySelectWithStatusField()
    {
        $builder = new FormItemBuilder();
        $builder->keyStatus();

        $list = $builder->getKeyList();
        $this->assertCount(1, $list);
        $this->assertSame('status', $list[0]['field']);
        $this->assertSame('select', $list[0]['type']);
        $this->assertArrayHasKey(-2, $list[0]['options']);
    }

    public function testKeySafeCheckAppendsTwoItems()
    {
        $builder = new FormItemBuilder();
        $builder->keySafeCheck('phone', '手机号');

        $list = $builder->getKeyList();
        $this->assertCount(2, $list);
        $this->assertSame('safe_check', $list[0]['type']);
        // 内部追加的验证码输入项（keyTextInline 互调在新类内自然生效）
        $this->assertSame('check_code', $list[1]['field']);
        $this->assertSame('inline', $list[1]['type']);
    }

    // ==================== Edit 门面链 ====================

    public function testFacadeChainReturnsSameEditInstance()
    {
        $edit = $this->makeEdit();

        $result = $edit
            ->keyText('name', '名称')
            ->keySelect('status', '状态', [1 => '启用'])
            ->keyTextInline('x', 'X');

        $this->assertSame($edit, $result);
        $builder = $this->invoke($edit, 'formItemBuilder');
        $list = $builder->getKeyList();
        $this->assertCount(3, $list);
        $this->assertSame('string', $list[0]['type']);
        $this->assertSame('select', $list[1]['type']);
        $this->assertSame('inline', $list[2]['type']);
    }

    public function testFacadeBuilderIsLazilyReused()
    {
        $edit = $this->makeEdit();
        $edit->keyText('a', 'A');

        $this->assertSame(
            $this->invoke($edit, 'formItemBuilder'),
            $this->invoke($edit, 'formItemBuilder')
        );
    }

    public function testFacadeBuilderInheritsEditDefaultPk()
    {
        $edit = $this->makeEdit();

        $builder = $this->invoke($edit, 'formItemBuilder');

        $this->assertSame('id', $builder->getDefaultPk());
    }

    public function testFacadeSetKeysAndKeysDelegate()
    {
        $edit = $this->makeEdit();
        $edit->setKeys([['field' => 'a', 'type' => 'string']]);
        $edit->keys([['field' => 'b', 'type' => 'number']]);

        $builder = $this->invoke($edit, 'formItemBuilder');
        $list = $builder->getKeyList();
        $this->assertCount(2, $list);
        $this->assertSame('a', $list[0]['field']);
        $this->assertSame('b', $list[1]['field']);
    }

    public function testFacadeStatusDelegationAppendsOneItem()
    {
        $edit = $this->makeEdit();

        $this->assertSame($edit, $edit->keyStatus());

        $list = $this->invoke($edit, 'formItemBuilder')->getKeyList();
        $this->assertCount(1, $list);
        $this->assertSame('status', $list[0]['field']);
    }

    public function testFacadeProtectedKeyWritesToBuilder()
    {
        // Edit::key 为 protected，经反射调用确认门面转发写入 FormItemBuilder
        $edit = $this->makeEdit();
        $this->invoke($edit, 'key', ['f', 'F', null, 'string']);

        $list = $this->invoke($edit, 'formItemBuilder')->getKeyList();
        $this->assertCount(1, $list);
        $this->assertSame('f', $list[0]['field']);
        $this->assertSame('string', $list[0]['type']);
    }
}
