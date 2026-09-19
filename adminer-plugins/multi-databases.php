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
      if (!empty($_COOKIE['adminer_permanent'])
        && isset($_GET['username']) && is_string(get_password())
      ) {
        cookie('adminer_permanent', $_COOKIE['adminer_permanent'], 2592000);
      }
    }

    function loginForm() {
        $databases = [];
        $quickSelect = [
          '' => ''
        ];
        foreach ($this->databases as $name => $config) {
            $databases[$name] = array(
                'db' => (string) @$config['database'],
                'driver' => (!empty($config['driver']) ? $config['driver'] : 'server'),
            );
            if (empty($config['password'])) {
                $quickSelect[$name] = $name;
            }
        }
        count($quickSelect) == 1 && $quickSelect = [];
?>
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
var mySelect = qs('select[name="mySelect"]');
if (mySelect) {
  mySelect.onchange = function() {
    var username = mySelect.value;
    qs('input[name="auth[username]"]').value = username;
    if (username != '') {
      qs('#myLogin').click();
    }
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
// 方案A:直接访问 ?username=xxx 登录页时,每个会话自动提交一次(配合 auth[permanent] 永久 cookie)
var initUser = <?= json_encode((string) @$_GET['username']); ?>;
if (initUser && databases[initUser]) {
  try {
    var autoFlag = 'al:' + initUser;
    if (!sessionStorage.getItem(autoFlag)) {
      sessionStorage.setItem(autoFlag, '1');
      qs('input[name="auth[username]"]').value = initUser;
      qs('#myLogin').click();
    }
  } catch (e) {}
}
</script>
<?php
        return true;
    }
}