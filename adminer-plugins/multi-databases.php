<?php

/**
 * A plugin for Adminer that supports independent account configuration for database connections.
 *
 * @link https://github.com/xuanyan/adminer-multi-databases
 * @author Yan Xuan, https://out-man.top
 */

namespace Adminer;

class MultiDatabases extends Plugin {
    private $databases;
  
    public function __construct($config) {
        $this->databases = $config;
    }
    function name() {
        // custom name in title and heading
        return @$this->databases[@$_GET['username']]['desc'];
    }

    function credentials() {
      return @$this->databases[@$_GET['username']]['dsn'];
    }

    function login($login, $password) {

      return ($password == @$this->databases[@$_GET['username']]['password']);
    }

    function permanentLogin($create = false) {
      // 稳定密钥:用于加密 adminer_permanent cookie 中保存的登录密码,
      // 配合表单中的 auth[permanent] 实现"登录一次,此后直达"
      return 'cc-adminer-multi-databases-v1';
    }

    function headers() {
      // 滚动续期:核心只在显式登录时写一次 adminer_permanent(30 天),
      // 这里在每次页面渲染前重发同名 cookie,把有效期从"当前"起顺延 30 天,
      // 只要 30 天内访问过一次就不会掉登录。
      // 守卫:仅当当前账号的会话密码有效时续期 —— auth_error 页面(登录失败/
      // 暴力锁定/永久状态损坏)核心会 set_password(...,null)+unset_permanent()
      // 清掉当前条目,此时绝不复活原 cookie,避免登录失败又被续期而死循环。
      // 再限 GET:登录 POST 上核心会写入更新后的 adminer_permanent,
      // 此处重发请求开始时读到的旧值会把它覆盖掉。
      if ($_SERVER['REQUEST_METHOD'] === 'GET'
        && !empty($_COOKIE['adminer_permanent'])
        && isset($_GET['username']) && is_string(get_password())
      ) {
        cookie('adminer_permanent', $_COOKIE['adminer_permanent'], 2592000);
      }
    }

    function loginForm() {
        $databases = [];
        $quickSelect = ['' => ''];
        $groups = [];
        foreach ($this->databases as $name => $config) {
            $databases[$name] = array(
                'db' => (string) @$config['database'],
                'driver' => (!empty($config['driver']) ? $config['driver'] : 'server'),
                'passwordless' => empty($config['password']),
            );
            if (empty($config['password'])) {
                $quickSelect[$name] = $name;
            }
            $at = strpos($name, '@');
            $group = ($at === false ? '' : substr($name, $at + 1));
            $groups[$group][$name] = ($at === false ? $name : substr($name, 0, $at));
        }
        count($quickSelect) == 1 && $quickSelect = [];
?>
<?php if ($groups) { ?>
<fieldset id='quick-login' style='font-size:200%'>
  <legend>Quick login</legend>
  <div style='display:flex;flex-wrap:wrap;gap:0.6em 1.5em;align-items:flex-start'>
<?php foreach ($groups as $group => $items) { ?>
    <table class='odds' style='width:auto;margin:0'>
      <thead><tr><th><?= h($group !== '' ? $group : 'other') ?></th></tr></thead>
      <tbody>
<?php foreach ($items as $name => $label) { ?>
        <tr><td><a href="?username=<?= urlencode($name) ?>" data-user="<?= h($name) ?>"><?= h($label) ?></a></td></tr>
<?php } ?>
      </tbody>
    </table>
<?php } ?>
  </div>
</fieldset>
<?php } ?>
<table class='layout'>
  <?= input_hidden('auth[driver]', DRIVER); ?>
  <?= input_hidden('auth[db]', ''); ?>
  <?= input_hidden('auth[permanent]', 1); ?>
  <?php if ($quickSelect) { echo adminer()->loginFormField('quick-select', '<tr><th>'.lang('select').'<td>', html_select('mySelect', $quickSelect));} ?>
  <?= adminer()->loginFormField('username', '<tr><th>'.lang('Username').'<td>', '<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'); ?>
  <?= adminer()->loginFormField('password', '<tr><th>'.lang('Password').'<td>', '<input type="password" name="auth[password]" autocomplete="current-password">'); ?>
</table>
<p><input id="myLogin" type="button" value="<?=lang('Login'); ?>"></p>
<script<?= nonce(); ?>>
var databases = <?= json_encode($databases); ?>;
function quickLogin(name) {
  var cfg = databases[name] || {};
  qs('input[name="auth[username]"]').value = name;
  if (cfg.passwordless) {
    qs('#myLogin').click();
  } else {
    qs('input[name="auth[password]"]').focus();
  }
}
var mySelect = qs('select[name="mySelect"]');
if (mySelect) {
  mySelect.onchange = function() {
    if (mySelect.value != '') {
      quickLogin(mySelect.value);
    }
  };
}
var accounts = qs('#quick-login');
if (accounts) {
  accounts.onclick = function(e) {
    var a = e.target;
    while (a && a.tagName != 'A') a = a.parentNode;
    if (!a || !a.getAttribute('data-user')) return;
    e.preventDefault();
    quickLogin(a.getAttribute('data-user'));
  };
}
qs('#myLogin').onclick = function() {
  var username = qs('input[name="auth[username]"]').value;
  var cfg = databases[username] || {};
  qs('input[name="auth[driver]"]').value = cfg.driver || 'server';
  qs('input[name="auth[db]"]').value = cfg.db || '';
  qs('form').submit();
};
qs('input[name="auth[password]"]').onkeydown = function(e) {
  if (e.keyCode == 13) {
    qs('#myLogin').click();
  }
}
// 方案A:直接访问 ?username=xxx 登录页时,免密账号每个会话自动提交一次(配合 auth[permanent] 永久 cookie)
var initUser = <?= json_encode((string) @$_GET['username']); ?>;
if (initUser && databases[initUser]) {
  try {
    var autoFlag = 'al:' + initUser;
    if (databases[initUser].passwordless && !sessionStorage.getItem(autoFlag)) {
      sessionStorage.setItem(autoFlag, '1');
      quickLogin(initUser);
    }
  } catch (e) {}
}
</script>
<?php
        return true;
    }
}