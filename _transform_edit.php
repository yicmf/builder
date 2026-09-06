<?php
// 2026-09-06 一次性拆分重构脚本：Edit.php key* 系列 -> edit/FormItemBuilder
// 用后即删。仅处理 CRLF、tab 缩进的 Edit.php，保持原方法体逐行注释保留。

$editPath = __DIR__ . '/src/Edit.php';
$newPath = __DIR__ . '/src/edit/FormItemBuilder.php';

$src = file_get_contents($editPath);
if (substr_count($src, "\r\n") !== substr_count($src, "\n")) {
    fwrite(STDERR, "FATAL: Edit.php 存在孤立 \\n，中止以防行粘连\n");
    exit(1);
}
$lines = explode("\r\n", $src);
$n = count($lines);

// 迁移清单（Edit.php 源码顺序）
$migrate = [
    'keyHtml', 'keyHidden', 'keyReadOnly', 'keyCopy', 'keyText', 'keyIcp', 'keyTextInline',
    'keySafeCheck', 'keyArray', 'keyTitle', 'keyAmap', 'keyRadio', 'keyBool', 'keySwitch',
    'keySex', 'keyBelongsToMany', 'keyBelongTo', 'keySelect', 'keySelectMultiple',
    'keySelectMultistage', 'keyStatus', 'keyCheckBox', 'keyTextArea', 'keyLabel', 'keyClosure',
    'keyPassword', 'keyUrl', 'keyColor', 'keyDragsortLi', 'keyTags', 'keySlider', 'keyDecimal',
    'keySort', 'keyNumber', 'keyTimeCycle', 'keyEmail', 'keyMobile', 'keyRate', 'keyVditor',
    'keyTime', 'keyDateTime', 'keyDate', 'keyDateTimeRange', 'keyDateRange', 'keyTimeRange',
    'keyShowImg', 'keyVoice', 'keyVideo', 'keyImage', 'keyImageModel', 'keyImageMultiple',
    'keyImageMultipleModel', 'keyImageShowMultiple', 'keyAuth', 'keyAttachment',
    'keyAttachmentModel', 'keyAttachmentMultiple', 'keyCity', 'setKeys', 'key', 'keys',
];

// 上下文依赖黑名单：命中则拒绝迁移（任务规则 4）
$ctxPatterns = ['\$this->url\(', '(?<![a-zA-Z0-9_$])url\s*\(', '\$this->module', '\$this->request', '\$this->app'];

/** 去掉行内字符串与注释，返回用于括号计数的安全文本 */
function stripLine($line)
{
    $s = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "''", $line);
    $s = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $s);
    $s = preg_replace('/\/\/.*$/', '', $s);
    return $s;
}

/** 向上收集紧邻的注释块（docblock + 单行注释），返回（自上而下的）行数组与起始下标 */
function collectDocblock(array $lines, $sigIdx)
{
    $i = $sigIdx - 1;
    while ($i >= 0 && trim($lines[$i]) === '') {
        $i--;
    }
    $block = [];
    while ($i >= 0) {
        $t = ltrim($lines[$i]);
        if (strpos($t, '*') === 0 || strpos($t, '//') === 0 || strpos($t, '/**') === 0) {
            $block[] = $lines[$i];
            $i--;
        } else {
            break;
        }
    }
    $block = array_reverse($block);
    return [$block, $i + 1];
}

// 1. 定位每个迁移方法的 [docStart, sigIdx, openIdx, closeIdx]
$found = [];
foreach ($migrate as $name) {
    $hits = [];
    for ($i = 0; $i < $n; $i++) {
        if (preg_match('/^(\s*)(public|protected|private)\s+function\s+' . preg_quote($name, '/') . '\s*\(/', $lines[$i], $m)) {
            $hits[] = ['sig' => $i, 'indent' => $m[1], 'vis' => $m[2]];
        }
    }
    if (count($hits) !== 1) {
        fwrite(STDERR, "FATAL: {$name} 命中 " . count($hits) . " 次（期望 1）\n");
        exit(1);
    }
    $h = $hits[0];
    $open = $h['sig'] + 1;
    if (trim($lines[$open]) !== '{') {
        fwrite(STDERR, "FATAL: {$name} 签名下一行不是 {\n");
        exit(1);
    }
    $depth = 0;
    $close = -1;
    for ($i = $open + 1; $i < $n; $i++) {
        $s = stripLine($lines[$i]);
        $depth += substr_count($s, '{') - substr_count($s, '}');
        if ($depth < 0) {
            $close = $i;
            break;
        }
    }
    if ($close < 0 || trim($lines[$close]) !== '}') {
        fwrite(STDERR, "FATAL: {$name} 未找到方法收尾 }\n");
        exit(1);
    }
    list($doc, $docStart) = collectDocblock($lines, $h['sig']);
    $h['open'] = $open;
    $h['close'] = $close;
    $h['doc'] = $doc;
    $h['docStart'] = $docStart;
    $h['name'] = $name;
    $found[$name] = $h;
}

// 2. 上下文依赖检查（迁移体）
foreach ($found as $name => $h) {
    $body = implode("\n", array_slice($lines, $h['open'] + 1, $h['close'] - $h['open'] - 1));
    foreach ($ctxPatterns as $p) {
        if (preg_match('/' . $p . '/', $body)) {
            fwrite(STDERR, "FATAL: {$name} 方法体命中上下文依赖 /{$p}/，应留在 Edit\n");
            exit(1);
        }
    }
}
echo "上下文依赖检查通过（" . count($found) . " 个方法均无 url()/module/request/app 依赖）\n";

// 3. 生成 FormItemBuilder.php
$bodyIndentOf = function ($sigIndent) {
    if (strpos($sigIndent, "\t") !== false) {
        return $sigIndent . "\t";
    }
    return $sigIndent . '    ';
};

$newSrc = "<?php\n";
$newSrc .= "\n";
$newSrc .= "// +----------------------------------------------------------------------\n";
$newSrc .= "// | builder\n";
$newSrc .= "// +----------------------------------------------------------------------\n";
$newSrc .= "// | Copyright (c) 2015-2026 http://www.yicmf.com, All rights reserved.\n";
$newSrc .= "// +----------------------------------------------------------------------\n";
$newSrc .= "// | Author: 微尘 <yicmf@qq.com>\n";
$newSrc .= "// +----------------------------------------------------------------------\n";
$newSrc .= "\n";
$newSrc .= "namespace yicmf\\builder\\edit;\n";
$newSrc .= "\n";
$newSrc .= "use Overtrue\\Pinyin\\Pinyin;\n";
$newSrc .= "\n";
$newSrc .= "/**\n";
$newSrc .= " * 表单项定义构建器\n";
$newSrc .= " * 2026-09-06 拆分重构：自 Edit 迁出的 key* 系列表单项方法与表单项状态持有者\n";
$newSrc .= " * @package yicmf\\builder\\edit\n";
$newSrc .= " */\n";
$newSrc .= "class FormItemBuilder\n";
$newSrc .= "{\n";
$newSrc .= "\t/** @var array 表单项定义清单 */\n";
$newSrc .= "\tpublic \$keyList = [];\n";
$newSrc .= "\t/** @var string 默认主键字段 */\n";
$newSrc .= "\tpublic \$defaultPk = 'id';\n";
$newSrc .= "\n";
$newSrc .= "\t/**\n";
$newSrc .= "\t * @param string \$defaultPk 默认主键字段\n";
$newSrc .= "\t */\n";
$newSrc .= "\tpublic function __construct(string \$defaultPk = 'id')\n";
$newSrc .= "\t{\n";
$newSrc .= "\t\t\$this->defaultPk = \$defaultPk;\n";
$newSrc .= "\t}\n";
$newSrc .= "\n";
$newSrc .= "\t/**\n";
$newSrc .= "\t * @return array 表单项定义清单\n";
$newSrc .= "\t */\n";
$newSrc .= "\tpublic function getKeyList()\n";
$newSrc .= "\t{\n";
$newSrc .= "\t\treturn \$this->keyList;\n";
$newSrc .= "\t}\n";
$newSrc .= "\n";
$newSrc .= "\t/**\n";
$newSrc .= "\t * @return array 表单项定义清单（引用，供外部原地修改）\n";
$newSrc .= "\t */\n";
$newSrc .= "\tpublic function &getKeyListRef()\n";
$newSrc .= "\t{\n";
$newSrc .= "\t\treturn \$this->keyList;\n";
$newSrc .= "\t}\n";
$newSrc .= "\n";
$newSrc .= "\t/**\n";
$newSrc .= "\t * @return string 默认主键字段\n";
$newSrc .= "\t */\n";
$newSrc .= "\tpublic function getDefaultPk()\n";
$newSrc .= "\t{\n";
$newSrc .= "\t\treturn \$this->defaultPk;\n";
$newSrc .= "\t}\n";

foreach ($migrate as $name) {
    $h = $found[$name];
    $newSrc .= "\n";
    foreach ($h['doc'] as $d) {
        $newSrc .= rtrim($d) . "\n";
    }
    if ($name === 'key') {
        $newSrc .= "\t// 2026-09-06 拆分重构：自 Edit 迁入；可见性 protected 调整为 public 以支持 Edit 门面转发，参数签名保持原样\n";
    }
    $sig = rtrim($lines[$h['sig']]);
    if ($name === 'key') {
        $sig = preg_replace('/protected\s+function\s+key\s*\(/', 'public function key(', $sig, 1);
    }
    $newSrc .= $sig . "\n";
    $newSrc .= rtrim($lines[$h['open']]) . "\n";
    for ($i = $h['open'] + 1; $i < $h['close']; $i++) {
        $l = $lines[$i];
        $l = str_replace('$this->_keyList', '$this->keyList', $l);
        $l = str_replace('$this->_default_pk', '$this->defaultPk', $l);
        $newSrc .= rtrim($l) . "\n";
    }
    $newSrc .= rtrim($lines[$h['close']]) . "\n";
}
$newSrc .= "}\n";

if (!is_dir(dirname($newPath))) {
    mkdir(dirname($newPath), 0777, true);
}
file_put_contents($newPath, str_replace("\n", "\r\n", $newSrc));
echo "已生成 src/edit/FormItemBuilder.php\n";

// 4. Edit.php：自底向上替换方法体为两行式门面（原体逐行注释保留）
$replacements = []; // [start, end, newLines[]]
foreach ($found as $name => $h) {
    $bi = $bodyIndentOf($h['indent']);
    $new = [];
    $new[] = rtrim($lines[$h['sig']]);
    $new[] = rtrim($lines[$h['open']]);
    $new[] = $bi . '$this->formItemBuilder()->' . $name . '(...func_get_args());';
    $new[] = $bi . '// 2026-09-06 拆分重构：保持原链式语义，返回 Edit 自身';
    $new[] = $bi . 'return $this;';
    $new[] = $bi . '// 2026-09-06 拆分重构：key*系列迁至 edit/FormItemBuilder，原实现注释保留';
    $new[] = $bi . '//';
    for ($i = $h['open'] + 1; $i < $h['close']; $i++) {
        $raw = rtrim($lines[$i]);
        if (trim($raw) === '') {
            $new[] = $bi . '//';
        } else {
            $new[] = $bi . '// ' . $raw;
        }
    }
    $new[] = rtrim($lines[$h['close']]);
    $replacements[] = [$h['sig'], $h['close'], $new];
}
usort($replacements, function ($a, $b) {
    return $b[0] <=> $a[0];
});
foreach ($replacements as $r) {
    list($start, $end, $new) = $r;
    array_splice($lines, $start, $end - $start + 1, $new);
}
echo "已门面化 " . count($replacements) . " 个方法\n";
file_put_contents($editPath, implode("\r\n", $lines));
