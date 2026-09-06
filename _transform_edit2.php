<?php
// 2026-09-06 一次性拆分重构脚本（第 2 步）：Edit.php 读取点改造 + use/属性/懒加载
// 用后即删。仅处理 CRLF、tab 缩进的 Edit.php，原行注释保留 + 新行替换。

$editPath = __DIR__ . '/src/Edit.php';
$src = file_get_contents($editPath);
if (substr_count($src, "\r\n") !== substr_count($src, "\n")) {
    fwrite(STDERR, "FATAL: Edit.php 存在孤立 \\n，中止\n");
    exit(1);
}
$lines = explode("\r\n", $src);

// ---------- 单行替换定义：[精确匹配行（rtrim 后）, 新行数组] ----------
// 新行以实际行首空白（从被匹配行取）为缩进。
$singleMap = [
    // keyEditor 写入点（留在 Edit，唯一活跃 `$this->_keyList[] = $key;`）
    '$this->_keyList[] = $key;' => [
        '<NEW>$this->formItemBuilder()->keyList[] = $key; // 2026-09-06 拆分重构：改为写入FormItemBuilder</NEW>',
        '<CMT>$this->_keyList[] = $key;</CMT>',
    ],
    // fetch：ajax 分支遍历
    'foreach ($this->_keyList as $item) {' => [
        '<NEW>foreach ($this->formItemBuilder()->getKeyList() as $item) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>',
        '<CMT>foreach ($this->_keyList as $item) {</CMT>',
    ],
    // fetch：count 判断
    'if (count($this->_keyList)) {' => [
        '<NEW>if (count($this->formItemBuilder()->getKeyList())) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>',
        '<CMT>if (count($this->_keyList)) {</CMT>',
    ],
    // fetch：assign
    "\$this->assign('keyList', \$this->_keyList);" => [
        "<NEW>\$this->assign('keyList', \$this->formItemBuilder()->getKeyList()); // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>",
        "<CMT>\$this->assign('keyList', \$this->_keyList);</CMT>",
    ],
    // _formatTrigger：遍历
    'foreach ($this->_keyList as $data) {' => [
        '<NEW>foreach ($this->formItemBuilder()->getKeyList() as $data) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>',
        '<CMT>foreach ($this->_keyList as $data) {</CMT>',
    ],
    // _formatData：主键判断
    "if ('id' !== \$this->_default_pk && is_object(\$this->_data)) {" => [
        "<NEW>if ('id' !== \$this->formItemBuilder()->getDefaultPk() && is_object(\$this->_data)) { // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>",
        "<CMT>if ('id' !== \$this->_default_pk && is_object(\$this->_data)) {</CMT>",
    ],
    // _formatData：主键赋值
    '$pk = $this->_default_pk;' => [
        '<NEW>$pk = $this->formItemBuilder()->getDefaultPk(); // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>',
        '<CMT>$pk = $this->_default_pk;</CMT>',
    ],
    // _formatData：原地写回（引用沿用）
    '$this->_keyList[$key] = $e;' => [
        '<NEW>$keyListRef[$key] = $e; // 2026-09-06 拆分重构：改为写入FormItemBuilder</NEW>',
        '<CMT>$this->_keyList[$key] = $e;</CMT>',
    ],
    // _formatData：追加隐藏主键项（引用沿用）
    '$this->_keyList[] = $edit;' => [
        '<NEW>$keyListRef[] = $edit; // 2026-09-06 拆分重构：改为写入FormItemBuilder</NEW>',
        '<CMT>$this->_keyList[] = $edit;</CMT>',
    ],
];

// _formatData 主遍历：单行换成「引用取值 + 注释原行 + 新遍历」三行
$foreachDataMap = [
    'foreach ($this->_keyList as $key => $e) {' => [
        '<NEW>$keyListRef = &$this->formItemBuilder()->getKeyListRef(); // 2026-09-06 拆分重构：改为从FormItemBuilder读取</NEW>',
        '<CMT>foreach ($this->_keyList as $key => $e) {</CMT>',
        '<NEW>foreach ($keyListRef as $key => $e) {</NEW>',
    ],
];

$replacements = []; // [lineIdx, newLines[]]
foreach (array_merge($singleMap, $foreachDataMap) as $needle => $tpl) {
    $hits = [];
    for ($i = 0; $i < count($lines); $i++) {
        if (rtrim($lines[$i]) === $needle) {
            $hits[] = $i;
        }
    }
    if (count($hits) !== 1) {
        fwrite(STDERR, "FATAL: 行 `" . $needle . "` 命中 " . count($hits) . " 次（期望 1）\n");
        exit(1);
    }
    $idx = $hits[0];
    preg_match('/^[\t ]*/', $lines[$idx], $m);
    $indent = $m[0];
    $newLines = [];
    foreach ($tpl as $t) {
        if (strpos($t, '<NEW>') === 0) {
            $newLines[] = $indent . substr($t, 5);
        } elseif (strpos($t, '<CMT>') === 0) {
            $newLines[] = $indent . '// ' . substr($t, 5);
        } else {
            $newLines[] = $indent . $t;
        }
    }
    $replacements[] = [$idx, $newLines];
}

// ---------- 插入定义：[锚点精确行, 位置(before/after), 插入行数组（不含缩进，统一用文件 tab 风格手写）] ----------
$useAnchor = "\tuse think\\Model;";
$propAnchor = "\t\tprotected \$_namespace;";
$initAnchor = "\t\t * 设置请求表单头";

function findAnchor($lines, $needle, $label)
{
    $hits = [];
    for ($i = 0; $i < count($lines); $i++) {
        if (rtrim($lines[$i]) === $needle) {
            $hits[] = $i;
        }
    }
    if (count($hits) !== 1) {
        fwrite(STDERR, "FATAL: 锚点 {$label} 命中 " . count($hits) . " 次\n");
        exit(1);
    }
    return $hits[0];
}

$useIdx = findAnchor($lines, $useAnchor, 'use think\\Model;');
$propIdx = findAnchor($lines, $propAnchor, '_namespace 属性');
$initIdx = findAnchor($lines, $initAnchor, 'initialize docblock');

$inserts = [];
$inserts[] = [$useIdx, 'after', ["\tuse yicmf\\builder\\edit\\FormItemBuilder;"]];
$inserts[] = [$propIdx, 'after', [
    '',
    "\t\t/** 2026-09-06 拆分重构：表单项配置持有者 */",
    "\t\tprivate \$formItemBuilderObj;",
]];
// 懒加载方法插到 initialize() docblock（即 initIdx-1 的 /** 行）之前
$docIdx = $initIdx - 1;
if (rtrim($lines[$docIdx]) !== "\t\t/**") {
    fwrite(STDERR, "FATAL: initialize docblock 锚点异常\n");
    exit(1);
}
$inserts[] = [$docIdx, 'before', [
    "\t/**",
    "\t * 表单项构建器（懒加载，兼容跳过构造函数的测试场景）",
    "\t * @return \\yicmf\\builder\\edit\\FormItemBuilder",
    "\t */",
    "\tprivate function formItemBuilder()",
    "\t{",
    "\t\tif (!isset(\$this->formItemBuilderObj)) {",
    "\t\t\t\$this->formItemBuilderObj = new FormItemBuilder(\$this->_default_pk);",
    "\t\t}",
    "\t\treturn \$this->formItemBuilderObj;",
    "\t}",
    '',
]];

// ---------- 自底向上应用 ----------
$ops = [];
foreach ($replacements as $r) {
    $ops[] = ['idx' => $r[0], 'remove' => 1, 'new' => $r[1]];
}
foreach ($inserts as $ins) {
    list($idx, $pos, $new) = $ins;
    $ops[] = ['idx' => $idx, 'remove' => ($pos === 'before' ? 0 : 1), 'new' => $new, 'after' => ($pos === 'after')];
}
usort($ops, function ($a, $b) {
    return $b['idx'] <=> $a['idx'];
});
foreach ($ops as $op) {
    if ($op['remove'] > 0) {
        array_splice($lines, $op['idx'], $op['remove'], $op['new']);
    } else {
        array_splice($lines, $op['idx'], 0, $op['new']);
    }
}

$out = implode("\r\n", $lines);
file_put_contents($editPath, $out);

// ---------- 校验：活跃 _keyList / _default_pk 仅剩属性声明 ----------
$allowed = ["\t\tprivate \$_keyList = [];", "\t\tprotected \$_default_pk = 'id';"];
$bad = [];
foreach ($lines as $i => $l) {
    if (strpos($l, '_keyList') !== false || strpos($l, '_default_pk') !== false) {
        if (!in_array(rtrim($l), $allowed) && strpos(ltrim($l), '//') !== 0) {
            $bad[] = ($i + 1) . ': ' . $l;
        }
    }
}
if ($bad) {
    fwrite(STDERR, "WARN: 疑似遗漏的活跃引用：\n" . implode("\n", $bad) . "\n");
} else {
    echo "校验通过：活跃 _keyList/_default_pk 引用均已改造（仅属性声明与注释保留）\n";
}
echo "第 2 步完成\n";
