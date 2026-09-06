<?php

namespace yicmf\builder\tests;

use PHPUnit\Framework\TestCase;
use yicmf\builder\Table;

/**
 * 解耦收尾测试（2026-09-06）：authCheck 回调三级解析
 * 1) 实例回调（setAuthChecker） 2) 静态默认回调（项目服务提供者注入） 3) 均未注入时放行
 *
 * 注意：包内已不存在 app\ucenter\event\AuthGroup 依赖（原默认实现迁至项目侧
 * app\common\service\BuilderService），本测试用哑回调验证解析顺序与放行兜底。
 */
class AuthDecoupleTest extends TestCase
{
    private Table $table;

    protected function setUp(): void
    {
        $this->table = $this->makeTable();
    }

    protected function tearDown(): void
    {
        // 每个用例后清空静态默认回调，避免用例间串扰
        Table::setDefaultAuthChecker(null);
        unset($this->table);
    }

    private function makeTable(): Table
    {
        $ref = new \ReflectionClass(Table::class);

        return $ref->newInstanceWithoutConstructor();
    }

    public function testFallbackAllowsWhenNothingInjected()
    {
        Table::setDefaultAuthChecker(null);

        $this->assertTrue($this->table->authCheck('/admin/Seo/index'));
    }

    public function testStaticDefaultCheckerIsUsed()
    {
        $called = [];
        Table::setDefaultAuthChecker(function ($url, $user) use (&$called) {
            $called[] = [$url, $user];

            return $url === 'allowed';
        });

        // 设置真实用户（_user 为假时静态分支会短路放行，见下一用例）
        $ref = new \ReflectionProperty(Table::class, '_user');
        $ref->setAccessible(true);
        $ref->setValue($this->table, (object) ['id' => 7]);

        // 静态分支会先做 URL 规整：去 query、去 .html、去前导斜杠
        $this->assertTrue($this->table->authCheck('/allowed?id=1'));
        $this->assertFalse($this->table->authCheck('/denied.html'));
        $this->assertSame('allowed', $called[0][0]);
        $this->assertSame('denied', $called[1][0]);
        $this->assertCount(2, $called);
    }

    public function testStaticDefaultSkippedWhenUserEmpty()
    {
        $called = 0;
        Table::setDefaultAuthChecker(function () use (&$called) {
            $called++;

            return false;
        });

        $this->assertTrue($this->table->authCheck('/anything'));
        $this->assertSame(0, $called, '_user 为假时不应触发静态回调');
    }

    public function testInstanceCheckerOverridesStaticDefault()
    {
        Table::setDefaultAuthChecker(function () {
            return false; // 静态默认：一律拒绝
        });
        $this->table->setAuthChecker(function () {
            return true; // 实例回调：一律放行，应优先生效
        });

        $this->assertTrue($this->table->authCheck('/anything'));
    }

    public function testStaticDefaultReceivesInstanceUser()
    {
        $user = (object) ['id' => 1];
        // 反射设置 _user（未走构造函数）
        $ref = new \ReflectionProperty(get_parent_class(Table::class), 'view_data');
        // _user 声明于 Table 自身而非 Builder
        $ref = new \ReflectionProperty(Table::class, '_user');
        $ref->setAccessible(true);
        $ref->setValue($this->table, $user);

        $received = null;
        Table::setDefaultAuthChecker(function ($url, $u) use (&$received) {
            $received = $u;

            return true;
        });

        $this->table->authCheck('/x');
        $this->assertSame($user, $received);
    }

    public function testPackageHasNoActiveAppImports()
    {
        // 包源码内不允许再出现活跃的 app\* 导入（解耦约束，注释保留除外）
        $srcDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src';
        $files = array_merge(
            glob($srcDir . '/*.php'),
            glob($srcDir . '/table/*.php'),
            glob($srcDir . '/edit/*.php')
        );
        foreach ($files as $file) {
            foreach (file($file) as $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, 'use app\\')) {
                    $this->fail(basename($file) . ' 出现活跃的 app\\* 导入: ' . trim($line));
                }
            }
        }
        $this->addToAssertionCount(1);
    }
}
