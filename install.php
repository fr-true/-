<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
$frInstalled = is_file(__DIR__ . '/frEnv.php');
function frIJ($frData) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($frData, JSON_UNESCAPED_UNICODE); exit; }
$frAct = $_GET['frStep'] ?? '';
if ($frAct !== '') {
  if ($frInstalled) frIJ(['code' => 1, 'msg' => '系统已安装，如需重装请先删除根目录 frEnv.php']);
  $frIn = json_decode((string)file_get_contents('php://input'), true) ?: [];
  if ($frAct === 'env') {
    @mkdir(__DIR__ . '/frData', 0755, true);
    @mkdir(__DIR__ . '/frUploads', 0755, true);
    $frExt = fn($frN) => extension_loaded($frN);
    $frW = is_writable(__DIR__) && is_writable(__DIR__ . '/frData');
    frIJ(['code' => 0, 'items' => [
      ['name' => 'PHP 版本 >= 8.0', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>='), 'val' => '当前 ' . PHP_VERSION, 'fatal' => true],
      ['name' => 'JSON 扩展', 'ok' => $frExt('json'), 'val' => $frExt('json') ? '已加载' : '缺失', 'fatal' => true],
      ['name' => 'Mbstring 扩展', 'ok' => $frExt('mbstring'), 'val' => $frExt('mbstring') ? '已加载' : '缺失', 'fatal' => true],
      ['name' => 'PDO MySQL 扩展', 'ok' => $frExt('pdo_mysql'), 'val' => $frExt('pdo_mysql') ? '已加载' : '未加载（仅影响数据库模式）', 'fatal' => false],
      ['name' => 'Fileinfo 扩展', 'ok' => $frExt('fileinfo'), 'val' => $frExt('fileinfo') ? '已加载（上传校验更严格）' : '未加载（上传将降级校验）', 'fatal' => false],
      ['name' => '上传配置', 'ok' => true, 'val' => 'upload_max_filesize=' . ini_get('upload_max_filesize') . ' / post_max_size=' . ini_get('post_max_size'), 'fatal' => false],
      ['name' => '目录写入权限', 'ok' => $frW, 'val' => $frW ? '站点根目录与 frData 可写' : '不可写，请检查目录权限', 'fatal' => true],
    ]]);
  }
  if ($frAct === 'dbtest') {
    if (!extension_loaded('pdo_mysql')) frIJ(['code' => 1, 'msg' => '服务器未启用 PDO MySQL 扩展']);
    try {
      $frPdo = new PDO('mysql:host=' . trim((string)($frIn['host'] ?? '127.0.0.1')) . ';port=' . (int)($frIn['port'] ?? 3306) . ';charset=utf8mb4', (string)($frIn['user'] ?? ''), (string)($frIn['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
      $frVer = $frPdo->query('SELECT VERSION()')->fetchColumn();
      if (trim((string)($frIn['name'] ?? '')) !== '') $frPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', trim((string)$frIn['name'])) . '` DEFAULT CHARSET utf8mb4');
      frIJ(['code' => 0, 'msg' => '连接成功', 'ver' => $frVer]);
    } catch (Throwable $frE) { frIJ(['code' => 1, 'msg' => '连接失败：' . $frE->getMessage()]); }
  }
  if ($frAct === 'install') {
    $frDriver = ($frIn['driver'] ?? 'json') === 'mysql' ? 'mysql' : 'json';
    $frSiteName = trim((string)($frIn['siteName'] ?? ''));
    $frAu = trim((string)($frIn['adminUser'] ?? ''));
    $frAp = (string)($frIn['adminPass'] ?? '');
    if ($frSiteName === '') frIJ(['code' => 1, 'msg' => '请填写网站名称']);
    if (!preg_match('/^[A-Za-z0-9_]{3,16}$/', $frAu)) frIJ(['code' => 1, 'msg' => '管理员账号需为 3-16 位字母、数字或下划线']);
    if (strlen($frAp) < 6) frIJ(['code' => 1, 'msg' => '管理员密码至少 6 位']);
    $frTzs = ['Asia/Shanghai', 'Asia/Hong_Kong', 'Asia/Taipei', 'Asia/Tokyo', 'UTC', 'Europe/London', 'America/New_York'];
    $frEnvData = ['driver' => $frDriver, 'timezone' => in_array($frIn['tz'] ?? '', $frTzs) ? $frIn['tz'] : 'Asia/Shanghai', 'installedAt' => date('Y-m-d H:i:s')];
    if ($frDriver === 'mysql') {
      if (!extension_loaded('pdo_mysql')) frIJ(['code' => 1, 'msg' => '服务器未启用 PDO MySQL 扩展']);
      $frDb = ['host' => trim((string)($frIn['dbHost'] ?? '127.0.0.1')), 'port' => (int)($frIn['dbPort'] ?? 3306), 'name' => trim((string)($frIn['dbName'] ?? '')), 'user' => trim((string)($frIn['dbUser'] ?? '')), 'pass' => (string)($frIn['dbPass'] ?? ''), 'prefix' => preg_match('/^[A-Za-z_]{1,10}$/', (string)($frIn['dbPrefix'] ?? 'fr_')) ? strtolower((string)$frIn['dbPrefix']) : 'fr_'];
      if ($frDb['name'] === '' || $frDb['user'] === '') frIJ(['code' => 1, 'msg' => '请完整填写数据库名与用户名']);
      try {
        $frPdo = new PDO('mysql:host=' . $frDb['host'] . ';port=' . $frDb['port'] . ';charset=utf8mb4', $frDb['user'], $frDb['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $frPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $frDb['name']) . '` DEFAULT CHARSET utf8mb4');
        $frPdo->exec('USE `' . str_replace('`', '', $frDb['name']) . '`');
        $frPdo->exec('CREATE TABLE IF NOT EXISTS `' . $frDb['prefix'] . 'store` (fr_key VARCHAR(64) NOT NULL PRIMARY KEY, fr_val LONGTEXT NOT NULL, fr_updated DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
      } catch (Throwable $frE) { frIJ(['code' => 1, 'msg' => '数据库初始化失败：' . $frE->getMessage()]); }
      $frEnvData['db'] = $frDb;
    } else {
      @mkdir(__DIR__ . '/frData', 0755, true);
      if (!is_writable(__DIR__ . '/frData')) frIJ(['code' => 1, 'msg' => 'frData 目录不可写，请检查权限']);
    }
    if (@file_put_contents(__DIR__ . '/frEnv.php', "<?php\nreturn " . var_export($frEnvData, true) . ";\n", LOCK_EX) === false) frIJ(['code' => 1, 'msg' => 'frEnv.php 写入失败，请检查目录权限']);
    define('FR_INSTALLING', true);
    require_once __DIR__ . '/frCore.php';
    $frCfg = [
      'siteName' => $frSiteName,
      'slogan' => trim((string)($frIn['slogan'] ?? '')) ?: '发现好软件 · 分享高效体验',
      'keywords' => trim((string)($frIn['keywords'] ?? '')) ?: '软件仓库,软件下载,应用推荐',
      'desc' => trim((string)($frIn['desc'] ?? '')) ?: '一个干净清爽的软件下载仓库，提供软件浏览、下载、评分、收藏与投稿的一站式体验。',
      'icp' => trim((string)($frIn['icp'] ?? '')),
      'footer' => trim((string)($frIn['footer'] ?? '')) ?: '本站为软件分享平台，仅供学习交流，所有软件版权归原作者所有。',
      'contact' => trim((string)($frIn['contact'] ?? '')),
      'adminUser' => $frAu,
      'adminPass' => password_hash($frAp, PASSWORD_DEFAULT),
      'pReg' => max(0, (int)($frIn['pReg'] ?? 10)),
      'pComment' => max(0, (int)($frIn['pComment'] ?? 2)),
      'pSign' => max(0, (int)($frIn['pSign'] ?? 5)),
      'pDl' => max(0, (int)($frIn['pDl'] ?? 0)),
      'antiCrawler' => empty($frIn['antiCrawler']) ? 0 : 1,
      'rateLimit' => max(5, (int)($frIn['rateLimit'] ?? 40)),
      'enableSubmit' => empty($frIn['enableSubmit']) ? 0 : 1,
      'upMax' => max(1, min(10, (int)($frIn['upMax'] ?? 2))),
      'rewardUrl' => '',
    ];
    frSave('config', $frCfg);
    frSeed(!empty($frIn['demo']));
    frUploadInit();
    frIJ(['code' => 0, 'driver' => $frDriver]);
  }
  frIJ(['code' => 1, 'msg' => '未知请求']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-fr-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>安装向导 - 软件仓库</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%235b6cff'/%3E%3Ctext x='16' y='22' font-size='16' fill='white' text-anchor='middle' font-family='sans-serif' font-weight='bold'%3EFR%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/normalize/8.0.1/normalize.min.css">
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<script>!function(){try{var t=localStorage.getItem('frTheme');if(!t)t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.dataset.frTheme=t}catch(e){}}();</script>
<style>
*{box-sizing:border-box}
:root{--fr-p1:#5b6cff;--fr-p1b:#7b8aff;--fr-p2:#18c0b2;--fr-bg:#f3f4fa;--fr-card:rgba(255,255,255,.78);--fr-solid:#fff;--fr-tx:#191c26;--fr-tx2:#5d6470;--fr-tx3:#9aa1b0;--fr-bd:rgba(25,28,38,.08);--fr-sh:0 10px 30px rgba(25,28,38,.08);--fr-r:18px;--fr-rs:12px;--fr-warn:#e8a33d;--fr-bad:#e25c5c;--fr-ok:#3dbb7e}
html[data-fr-theme="dark"]{--fr-bg:#0e1016;--fr-card:rgba(28,31,42,.72);--fr-solid:#1b1e29;--fr-tx:#eceef4;--fr-tx2:#a7adbd;--fr-tx3:#6d7484;--fr-bd:rgba(255,255,255,.08);--fr-sh:0 10px 30px rgba(0,0,0,.35)}
body{margin:0;background:var(--fr-bg);color:var(--fr-tx);font:400 14px/1.65 -apple-system,BlinkMacSystemFont,"PingFang SC","HarmonyOS Sans SC","Microsoft YaHei",sans-serif;-webkit-font-smoothing:antialiased;transition:background .3s,color .3s}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(620px 320px at 88% -6%,rgba(91,108,255,.14),transparent 60%),radial-gradient(520px 280px at 6% 10%,rgba(24,192,178,.10),transparent 60%)}
button{font:inherit;cursor:pointer;border:none;background:none;color:inherit;padding:0}
input,select,textarea{font:inherit;color:inherit}
a{color:inherit;text-decoration:none}
.fr-glass,.fr-card{background:var(--fr-card);backdrop-filter:blur(20px) saturate(1.5);-webkit-backdrop-filter:blur(20px) saturate(1.5)}
.fr-card{border:1px solid var(--fr-bd);border-radius:var(--fr-r);box-shadow:var(--fr-sh);padding:26px}
.fr-inp{width:100%;background:var(--fr-solid);border:1px solid var(--fr-bd);border-radius:var(--fr-rs);padding:11px 14px;outline:none;transition:border-color .2s,box-shadow .2s}
.fr-inp:focus{border-color:var(--fr-p1);box-shadow:0 0 0 3px rgba(91,108,255,.15)}
.fr-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 24px;border-radius:var(--fr-rs);font-weight:600;font-size:14px;white-space:nowrap;transition:transform .15s,box-shadow .2s,opacity .2s}
.fr-btn:active{transform:scale(.96)}
.fr-btn-p{background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));color:#fff;box-shadow:0 6px 18px rgba(91,108,255,.35)}
.fr-btn-g{background:var(--fr-solid);border:1px solid var(--fr-bd);color:var(--fr-tx2)}
.fr-btn[disabled]{opacity:.5;pointer-events:none}
.fr-badge-b{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:999px;font-size:12px;background:rgba(91,108,255,.12);color:var(--fr-p1)}
#frTop{position:sticky;top:0;z-index:50;border-bottom:1px solid var(--fr-bd)}
.fr-top-in{max-width:860px;margin:auto;display:flex;align-items:center;gap:14px;padding:0 20px;height:62px}
.fr-logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:16px;flex:none}
.fr-logo-ic{width:34px;height:34px;border-radius:11px;background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));display:grid;place-items:center;color:#fff;font-size:15px;box-shadow:0 4px 12px rgba(91,108,255,.4)}
.fr-logo small{display:block;font-weight:400;font-size:11px;color:var(--fr-tx3)}
#frSteps{display:flex;gap:6px;margin-left:auto}
#frSteps b{width:26px;height:6px;border-radius:3px;background:rgba(128,132,150,.25);transition:.3s}
#frSteps b.on{background:var(--fr-p1);width:36px}
#frSteps b.done{background:var(--fr-p2)}
.fr-icon-btn{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;color:var(--fr-tx2);background:var(--fr-solid);border:1px solid var(--fr-bd);flex:none}
main{max-width:860px;margin:26px auto 130px;padding:0 20px}
.fr-step{display:none}
.fr-step.on{display:block;animation:frFade .35s ease}
@keyframes frFade{from{opacity:0;transform:translateY(12px)}}
@keyframes frPop{0%{transform:scale(.6);opacity:0}60%{transform:scale(1.08)}100%{transform:scale(1)}}
.fr-h{font-size:20px;margin:0 0 4px;display:flex;align-items:center;gap:10px}
.fr-s{color:var(--fr-tx3);font-size:13px;margin:0 0 20px}
.fr-g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:600px){.fr-g2{grid-template-columns:1fr}}
.fr-frow{margin-bottom:13px}
.fr-flabel{display:block;font-size:12px;color:var(--fr-tx2);margin-bottom:6px}
.fr-feats{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:18px 0}
@media(max-width:600px){.fr-feats{grid-template-columns:1fr}}
.fr-feat{padding:16px;border-radius:var(--fr-rs);background:var(--fr-solid);border:1px solid var(--fr-bd);display:flex;gap:12px;align-items:flex-start}
.fr-feat i{width:38px;height:38px;border-radius:12px;background:rgba(91,108,255,.1);color:var(--fr-p1);display:grid;place-items:center;font-size:16px;flex:none}
.fr-feat b{display:block;font-size:13px}
.fr-feat span{color:var(--fr-tx3);font-size:12px}
.fr-tip{padding:12px 16px;border-radius:var(--fr-rs);background:rgba(24,192,178,.08);color:var(--fr-tx2);font-size:12px;display:flex;gap:8px;align-items:center}
.fr-tip.bad{background:rgba(226,92,92,.09);color:var(--fr-bad);margin-top:14px}
.fr-env-row{display:flex;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--fr-bd)}
.fr-env-row:last-child{border:none}
.fr-env-ic{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:11px;color:#fff;flex:none}
.fr-env-ic.ok{background:var(--fr-ok)}
.fr-env-ic.warn{background:var(--fr-warn)}
.fr-env-ic.bad{background:var(--fr-bad)}
.fr-env-v{color:var(--fr-tx3);font-size:12px}
.fr-pick{display:flex;gap:14px;align-items:center;padding:18px;border-radius:var(--fr-r);border:1px solid var(--fr-bd);background:var(--fr-solid);cursor:pointer;transition:.2s;margin-bottom:12px;width:100%;text-align:left}
.fr-pick:hover{border-color:rgba(91,108,255,.5)}
.fr-pick.on{border-color:var(--fr-p1);background:rgba(91,108,255,.06);box-shadow:0 0 0 3px rgba(91,108,255,.12)}
.fr-pick-r{width:20px;height:20px;border-radius:50%;border:2px solid var(--fr-tx3);flex:none;display:grid;place-items:center}
.fr-pick.on .fr-pick-r{border-color:var(--fr-p1)}
.fr-pick.on .fr-pick-r::after{content:"";width:10px;height:10px;border-radius:50%;background:var(--fr-p1)}
.fr-pick-ic{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font-size:18px;color:#fff;flex:none}
.fr-pick b{display:block;font-size:14px}
.fr-pick span{color:var(--fr-tx3);font-size:12px}
#frDbForm{display:none;gap:12px;margin-top:4px;padding:18px;border-radius:var(--fr-r);border:1px dashed var(--fr-bd)}
#frDbForm.show{display:grid}
.fr-db-res{font-size:12px;padding:10px 14px;border-radius:var(--fr-rs);display:none}
.fr-db-res.ok{display:block;background:rgba(61,187,126,.1);color:var(--fr-ok)}
.fr-db-res.bad{display:block;background:rgba(226,92,92,.1);color:var(--fr-bad)}
.fr-opt-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--fr-bd)}
.fr-opt-row:last-of-type{border:none}
.fr-opt-row b{font-size:13px;display:block}
.fr-opt-row span{color:var(--fr-tx3);font-size:12px}
.fr-sw{width:42px;height:24px;border-radius:999px;background:rgba(128,132,150,.28);position:relative;transition:.25s;flex:none}
.fr-sw::after{content:"";position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;left:3px;transition:.25s;box-shadow:0 2px 6px rgba(0,0,0,.2)}
.fr-sw.on{background:var(--fr-p1)}
.fr-sw.on::after{left:21px}
.fr-inst{display:flex;gap:12px;align-items:center;padding:13px 0;color:var(--fr-tx3);border-bottom:1px solid var(--fr-bd)}
.fr-inst:last-child{border:none}
.fr-inst-ic{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:rgba(128,132,150,.15);color:var(--fr-tx2);font-size:12px;flex:none}
.fr-inst.done{color:var(--fr-tx)}
.fr-inst.done .fr-inst-ic{background:rgba(61,187,126,.15);color:var(--fr-ok)}
#frNavBar{position:fixed;left:0;right:0;bottom:0;z-index:40;border-top:1px solid var(--fr-bd);padding:14px 20px calc(14px + env(safe-area-inset-bottom))}
.fr-nav-in2{max-width:860px;margin:auto;display:flex;gap:12px;justify-content:space-between}
.fr-done-ic{width:80px;height:80px;border-radius:50%;margin:10px auto 16px;background:rgba(61,187,126,.14);display:grid;place-items:center;color:var(--fr-ok);font-size:32px;animation:frPop .6s}
#frToast{position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:120;display:flex;flex-direction:column;gap:8px;align-items:center;pointer-events:none;width:max-content;max-width:90vw}
.fr-toast{display:flex;align-items:center;gap:9px;padding:11px 20px;border-radius:999px;font-size:13px;box-shadow:var(--fr-sh);border:1px solid var(--fr-bd);background:var(--fr-card);backdrop-filter:blur(18px);animation:frTin .3s ease}
.fr-toast i{color:var(--fr-p1)}
.fr-toast.bad i{color:var(--fr-bad)}
.fr-toast.warn i{color:var(--fr-warn)}
@keyframes frTin{from{opacity:0;transform:translateY(-12px)}}
.fr-sk{position:relative;overflow:hidden;background:rgba(128,132,150,.12);border-radius:10px;height:22px;margin-bottom:10px}
.fr-sk::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);transform:translateX(-100%);animation:frSk 1.3s infinite}
@keyframes frSk{to{transform:translateX(100%)}}
.fr-copy{text-align:center;color:var(--fr-tx3);font-size:11px;margin:26px 0 0}
:focus-visible{outline:2px solid var(--fr-p1);outline-offset:2px}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<?php if ($frInstalled): ?>
<main style="margin-top:16vh">
<div class="fr-card" style="max-width:460px;margin:0 auto;text-align:center">
<div style="width:64px;height:64px;margin:0 auto 14px;border-radius:20px;background:rgba(91,108,255,.12);display:grid;place-items:center;color:var(--fr-p1);font-size:24px"><i class="fa-solid fa-lock"></i></div>
<h1 class="fr-h" style="justify-content:center">系统已安装</h1>
<p class="fr-s">安装页已自动锁定，如需重新安装请删除根目录 frEnv.php 后刷新本页</p>
<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap"><a class="fr-btn fr-btn-p" href="index.php"><i class="fa-solid fa-house"></i> 进入前台</a><a class="fr-btn fr-btn-g" href="admin.php"><i class="fa-solid fa-user-shield"></i> 管理后台</a></div>
<p class="fr-copy">出于安全考虑，建议直接删除 install.php · 开发者 曾先生</p>
</div>
</main>
<?php exit; endif; ?>
<header id="frTop" class="fr-glass">
<div class="fr-top-in">
<div class="fr-logo"><span class="fr-logo-ic"><i class="fa-solid fa-cubes"></i></span><span>软件仓库<small>安装向导</small></span></div>
<div id="frSteps" aria-label="安装进度"><b class="on" title="欢迎"></b><b title="环境检测"></b><b title="存储方式"></b><b title="站点配置"></b><b title="高级选项"></b><b title="安装"></b></div>
<button id="frThemeBtn" class="fr-icon-btn" aria-label="切换深浅模式"><i class="fa-solid fa-moon"></i></button>
</div>
</header>
<main>
<section class="fr-step on" data-st="0"><div class="fr-card">
<h1 class="fr-h"><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--fr-p1)"></i> 欢迎使用安装向导</h1>
<p class="fr-s">只需几分钟，完成存储引擎、站点信息与管理账号配置</p>
<div class="fr-feats">
<div class="fr-feat"><i class="fa-solid fa-database"></i><div><b>双存储引擎</b><span>JSON 文件零配置起步，MySQL 数据库按需切换</span></div></div>
<div class="fr-feat"><i class="fa-solid fa-cloud-arrow-up"></i><div><b>图片上传体系</b><span>图标、截图、轮播、头像、打赏码全站可上传</span></div></div>
<div class="fr-feat"><i class="fa-solid fa-box-open"></i><div><b>演示数据</b><span>可一键导入 12 款示例软件与统计曲线预览效果</span></div></div>
<div class="fr-feat"><i class="fa-solid fa-shield-halved"></i><div><b>环境自检</b><span>安装前自动检测运行环境、扩展与目录权限</span></div></div>
</div>
<div class="fr-tip"><i class="fa-solid fa-circle-info" style="color:var(--fr-p2)"></i>安装完成后本页自动锁定，重装需删除根目录 frEnv.php</div>
</div></section>

<section class="fr-step" data-st="1"><div class="fr-card">
<h1 class="fr-h"><i class="fa-solid fa-stethoscope" style="color:var(--fr-p1)"></i> 环境检测</h1>
<p class="fr-s">正在检查服务器运行环境，全部必检项通过后方可继续</p>
<div id="frEnvBox"></div>
<div class="fr-tip bad" id="frEnvTip" style="display:none"></div>
</div></section>

<section class="fr-step" data-st="2"><div class="fr-card">
<h1 class="fr-h"><i class="fa-solid fa-layer-group" style="color:var(--fr-p1)"></i> 存储方式</h1>
<p class="fr-s">选择数据存放引擎，安装后可通过迁移数据更换</p>
<button type="button" class="fr-pick on" data-d="json"><span class="fr-pick-r"></span><span class="fr-pick-ic" style="background:linear-gradient(135deg,#18c0b2,#3ad6c8)"><i class="fa-solid fa-file-lines"></i></span><span style="flex:1"><b>JSON 文件存储</b><span>零配置开箱即用，数据保存在 frData 目录，适合个人与小团队</span></span></button>
<button type="button" class="fr-pick" data-d="mysql"><span class="fr-pick-r"></span><span class="fr-pick-ic" style="background:linear-gradient(135deg,#5b6cff,#7b8aff)"><i class="fa-solid fa-database"></i></span><span style="flex:1"><b>MySQL 数据库</b><span>数据集中入库，便于备份迁移与多站点共享，需 PHP 启用 PDO MySQL</span></span></button>
<div id="frDbForm">
<div class="fr-g2">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frDbHost">数据库主机</label><input class="fr-inp" id="frDbHost" value="127.0.0.1"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frDbPort">端口</label><input class="fr-inp" id="frDbPort" type="number" value="3306"></div>
</div>
<div class="fr-g2">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frDbName">数据库名（不存在将自动创建）</label><input class="fr-inp" id="frDbName" placeholder="frhub"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frDbPrefix">表前缀</label><input class="fr-inp" id="frDbPrefix" value="fr_"></div>
</div>
<div class="fr-g2">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frDbUser">用户名</label><input class="fr-inp" id="frDbUser" autocomplete="off"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frDbPass">密码</label><input class="fr-inp" id="frDbPass" type="password" autocomplete="off"></div>
</div>
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button type="button" class="fr-btn fr-btn-p" id="frDbTest"><i class="fa-solid fa-plug"></i> 测试连接</button><span style="color:var(--fr-tx3);font-size:12px">测试通过后方可进入下一步</span></div>
<div class="fr-db-res" id="frDbRes"></div>
</div>
</div></section>

<section class="fr-step" data-st="3"><div class="fr-card">
<h1 class="fr-h"><i class="fa-solid fa-globe" style="color:var(--fr-p1)"></i> 站点与管理员</h1>
<p class="fr-s">基础信息可随时在后台「网站配置」中修改</p>
<div class="fr-g2">
<div class="fr-frow"><label class="fr-flabel" for="frSiteName">网站名称 *</label><input class="fr-inp" id="frSiteName" value="软件仓库" maxlength="20"></div>
<div class="fr-frow"><label class="fr-flabel" for="frSlogan">网站标语</label><input class="fr-inp" id="frSlogan" value="发现好软件 · 分享高效体验"></div>
</div>
<div class="fr-g2">
<div class="fr-frow"><label class="fr-flabel" for="frKeywords">SEO 关键词</label><input class="fr-inp" id="frKeywords" value="软件仓库,软件下载,应用推荐"></div>
<div class="fr-frow"><label class="fr-flabel" for="frIcp">ICP 备案号</label><input class="fr-inp" id="frIcp" placeholder="可留空"></div>
</div>
<div class="fr-frow"><label class="fr-flabel" for="frDesc">网站描述</label><textarea class="fr-inp" id="frDesc" rows="2" placeholder="用于 SEO，可留空使用默认">一个干净清爽的软件下载仓库，提供软件浏览、下载、评分、收藏与投稿的一站式体验。</textarea></div>
<div class="fr-g2">
<div class="fr-frow"><label class="fr-flabel" for="frContact">联系邮箱</label><input class="fr-inp" id="frContact" type="email" placeholder="dev@example.com"></div>
<div class="fr-frow"><label class="fr-flabel" for="frFooter">页脚说明</label><input class="fr-inp" id="frFooter" value="本站为软件分享平台，仅供学习交流，所有软件版权归原作者所有。"></div>
</div>
<div style="border-top:1px solid var(--fr-bd);margin:18px 0;padding-top:18px">
<b style="display:block;margin-bottom:12px"><i class="fa-solid fa-user-shield" style="color:var(--fr-p1)"></i> 管理员账号</b>
<div class="fr-g2">
<div class="fr-frow"><label class="fr-flabel" for="frAdmU">后台账号 *</label><input class="fr-inp" id="frAdmU" placeholder="3-16 位字母、数字或下划线" autocomplete="off"></div>
<div class="fr-frow"><label class="fr-flabel" for="frTz">时区</label><select class="fr-inp" id="frTz"><option value="Asia/Shanghai" selected>Asia/Shanghai（北京时间）</option><option value="Asia/Hong_Kong">Asia/Hong_Kong</option><option value="Asia/Taipei">Asia/Taipei</option><option value="Asia/Tokyo">Asia/Tokyo</option><option value="UTC">UTC</option><option value="Europe/London">Europe/London</option><option value="America/New_York">America/New_York</option></select></div>
</div>
<div class="fr-g2">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frAdmP">密码 *（至少 6 位）</label><input class="fr-inp" id="frAdmP" type="password" autocomplete="new-password"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frAdmP2">确认密码 *</label><input class="fr-inp" id="frAdmP2" type="password" autocomplete="new-password"></div>
</div>
</div>
</div></section>

<section class="fr-step" data-st="4"><div class="fr-card">
<h1 class="fr-h"><i class="fa-solid fa-sliders" style="color:var(--fr-p1)"></i> 高级选项</h1>
<p class="fr-s">积分规则、安全策略与演示数据，安装后均可在后台调整</p>
<div class="fr-g2" style="margin-bottom:6px">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frPReg">注册赠送积分</label><input class="fr-inp" id="frPReg" type="number" min="0" value="10"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frPCmt">评论通过奖励</label><input class="fr-inp" id="frPCmt" type="number" min="0" value="2"></div>
</div>
<div class="fr-g2" style="margin-bottom:6px">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frPSign">每日签到奖励</label><input class="fr-inp" id="frPSign" type="number" min="0" value="5"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frPDl">下载消耗积分（0 为免费）</label><input class="fr-inp" id="frPDl" type="number" min="0" value="0"></div>
</div>
<div class="fr-g2" style="margin-bottom:6px">
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frRate">防爬限流阈值（次 / 10 秒）</label><input class="fr-inp" id="frRate" type="number" min="5" value="40"></div>
<div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frUpMax">上传大小限制（MB）</label><input class="fr-inp" id="frUpMax" type="number" min="1" max="10" value="2"></div>
</div>
<div class="fr-opt-row"><div><b>防爬限流</b><span>拦截常见脚本 UA 并限制请求频率</span></div><button type="button" class="fr-sw on" id="frSwAc" aria-label="防爬限流"></button></div>
<div class="fr-opt-row"><div><b>开放用户投稿</b><span>允许前台用户提交软件收录申请</span></div><button type="button" class="fr-sw on" id="frSwSub" aria-label="开放投稿"></button></div>
<div class="fr-opt-row"><div><b>导入演示数据</b><span>12 款示例软件、演示用户、评论与 14 日统计曲线</span></div><button type="button" class="fr-sw on" id="frSwDemo" aria-label="演示数据"></button></div>
<div class="fr-tip bad" id="frInstErr" style="display:none"></div>
</div></section>

<section class="fr-step" data-st="5"><div class="fr-card" style="text-align:center">
<h1 class="fr-h" style="justify-content:center"><i class="fa-solid fa-rocket" style="color:var(--fr-p1)"></i> 正在安装</h1>
<p class="fr-s">过程约需数秒，请勿关闭或刷新页面</p>
<div id="frInstBox" style="text-align:left;max-width:340px;margin:18px auto 0"></div>
</div></section>

<section class="fr-step" data-st="6"><div class="fr-card" style="text-align:center">
<div class="fr-done-ic"><i class="fa-solid fa-check"></i></div>
<h1 class="fr-h" style="justify-content:center">安装完成</h1>
<p class="fr-s">存储引擎：<span class="fr-badge-b" id="frDoneDriver">JSON 文件</span> · 安装页已自动锁定</p>
<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin:20px 0"><a class="fr-btn fr-btn-p" href="index.php"><i class="fa-solid fa-house"></i> 进入前台</a><a class="fr-btn fr-btn-g" href="admin.php"><i class="fa-solid fa-user-shield"></i> 进入管理后台</a></div>
<div style="text-align:left;padding:14px 16px;border-radius:var(--fr-rs);background:rgba(232,163,61,.1);color:var(--fr-tx2);font-size:12px;display:grid;gap:6px">
<b style="color:var(--fr-warn)"><i class="fa-solid fa-triangle-exclamation"></i> 安全建议</b>
<span>1. 安装完成后建议直接删除 install.php 文件</span>
<span>2. frEnv.php 保存存储配置与数据库凭据，请勿泄露</span>
<span>3. 上传的图片存放于 frUploads 目录，请定期备份</span>
</div>
</div></section>
<p class="fr-copy">软件仓库 FR Hub · 安装向导 · 开发者 曾先生</p>
</main>
<div id="frNavBar" class="fr-glass"><div class="fr-nav-in2"><button class="fr-btn fr-btn-g" id="frPrev"><i class="fa-solid fa-angle-left"></i> 上一步</button><button class="fr-btn fr-btn-p" id="frNext">开始向导 <i class="fa-solid fa-angle-right"></i></button></div></div>
<div id="frToast" role="status" aria-live="polite"></div>
<script>
const frQ=s=>document.querySelector(s);
const frA=s=>document.querySelectorAll(s);
const frEsc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function frToast(msg,type='ok'){const box=frQ('#frToast');const el=document.createElement('div');el.className='fr-toast '+type;const ic={ok:'fa-circle-check',bad:'fa-circle-exclamation',warn:'fa-triangle-exclamation'}[type]||'fa-circle-info';el.innerHTML='<i class="fa-solid '+ic+'"></i><span>'+frEsc(msg)+'</span>';box.appendChild(el);setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),420)},2600)}
const frSt={i:0,envOk:false,envLoaded:false,driver:'json',dbOk:false,busy:false,usedDriver:'json'};
function frNavSync(){const n=frSt.i;frQ('#frPrev').style.display=(n>0&&n<5)?'':'none';const nx=frQ('#frNext');nx.style.display=n<5?'':'none';nx.innerHTML=n===0?'开始向导 <i class="fa-solid fa-angle-right"></i>':n===4?'<i class="fa-solid fa-rocket"></i> 开始安装':'下一步 <i class="fa-solid fa-angle-right"></i>'}
function frGo(n){if(frSt.busy&&n!==5&&n!==6)return;n=Math.max(0,Math.min(6,n));frSt.i=n;if(n===5)frSt.busy=true;
frA('.fr-step').forEach(s=>s.classList.toggle('on',+s.dataset.st===n));
frA('#frSteps b').forEach((b,i)=>{b.classList.toggle('done',i<n||(n===6));b.classList.toggle('on',i===n)});
frNavSync();
if(n===1)frEnvLoad();
if(n===5)frInstall();
window.scrollTo({top:0,behavior:'smooth'})}
async function frEnvLoad(){if(frSt.envLoaded)return;const box=frQ('#frEnvBox');box.innerHTML='<div class="fr-sk"></div>'.repeat(6);
try{const r=await fetch('?frStep=env');const j=await r.json();
box.innerHTML=j.items.map(it=>'<div class="fr-env-row"><span class="fr-env-ic '+(it.ok?'ok':it.fatal?'bad':'warn')+'"><i class="fa-solid '+(it.ok?'fa-check':it.fatal?'fa-xmark':'fa-triangle-exclamation')+'"></i></span><div style="flex:1;min-width:0"><b>'+frEsc(it.name)+'</b><div class="fr-env-v">'+frEsc(it.val)+'</div></div></div>').join('');
frSt.envLoaded=true;frSt.envOk=j.items.every(it=>it.ok||!it.fatal);
const tip=frQ('#frEnvTip');if(frSt.envOk){tip.style.display='none'}else{tip.style.display='flex';tip.innerHTML='<i class="fa-solid fa-circle-exclamation"></i> 存在未通过的必检项，请修复后刷新重试'}}catch(e){box.innerHTML='<div class="fr-env-row" style="color:var(--fr-bad)">检测请求失败，请刷新重试</div>'}}
frA('.fr-pick').forEach(p=>p.onclick=()=>{frSt.driver=p.dataset.d;frA('.fr-pick').forEach(x=>x.classList.toggle('on',x===p));frQ('#frDbForm').classList.toggle('show',frSt.driver==='mysql')});
frQ('#frDbTest').onclick=async()=>{const b=frQ('#frDbTest'),res=frQ('#frDbRes');b.disabled=true;b.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> 测试中';res.className='fr-db-res';frSt.dbOk=false;
try{const r=await fetch('?frStep=dbtest',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({host:frQ('#frDbHost').value.trim(),port:+frQ('#frDbPort').value||3306,name:frQ('#frDbName').value.trim(),user:frQ('#frDbUser').value.trim(),pass:frQ('#frDbPass').value})});const j=await r.json();
if(j.code===0){frSt.dbOk=true;res.className='fr-db-res ok';res.innerHTML='<i class="fa-solid fa-circle-check"></i> 连接成功 · MySQL '+frEsc(j.ver||'')+'，数据库与数据表将自动创建'}else{res.className='fr-db-res bad';res.innerHTML='<i class="fa-solid fa-circle-xmark"></i> '+frEsc(j.msg)}}catch(e){res.className='fr-db-res bad';res.textContent='请求失败，请检查网络'}
b.disabled=false;b.innerHTML='<i class="fa-solid fa-plug"></i> 测试连接'};
['#frDbHost','#frDbPort','#frDbName','#frDbUser','#frDbPass','#frDbPrefix'].forEach(id=>frQ(id).addEventListener('input',()=>{frSt.dbOk=false;frQ('#frDbRes').className='fr-db-res'}));
frA('.fr-sw').forEach(s=>s.onclick=()=>s.classList.toggle('on'));
function frSiteOk(){const v=id=>frQ(id).value.trim();
if(!v('#frSiteName')){frToast('请填写网站名称','warn');return false}
if(!/^[A-Za-z0-9_]{3,16}$/.test(v('#frAdmU'))){frToast('管理员账号需为 3-16 位字母、数字或下划线','warn');return false}
if(frQ('#frAdmP').value.length<6){frToast('管理员密码至少 6 位','warn');return false}
if(frQ('#frAdmP').value!==frQ('#frAdmP2').value){frToast('两次输入的密码不一致','warn');return false}
return true}
function frCollect(){const v=id=>frQ(id).value.trim();const sw=id=>frQ(id).classList.contains('on')?1:0;
const p={driver:frSt.driver,siteName:v('#frSiteName'),slogan:v('#frSlogan'),keywords:v('#frKeywords'),desc:v('#frDesc'),icp:v('#frIcp'),footer:v('#frFooter'),contact:v('#frContact'),adminUser:v('#frAdmU'),adminPass:frQ('#frAdmP').value,tz:v('#frTz'),pReg:+frQ('#frPReg').value||0,pComment:+frQ('#frPCmt').value||0,pSign:+frQ('#frPSign').value||0,pDl:+frQ('#frPDl').value||0,rateLimit:+frQ('#frRate').value||40,upMax:Math.min(10,Math.max(1,+frQ('#frUpMax').value||2)),antiCrawler:sw('#frSwAc'),enableSubmit:sw('#frSwSub'),demo:sw('#frSwDemo')};
if(p.driver==='mysql'){p.dbHost=v('#frDbHost');p.dbPort=+v('#frDbPort')||3306;p.dbName=v('#frDbName');p.dbUser=v('#frDbUser');p.dbPass=frQ('#frDbPass').value;p.dbPrefix=v('#frDbPrefix')||'fr_'}
return p}
async function frInstall(){const box=frQ('#frInstBox');
box.innerHTML=['写入环境配置','初始化存储引擎','导入基础数据','完成安装'].map((s,i)=>'<div class="fr-inst" data-i="'+i+'"><span class="fr-inst-ic"><i class="fa-solid fa-circle-notch fa-spin"></i></span><span>'+s+'</span></div>').join('');
let k=0,fin=false;
const tk=setInterval(()=>{if(k<3||(k===3&&fin)){const el=box.querySelector('[data-i="'+k+'"]');if(el){el.classList.add('done');el.querySelector('.fr-inst-ic').innerHTML='<i class="fa-solid fa-circle-check"></i>'}k++;if(k>3){clearInterval(tk);setTimeout(frDone,550)}}},520);
const fail=msg=>{clearInterval(tk);frSt.busy=false;const eb=frQ('#frInstErr');eb.style.display='flex';eb.innerHTML='<i class="fa-solid fa-circle-exclamation"></i> '+frEsc(msg);frGo(4);frToast(msg,'bad')};
try{const r=await fetch('?frStep=install',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(frCollect())});const j=await r.json();
if(j.code===0){fin=true;frSt.usedDriver=j.driver}else fail(j.msg||'安装失败')}catch(e){fail('网络异常，安装中断，请检查后重试')}}
function frDone(){frSt.busy=false;frQ('#frDoneDriver').textContent=frSt.usedDriver==='mysql'?'MySQL 数据库':'JSON 文件';frGo(6)}
frQ('#frNext').onclick=()=>{const n=frSt.i;
if(n===1&&!frSt.envOk){frToast('环境检测未通过，请先修复','bad');return}
if(n===2&&frSt.driver==='mysql'){
if(!frQ('#frDbHost').value.trim()||!frQ('#frDbName').value.trim()||!frQ('#frDbUser').value.trim()){frToast('请完整填写数据库连接信息','warn');return}
if(!frSt.dbOk){frToast('请先点击测试连接并确认通过','warn');return}}
if(n===3&&!frSiteOk())return;
frGo(n+1)};
frQ('#frPrev').onclick=()=>frGo(frSt.i-1);
frQ('#frThemeBtn').onclick=()=>{const t=document.documentElement.dataset.frTheme==='dark'?'light':'dark';document.documentElement.dataset.frTheme=t;localStorage.setItem('frTheme',t);frQ('#frThemeBtn i').className=t==='dark'?'fa-solid fa-sun':'fa-solid fa-moon'};
frQ('#frThemeBtn i').className=document.documentElement.dataset.frTheme==='dark'?'fa-solid fa-sun':'fa-solid fa-moon';
frNavSync();
</script>
</body>
</html>
