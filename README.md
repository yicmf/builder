# builder

yicmf 后台表单构建器（layuiAdmin 风格）。结构：`Edit::fetch` 选壳（`edit.html` 页 / `dialog.html` 弹窗）
→ 公共体 `_form.html` → `{include $key_path}` 路由到 `_key.html` → `tpl/key/<type>.html`。

> 本仓库是**独立 git 仓库**。改动须在 `D:/www/builder` 内 commit 并 push，否则 `composer update` 拉取远端会覆盖本地改动；
> 源码映射：`vendor/yicmf/builder` 由本目录同步。

## 致命坑：模板 `<script>` 被挤出包裹 div（静默失效）

layuiAdmin 的 `view.parse()` 注入逻辑：

```js
html = '<div>' + html + '</div>';
that.container.html(elem.children());   // 只取「包裹 div 的子元素」
```

若模板在 `</form>` 之后多出收尾标签（如多余的 `</div>`），解析时会**提前闭合这个临时包裹 `<div>`**，
紧随其后的表单 `<script>`（`layui.define([...], fn)` → `form.on('submit(<filter>)', ...)` 注册）会落到包裹 div **之外**，
**不在 `elem.children()` 里 → 永不插入 DOM → 永不执行**。

### 症状（真实案例）
后台某表单点「保存」：
- **首次** → 页面直接跳转、URL 带上表单参数（浏览器原生 GET 提交）
- **再次** → 完全无反应

根因链：表单 JS 未执行 → `form.on('submit')` 未注册 → layui 查不到处理器返回 `null`（非 `false`）→ 未 `preventDefault`
→ `<button type="submit" lay-submit>` 触发原生 GET 提交。

**这是静默失效——控制台不报错、模板渲染正常，只有 JS 整段不跑。**

### 已发生的回归与修复

> 历史 commit `10c872c`（曾在包内 commit，注意确认是否已 push，否则 `composer update` 会覆盖）。

1. `_form.html`（`edit`/`dialog` 合并产物）在 `</form>` 之后**多继承了 5 个收尾 `</div>`**（来自 `edit.html.bak` 的卡片容器收尾）。
   修复：删除这 5 个 `</div>`（用 `{/* */}` 注释留痕，编译期剥除、不进输出），尾部与 `dialog.html.bak` 一致；
   各壳各自收尾（`dialog.html` 无容器；`edit.html` 自行收尾），互不越界。
2. 拆分时把 `lay-verify="{$field['verify']|raw}"` 截断成 `lay-verify="{$field['verify']"`（丢了 `]|raw}`），
   渲染出的属性值变成字面模板文本，layui 查不到校验规则 → 该字段必填/格式校验被**静默跳过**。
   修复 9 个文件：`key/number|url|textarea|default|email|select|switch|checkbox|belongTo.html`。

### 诊断方法（复发时照做）

- 渲染后检查 `elem.children()` 是否包含表单 `<script>`；`$(html).length` 现状 = 2（脚本被挤出）即异常，
  修复后应为 1、`children = [FORM, SCRIPT]`。
- 用 headless 浏览器复刻 layuiAdmin 注入路径 `layui.view('容器').parse('<div>'+tpl+'</div>')`，
  在捕获阶段 `document.addEventListener('submit', fn, true)` 判定是否发生原生 GET 提交。
- 关键验证点：`window.closeTab` 是否已定义（表单 JS 是否执行）、服务端是否出现对应 `POST`（走回 layui ajax）。

### ⚠️ 改模板后必清缓存

think-template 按「路径 + include 文件 mtime」缓存（`runtime/temp`）。改了 `_form.html` / `key/*.html` 后
**必须清 `runtime/temp`**，否则继续用旧产物。

## 约定

- `{switch}`（`_key.html`）内只允许 `case`/`default` 与空白行；`{include}` 编译期内联，路径须为单一变量。
- 新增/修改方法遵循项目留痕规范（方法首行加「调整记录」注释）。