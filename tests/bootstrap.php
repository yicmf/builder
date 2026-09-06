<?php

// +----------------------------------------------------------------------
// | builder 测试 bootstrap
// +----------------------------------------------------------------------

// 兼容两种安装方式：独立项目 vendor 目录（包安装后位于 vendor/yicmf/builder）
// 或本项目根目录 vendor 内。向上最多回溯 3 级寻找 autoload.php。
$candidates = [
    __DIR__ . '/../../../autoload.php',  // 独立安装: vendor/yicmf/builder/tests -> vendor/autoload.php
    __DIR__ . '/../../../../autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];
foreach ($candidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

// 2026-09-06 拆分重构：测试环境无 ThinkPHP 框架，补一个全局 url() 助手桩
// （Table/ButtonBuilder 的 buttonAjax 等方法依赖该框架全局函数，仅拼装 URL）
if (!function_exists('url')) {
    function url($url = '', $vars = [])
    {
        return '/' . ltrim((string) $url, '/');
    }
}
