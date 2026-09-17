<?php
require_once __DIR__ . '/frCore.php';
function frAdHandle($frCfg) {
  $frAct = $_GET['frApi'] ?? '';
  $frIn = json_decode((string)file_get_contents('php://input'), true) ?: [];
  if ($frAct === 'login') {
    if (($frIn['user'] ?? '') === ($frCfg['adminUser'] ?? '') && password_verify((string)($frIn['pass'] ?? ''), $frCfg['adminPass'] ?? '')) { $_SESSION['frAdmin'] = 1; frLog('登录', '管理员登录后台'); frOk(); }
    frErr('账号或密码错误');
  }
  if ($frAct === 'logout') { unset($_SESSION['frAdmin']); frLog('退出', '管理员退出后台'); frOk(); }
  if (empty($_SESSION['frAdmin'])) frErr('请先登录', 401);
  if ($frAct === 'upload') {
    $frKind = $_GET['kind'] ?? 'icon';
    if (!in_array($frKind, ['icon', 'shot', 'car', 'reward'], true)) frErr('上传类型错误');
    $frUrl = frUploadHandle($frKind, $frCfg);
    frLog('上传', '上传图片：' . $frUrl);
    if ($frKind === 'reward') { $frNewCfg = $frCfg; $frNewCfg['rewardUrl'] = $frUrl; frSave('config', $frNewCfg); }
    frOk(['url' => $frUrl]);
  }
  $frCatMap = []; foreach (frLoad('cats', []) as $frC) $frCatMap[$frC['id']] = $frC['name'];
  switch ($frAct) {
    case 'stats':
      $frS = frLoad('stats', ['days' => [], 'dDays' => []]);
      $frDays = [];
      for ($frI = 13; $frI >= 0; $frI--) { $frD = date('Y-m-d', strtotime("-$frI day")); $frDays[] = ['d' => substr($frD, 5), 'v' => $frS['days'][$frD] ?? 0]; }
      $frApps = frLoad('apps', []); $frTop = $frApps; usort($frTop, fn($frA, $frB) => $frB['downloads'] <=> $frA['downloads']);
      frOk(['cards' => ['今日访问' => $frS['today'] ?? 0, '总访问' => $frS['total'] ?? 0, '今日下载' => $frS['dToday'] ?? 0, '总下载' => $frS['dTotal'] ?? 0, '注册用户' => count(frLoad('users', [])), '软件总数' => count($frApps), '待审评论' => count(array_filter(frLoad('comments', []), fn($frC) => (int)$frC['status'] === 0)), '待审投稿' => count(array_filter(frLoad('subs', []), fn($frS2) => (int)$frS2['status'] === 0))], 'days' => $frDays, 'top' => array_slice($frTop, 0, 5), 'logs' => array_slice(frLoad('logs', []), 0, 8)]);
    case 'apps':
      $frApps = frLoad('apps', []); usort($frApps, fn($frA, $frB) => ($frB['top'] ?? 0) <=> ($frA['top'] ?? 0) ?: $frB['updated'] <=> $frA['updated']);
      foreach ($frApps as $frI => $frA) $frApps[$frI]['catName'] = $frCatMap[$frA['catId']] ?? '-';
      frOk(['apps' => $frApps]);
    case 'appSave':
      $frApps = frLoad('apps', []);
      $frId = (int)($frIn['id'] ?? 0);
      $frName = trim((string)($frIn['name'] ?? '')); if ($frName === '') frErr('请填写软件名称');
      $frLinks = [];
      foreach (explode("\n", str_replace("\r", '', (string)($frIn['links'] ?? ''))) as $frLn) { $frLn = trim($frLn); if ($frLn === '') continue; $frP = explode('|', $frLn, 2); $frLinks[] = ['label' => trim($frP[0]) ?: '下载链接', 'url' => trim($frP[1] ?? '#')]; }
      if (!$frLinks) $frLinks = [['label' => '官方镜像', 'url' => 'https://example.com']];
      $frTags = array_values(array_filter(array_map('trim', explode(',', (string)($frIn['tags'] ?? '')))));
      $frIconImg = frUpPath($frIn['iconImg'] ?? '');
      $frShots = [];
      foreach ((array)($frIn['shotsImg'] ?? []) as $frSu) { $frSu = frUpPath($frSu); if ($frSu !== '') $frShots[] = $frSu; if (count($frShots) >= 8) break; }
      $frData = ['name' => $frName, 'icon' => trim((string)($frIn['icon'] ?? 'fa-cube')), 'hue' => ((int)($frIn['hue'] ?? 220)) % 360, 'hue2' => ((int)($frIn['hue2'] ?? 260)) % 360, 'iconImg' => $frIconImg, 'shotsImg' => $frShots, 'catId' => (int)($frIn['catId'] ?? 1), 'tags' => $frTags, 'ver' => trim((string)($frIn['ver'] ?? '1.0')), 'size' => trim((string)($frIn['size'] ?? '10 MB')), 'dev' => trim((string)($frIn['dev'] ?? '独立开发者')), 'short' => trim((string)($frIn['short'] ?? '')), 'desc' => trim((string)($frIn['desc'] ?? '')), 'links' => $frLinks];
      if ($frId) { foreach ($frApps as $frI => $frA) if ((int)$frA['id'] === $frId) { $frApps[$frI] = array_merge($frA, $frData); $frApps[$frI]['updated'] = time(); break; } frLog('软件管理', '更新软件：' . $frName); }
      else { $frData += ['id' => frNid($frApps), 'downloads' => 0, 'favs' => 0, 'rating' => 5.0, 'top' => 0, 'status' => 0, 'views' => 0, 'created' => time(), 'updated' => time()]; $frApps[] = $frData; frLog('软件管理', '新增软件：' . $frName); }
      frSave('apps', $frApps); frOk();
    case 'appFlag':
      $frApps = frLoad('apps', []); $frId = (int)($frIn['id'] ?? 0); $frK = ($frIn['key'] ?? '') === 'top' ? 'top' : 'status';
      foreach ($frApps as $frI => $frA) if ((int)$frA['id'] === $frId) { $frApps[$frI][$frK] = (int)!($frA[$frK] ?? 0); frLog('软件管理', ($frK === 'top' ? '切换置顶：' : '切换上架：') . $frA['name']); break; }
      frSave('apps', $frApps); frOk();
    case 'appDel':
      $frApps = frLoad('apps', []); $frId = (int)($frIn['id'] ?? 0); $frNm = '';
      foreach ($frApps as $frI => $frA) if ((int)$frA['id'] === $frId) { $frNm = $frA['name']; unset($frApps[$frI]); break; }
      frSave('apps', array_values($frApps));
      frSave('comments', array_values(array_filter(frLoad('comments', []), fn($frC) => (int)$frC['appId'] !== $frId)));
      frLog('软件管理', '删除软件：' . $frNm); frOk();
    case 'cats':
      $frApps = frLoad('apps', []); $frCats = frLoad('cats', []);
      foreach ($frCats as $frI => $frC) $frCats[$frI]['count'] = count(array_filter($frApps, fn($frA) => (int)$frA['catId'] === (int)$frC['id']));
      frOk(['cats' => $frCats]);
    case 'catSave':
      $frCats = frLoad('cats', []); $frId = (int)($frIn['id'] ?? 0); $frName = trim((string)($frIn['name'] ?? ''));
      if ($frName === '') frErr('请填写分类名称');
      $frRow = ['name' => $frName, 'icon' => trim((string)($frIn['icon'] ?? 'fa-folder'))];
      if ($frId) { foreach ($frCats as $frI => $frC) if ((int)$frC['id'] === $frId) $frCats[$frI] = array_merge($frC, $frRow); frLog('分类管理', '更新分类：' . $frName); }
      else { $frRow['id'] = frNid($frCats); $frCats[] = $frRow; frLog('分类管理', '新增分类：' . $frName); }
      frSave('cats', $frCats); frOk();
    case 'catDel':
      $frId = (int)($frIn['id'] ?? 0);
      if (count(array_filter(frLoad('apps', []), fn($frA) => (int)$frA['catId'] === $frId))) frErr('该分类下还有软件，无法删除');
      frSave('cats', array_values(array_filter(frLoad('cats', []), fn($frC) => (int)$frC['id'] !== $frId)));
      frLog('分类管理', '删除分类#' . $frId); frOk();
    case 'cars': frOk(['cars' => frLoad('cars', [])]);
    case 'carSave':
      $frCars = frLoad('cars', []); $frId = (int)($frIn['id'] ?? 0);
      $frRow = ['title' => trim((string)($frIn['title'] ?? '')), 'sub' => trim((string)($frIn['sub'] ?? '')), 'badge' => trim((string)($frIn['badge'] ?? '')), 'hue' => ((int)($frIn['hue'] ?? 220)) % 360, 'link' => trim((string)($frIn['link'] ?? '#/')), 'top' => (int)($frIn['top'] ?? 0), 'img' => frUpPath($frIn['img'] ?? '')];
      if ($frRow['title'] === '') frErr('请填写标题');
      if ($frId) { foreach ($frCars as $frI => $frC) if ((int)$frC['id'] === $frId) $frCars[$frI] = array_merge($frC, $frRow); frLog('轮播管理', '更新轮播：' . $frRow['title']); }
      else { $frRow['id'] = frNid($frCars); $frCars[] = $frRow; frLog('轮播管理', '新增轮播：' . $frRow['title']); }
      frSave('cars', $frCars); frOk();
    case 'carDel':
      $frId = (int)($frIn['id'] ?? 0);
      frSave('cars', array_values(array_filter(frLoad('cars', []), fn($frC) => (int)$frC['id'] !== $frId)));
      frLog('轮播管理', '删除轮播#' . $frId); frOk();
    case 'notices': frOk(['notices' => frLoad('notices', [])]);
    case 'noticeSave':
      $frNs = frLoad('notices', []); $frId = (int)($frIn['id'] ?? 0);
      $frRow = ['title' => trim((string)($frIn['title'] ?? '')), 'content' => trim((string)($frIn['content'] ?? '')), 'top' => (int)($frIn['top'] ?? 0), 'at' => date('Y-m-d H:i')];
      if ($frRow['title'] === '' || $frRow['content'] === '') frErr('标题与内容不能为空');
      if ($frId) { foreach ($frNs as $frI => $frN) if ((int)$frN['id'] === $frId) $frNs[$frI] = array_merge($frN, $frRow); frLog('公告管理', '更新公告：' . $frRow['title']); }
      else { $frRow['id'] = frNid($frNs); $frNs[] = $frRow; frLog('公告管理', '发布公告：' . $frRow['title']); }
      frSave('notices', $frNs); frOk();
    case 'noticeDel':
      $frId = (int)($frIn['id'] ?? 0);
      frSave('notices', array_values(array_filter(frLoad('notices', []), fn($frN) => (int)$frN['id'] !== $frId)));
      frLog('公告管理', '删除公告#' . $frId); frOk();
    case 'cmts':
      $frCms = frLoad('comments', []); usort($frCms, fn($frA, $frB) => $frB['at'] <=> $frA['at']);
      $frAppMap = []; foreach (frLoad('apps', []) as $frA) $frAppMap[(int)$frA['id']] = $frA['name'];
      foreach ($frCms as $frI => $frC) $frCms[$frI]['appName'] = $frAppMap[$frC['appId']] ?? '已删除';
      frOk(['cmts' => $frCms]);
    case 'cmtSet':
      $frCms = frLoad('comments', []); $frId = (int)($frIn['id'] ?? 0); $frSt = (int)($frIn['status'] ?? 1);
      foreach ($frCms as $frI => $frC) if ((int)$frC['id'] === $frId) {
        if ((int)$frC['status'] !== 1 && $frSt === 1) { $frUsers = frLoad('users', []); foreach ($frUsers as $frJ => $frU) if ((int)$frU['id'] === (int)$frC['userId']) { $frUsers[$frJ]['points'] = (int)$frU['points'] + (int)($frCfg['pComment'] ?? 2); break; } frSave('users', $frUsers); }
        $frCms[$frI]['status'] = $frSt; frLog('评论审核', ($frSt === 1 ? '通过' : '驳回') . '评论：' . mb_substr($frC['text'], 0, 24)); break;
      }
      frSave('comments', $frCms); frOk();
    case 'cmtDel':
      $frId = (int)($frIn['id'] ?? 0);
      frSave('comments', array_values(array_filter(frLoad('comments', []), fn($frC) => (int)$frC['id'] !== $frId)));
      frLog('评论审核', '删除评论#' . $frId); frOk();
    case 'users': frOk(['users' => frLoad('users', [])]);
    case 'userBan':
      $frUsers = frLoad('users', []); $frId = (int)($frIn['id'] ?? 0);
      foreach ($frUsers as $frI => $frU) if ((int)$frU['id'] === $frId) { $frUsers[$frI]['role'] = ($frU['role'] ?? 'user') === 'banned' ? 'user' : 'banned'; frLog('用户管理', ($frUsers[$frI]['role'] === 'banned' ? '停用' : '恢复') . '用户：' . $frU['name']); break; }
      frSave('users', $frUsers); frOk();
    case 'userPts':
      $frUsers = frLoad('users', []); $frId = (int)($frIn['id'] ?? 0); $frD = (int)($frIn['delta'] ?? 0);
      foreach ($frUsers as $frI => $frU) if ((int)$frU['id'] === $frId) { $frUsers[$frI]['points'] = max(0, (int)$frU['points'] + $frD); frLog('用户管理', '调整 ' . $frU['name'] . ' 积分 ' . ($frD > 0 ? '+' : '') . $frD); break; }
      frSave('users', $frUsers); frOk();
    case 'userDel':
      $frId = (int)($frIn['id'] ?? 0);
      frSave('users', array_values(array_filter(frLoad('users', []), fn($frU) => (int)$frU['id'] !== $frId)));
      frLog('用户管理', '删除用户#' . $frId); frOk();
    case 'subs':
      $frSubs = frLoad('subs', []); usort($frSubs, fn($frA, $frB) => $frB['at'] <=> $frA['at']);
      foreach ($frSubs as $frI => $frS) $frSubs[$frI]['catName'] = $frCatMap[$frS['catId']] ?? '-';
      frOk(['subs' => $frSubs]);
    case 'subSet':
      $frSubs = frLoad('subs', []); $frId = (int)($frIn['id'] ?? 0); $frSt = (int)($frIn['status'] ?? 1); $frRow = null;
      foreach ($frSubs as $frI => $frS) if ((int)$frS['id'] === $frId) { $frSubs[$frI]['status'] = $frSt; $frRow = $frSubs[$frI]; break; }
      if ($frRow && $frSt === 1) {
        $frApps = frLoad('apps', []);
        $frApps[] = ['id' => frNid($frApps), 'name' => $frRow['name'], 'icon' => 'fa-cube', 'hue' => mt_rand(0, 359), 'hue2' => mt_rand(0, 359), 'iconImg' => '', 'shotsImg' => [], 'catId' => (int)$frRow['catId'], 'tags' => ['投稿'], 'ver' => $frRow['ver'] ?: '1.0', 'size' => '未知', 'dev' => '投稿用户 ' . $frRow['user'], 'short' => mb_substr($frRow['desc'], 0, 40), 'desc' => $frRow['desc'], 'links' => [['label' => '官方来源', 'url' => $frRow['link']]], 'downloads' => 0, 'favs' => 0, 'rating' => 5.0, 'top' => 0, 'status' => 0, 'views' => 0, 'created' => time(), 'updated' => time()];
        frSave('apps', $frApps);
        $frUsers = frLoad('users', []); foreach ($frUsers as $frI => $frU) if ((int)$frU['id'] === (int)$frRow['uid']) { $frUsers[$frI]['points'] = (int)$frU['points'] + 10; break; } frSave('users', $frUsers);
        frLog('投稿审核', '收录投稿：' . $frRow['name'] . '（已生成待上架草稿）');
      } elseif ($frRow) frLog('投稿审核', '驳回投稿：' . $frRow['name']);
      frSave('subs', $frSubs); frOk();
    case 'logs': frOk(['logs' => array_slice(frLoad('logs', []), 0, 200)]);
    case 'logClear': frSave('logs', []); frLog('日志', '清空操作日志'); frOk();
    case 'cfg': frOk(['cfg' => ['siteName' => $frCfg['siteName'] ?? '', 'slogan' => $frCfg['slogan'] ?? '', 'keywords' => $frCfg['keywords'] ?? '', 'desc' => $frCfg['desc'] ?? '', 'icp' => $frCfg['icp'] ?? '', 'footer' => $frCfg['footer'] ?? '', 'contact' => $frCfg['contact'] ?? '', 'pReg' => $frCfg['pReg'] ?? 10, 'pComment' => $frCfg['pComment'] ?? 2, 'pSign' => $frCfg['pSign'] ?? 5, 'pDl' => $frCfg['pDl'] ?? 0, 'antiCrawler' => $frCfg['antiCrawler'] ?? 1, 'rateLimit' => $frCfg['rateLimit'] ?? 40, 'enableSubmit' => $frCfg['enableSubmit'] ?? 1, 'upMax' => $frCfg['upMax'] ?? 2, 'rewardUrl' => $frCfg['rewardUrl'] ?? '', 'adminUser' => $frCfg['adminUser'] ?? 'admin']]);
    case 'cfgSave':
      $frNew = $frCfg;
      foreach (['siteName', 'slogan', 'keywords', 'desc', 'icp', 'footer', 'contact'] as $frK) $frNew[$frK] = trim((string)($frIn[$frK] ?? ($frCfg[$frK] ?? '')));
      foreach (['pReg', 'pComment', 'pSign', 'pDl', 'rateLimit'] as $frK) $frNew[$frK] = max(0, (int)($frIn[$frK] ?? ($frCfg[$frK] ?? 0)));
      $frNew['antiCrawler'] = (int)($frIn['antiCrawler'] ?? 0);
      $frNew['enableSubmit'] = (int)($frIn['enableSubmit'] ?? 0);
      $frNew['upMax'] = max(1, min(10, (int)($frIn['upMax'] ?? 2)));
      $frNew['rewardUrl'] = frUpPath($frIn['rewardUrl'] ?? '');
      $frAu = trim((string)($frIn['adminUser'] ?? '')); if ($frAu !== '') $frNew['adminUser'] = $frAu;
      $frNp = (string)($frIn['newPass'] ?? ''); if (strlen($frNp) >= 6) $frNew['adminPass'] = password_hash($frNp, PASSWORD_DEFAULT);
      frSave('config', $frNew); frLog('网站配置', '更新网站配置'); frOk();
  }
  frErr('未知请求', 404);
}
$frCfg = frLoad('config', []);
if (isset($_GET['frApi'])) frAdHandle($frCfg);
$frIsAd = !empty($_SESSION['frAdmin']);
?>
<!DOCTYPE html>
<html lang="zh-CN" data-fr-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>管理后台 - <?php echo htmlspecialchars($frCfg['siteName'] ?? '软件仓库'); ?></title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/normalize/8.0.1/normalize.min.css">
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<script>!function(){try{var t=localStorage.getItem('frTheme');if(!t)t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.dataset.frTheme=t}catch(e){}}();</script>
<style>
*{box-sizing:border-box}
:root{--fr-p1:#5b6cff;--fr-p1b:#7b8aff;--fr-p2:#18c0b2;--fr-bg:#f3f4fa;--fr-card:rgba(255,255,255,.78);--fr-solid:#fff;--fr-tx:#191c26;--fr-tx2:#5d6470;--fr-tx3:#9aa1b0;--fr-bd:rgba(25,28,38,.08);--fr-sh:0 10px 30px rgba(25,28,38,.08);--fr-r:18px;--fr-rs:12px;--fr-warn:#e8a33d;--fr-bad:#e25c5c;--fr-ok:#3dbb7e;--fr-star:#f5a94b}
html[data-fr-theme="dark"]{--fr-bg:#0e1016;--fr-card:rgba(28,31,42,.72);--fr-solid:#1b1e29;--fr-tx:#eceef4;--fr-tx2:#a7adbd;--fr-tx3:#6d7484;--fr-bd:rgba(255,255,255,.08);--fr-sh:0 10px 30px rgba(0,0,0,.35)}
body{margin:0;background:var(--fr-bg);color:var(--fr-tx);font:400 14px/1.6 -apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;transition:background .3s,color .3s}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(620px 320px at 88% -6%,rgba(91,108,255,.13),transparent 60%),radial-gradient(520px 280px at 6% 10%,rgba(24,192,178,.09),transparent 60%)}
button{font:inherit;cursor:pointer;border:none;background:none;color:inherit;padding:0}
input,select,textarea{font:inherit;color:inherit}
a{color:inherit;text-decoration:none}
.fr-glass,.fr-card{background:var(--fr-card);backdrop-filter:blur(20px) saturate(1.5);-webkit-backdrop-filter:blur(20px) saturate(1.5)}
.fr-card{border:1px solid var(--fr-bd);border-radius:var(--fr-r);box-shadow:var(--fr-sh);padding:20px}
.fr-inp{width:100%;background:var(--fr-solid);border:1px solid var(--fr-bd);border-radius:var(--fr-rs);padding:10px 13px;outline:none;transition:border-color .2s,box-shadow .2s}
.fr-inp:focus{border-color:var(--fr-p1);box-shadow:0 0 0 3px rgba(91,108,255,.15)}
.fr-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 20px;border-radius:var(--fr-rs);font-weight:600;font-size:13px;white-space:nowrap;transition:transform .15s,opacity .2s}
.fr-btn:active{transform:scale(.96)}
.fr-btn-p{background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));color:#fff;box-shadow:0 6px 16px rgba(91,108,255,.35)}
.fr-btn-g{background:var(--fr-solid);border:1px solid var(--fr-bd)}
.fr-btn-s{background:rgba(91,108,255,.12);color:var(--fr-p1)}
.fr-btn-d{background:rgba(226,92,92,.12);color:var(--fr-bad)}
.fr-btn[disabled]{opacity:.5;pointer-events:none}
.fr-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:11px}
.fr-badge-w{background:rgba(232,163,61,.14);color:var(--fr-warn)}
.fr-badge-g{background:rgba(61,187,126,.14);color:var(--fr-ok)}
.fr-badge-r{background:rgba(226,92,92,.14);color:var(--fr-bad)}
.fr-badge-b{background:rgba(91,108,255,.12);color:var(--fr-p1)}
.fr-badge-n{background:rgba(128,132,150,.14);color:var(--fr-tx2)}
#frSide{position:fixed;left:0;top:0;bottom:0;width:232px;z-index:60;display:flex;flex-direction:column;border-right:1px solid var(--fr-bd);padding:18px 14px}
.fr-ad-logo{display:flex;align-items:center;gap:10px;padding:6px 8px 18px;border-bottom:1px solid var(--fr-bd);margin-bottom:12px}
.fr-logo-ic{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));display:grid;place-items:center;color:#fff;font-size:15px;flex:none;box-shadow:0 4px 12px rgba(91,108,255,.4)}
.fr-ad-logo b{display:block;font-size:15px}
.fr-ad-logo span{font-size:11px;color:var(--fr-tx3)}
#frAdMenu{display:grid;gap:3px;overflow:auto;flex:1}
#frAdMenu button{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:var(--fr-rs);color:var(--fr-tx2);font-size:13px;text-align:left;transition:.2s}
#frAdMenu button i{width:18px;text-align:center}
#frAdMenu button:hover{background:rgba(91,108,255,.08);color:var(--fr-p1)}
#frAdMenu button.on{background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));color:#fff;box-shadow:0 6px 14px rgba(91,108,255,.3)}
.fr-bdg{margin-left:auto;background:var(--fr-bad);color:#fff;font-size:10px;padding:1px 7px;border-radius:999px;display:none}
.fr-ad-side-f{border-top:1px solid var(--fr-bd);padding-top:12px;display:grid;gap:3px}
.fr-ad-side-f a,.fr-ad-side-f button{display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:var(--fr-rs);color:var(--fr-tx3);font-size:13px}
.fr-ad-side-f a:hover,.fr-ad-side-f button:hover{color:var(--fr-p1);background:rgba(91,108,255,.08)}
#frAdMain{margin-left:232px;min-height:100vh;display:flex;flex-direction:column}
#frAdTop{position:sticky;top:0;z-index:40;display:flex;align-items:center;gap:12px;padding:0 22px;height:60px;border-bottom:1px solid var(--fr-bd)}
#frMenuBtn{display:none;width:36px;height:36px;border-radius:10px;place-items:center;background:var(--fr-solid);border:1px solid var(--fr-bd)}
#frAdBox{padding:22px;flex:1;max-width:1200px;width:100%;margin:0 auto}
.fr-stat-g{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.fr-stat{padding:18px;display:flex;flex-direction:column;gap:6px}
.fr-stat b{font-size:24px}
.fr-stat span{color:var(--fr-tx3);font-size:12px;display:flex;align-items:center;gap:6px}
.fr-stat i{color:var(--fr-p1)}
.fr-chart{display:flex;align-items:flex-end;gap:7px;height:170px;padding-top:10px}
.fr-bar{flex:1;display:flex;flex-direction:column;justify-content:flex-end;gap:6px;height:100%;min-width:0}
.fr-bar i{display:block;border-radius:6px 6px 2px 2px;background:linear-gradient(180deg,var(--fr-p1),rgba(91,108,255,.4));min-height:3px;transition:height .5s}
.fr-bar span{font-size:10px;color:var(--fr-tx3);text-align:center;white-space:nowrap;overflow:hidden}
.fr-tb-wrap{overflow-x:auto}
.fr-tb{width:100%;border-collapse:collapse;font-size:13px;min-width:640px}
.fr-tb th{text-align:left;color:var(--fr-tx3);font-weight:500;font-size:12px;padding:10px 12px;border-bottom:1px solid var(--fr-bd);white-space:nowrap}
.fr-tb td{padding:12px;border-bottom:1px solid var(--fr-bd);vertical-align:middle}
.fr-tb tr:last-child td{border:none}
.fr-tb tr:hover td{background:rgba(91,108,255,.04)}
.fr-mini{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:9px;font-size:12px;background:var(--fr-solid);border:1px solid var(--fr-bd);color:var(--fr-tx2);margin-right:6px;margin-top:4px;transition:.2s}
.fr-mini:hover{color:var(--fr-p1);border-color:var(--fr-p1)}
.fr-mini.danger:hover{color:var(--fr-bad);border-color:var(--fr-bad)}
.fr-ic{display:grid;place-items:center;border-radius:26%;color:#fff;flex:none;background:linear-gradient(135deg,hsl(var(--h1) 78% 60%),hsl(var(--h2) 72% 48%));overflow:hidden}
.fr-ic img{width:100%;height:100%;object-fit:cover;display:block}
.fr-ava{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;flex:none;background:linear-gradient(135deg,hsl(var(--h,220) 75% 60%),hsl(calc(var(--h,220) + 32) 70% 48%));overflow:hidden}
.fr-ava img{width:100%;height:100%;object-fit:cover;display:block}
.fr-sw{width:40px;height:22px;border-radius:999px;background:rgba(128,132,150,.28);position:relative;transition:.25s;flex:none;display:inline-block;vertical-align:middle}
.fr-sw::after{content:"";position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;left:3px;transition:.25s;box-shadow:0 2px 6px rgba(0,0,0,.2)}
.fr-sw.on{background:var(--fr-p1)}
.fr-sw.on::after{left:21px}
.fr-seg{display:inline-flex;background:var(--fr-solid);border:1px solid var(--fr-bd);border-radius:999px;padding:4px;gap:2px}
.fr-seg button{padding:7px 16px;border-radius:999px;font-size:12px;color:var(--fr-tx2)}
.fr-seg button.on{background:var(--fr-p1);color:#fff}
.fr-frow{margin-bottom:13px}
.fr-flabel{display:block;font-size:12px;color:var(--fr-tx2);margin-bottom:6px}
.fr-ov{position:fixed;inset:0;z-index:80;background:rgba(15,17,26,.45);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;transition:opacity .25s}
.fr-ov.on{opacity:1}
.fr-mcard{width:100%;max-width:520px;max-height:88vh;overflow:auto;border-radius:22px;padding:26px;background:var(--fr-card);backdrop-filter:blur(26px) saturate(1.5);border:1px solid var(--fr-bd);box-shadow:0 24px 60px rgba(10,12,20,.3);transform:translateY(20px) scale(.97);transition:transform .3s}
.fr-ov.on .fr-mcard{transform:none}
.fr-m-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.fr-m-t{font-size:17px;font-weight:800}
.fr-m-x{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;color:var(--fr-tx3);background:rgba(128,132,150,.12)}
#frToast{position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:120;display:flex;flex-direction:column;gap:8px;align-items:center;pointer-events:none}
.fr-toast{display:flex;align-items:center;gap:9px;padding:11px 20px;border-radius:999px;font-size:13px;box-shadow:var(--fr-sh);border:1px solid var(--fr-bd);background:var(--fr-card);backdrop-filter:blur(18px);animation:frTin .3s ease}
.fr-toast i{color:var(--fr-p1)}
.fr-toast.bad i{color:var(--fr-bad)}
.fr-toast.warn i{color:var(--fr-warn)}
@keyframes frTin{from{opacity:0;transform:translateY(-12px)}}
.fr-stars{color:var(--fr-star);font-size:11px;display:inline-flex;gap:1px}
.fr-log-item{display:flex;gap:10px;align-items:baseline;padding:9px 0;border-bottom:1px solid var(--fr-bd);font-size:13px}
.fr-log-item:last-child{border:none}
.fr-icon-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
.fr-icon-grid button{aspect-ratio:1;border-radius:10px;border:1px solid var(--fr-bd);display:grid;place-items:center;color:var(--fr-tx2);background:var(--fr-solid);font-size:15px}
.fr-icon-grid button.on{border-color:var(--fr-p1);color:var(--fr-p1);background:rgba(91,108,255,.1)}
.fr-hue-prev{width:44px;height:44px;border-radius:12px;flex:none;background:linear-gradient(135deg,hsl(var(--h1) 78% 60%),hsl(var(--h2) 72% 48%))}
.fr-ic-prev{position:relative;width:56px;height:56px;border-radius:16px;overflow:hidden;border:1px solid var(--fr-bd);background:rgba(128,132,150,.12);display:grid;place-items:center;color:var(--fr-tx3);flex:none}
.fr-ic-prev img{width:100%;height:100%;object-fit:cover}
.fr-sh-thumb{position:relative;width:120px;aspect-ratio:16/10;border-radius:10px;overflow:hidden;border:1px solid var(--fr-bd)}
.fr-sh-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.fr-sh-thumb button{position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:7px;background:rgba(15,17,26,.6);color:#fff;display:grid;place-items:center;font-size:11px}
.fr-drop{border:1.5px dashed var(--fr-bd);border-radius:var(--fr-rs);padding:16px;width:100%;display:flex;gap:8px;align-items:center;justify-content:center;color:var(--fr-tx3);font-size:12px;transition:.2s;background:var(--fr-solid)}
.fr-drop:hover,.fr-drop.drag{border-color:var(--fr-p1);color:var(--fr-p1);background:rgba(91,108,255,.06)}
.fr-car-prev{width:110px;height:52px;border-radius:10px;background-color:rgba(128,132,150,.15);background-size:cover;background-position:center;border:1px solid var(--fr-bd);display:inline-block;flex:none}
body.fr-login-body{min-height:100vh;display:grid;place-items:center;padding:20px}
#frAdLoginForm{width:100%;max-width:380px;text-align:center}
#frAdLoginForm.shake{animation:frShake .4s}
@keyframes frShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-8px)}75%{transform:translateX(8px)}}
@media(max-width:900px){
#frSide{transform:translateX(-105%);transition:transform .3s;box-shadow:0 0 40px rgba(0,0,0,.2)}
#frSide.open{transform:none}
#frAdMain{margin-left:0}
#frMenuBtn{display:grid}
.fr-stat-g{grid-template-columns:repeat(2,1fr)}
}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<?php if (!$frIsAd): ?>
<body class="fr-login-body">
<form class="fr-mcard" id="frAdLoginForm" style="opacity:1">
<div class="fr-logo-ic" style="width:56px;height:56px;font-size:22px;margin:0 auto 14px;border-radius:18px"><i class="fa-solid fa-cubes"></i></div>
<h1 style="margin:0;font-size:20px">管理控制台</h1>
<p style="color:var(--fr-tx3);font-size:12px;margin:6px 0 22px"><?php echo htmlspecialchars($frCfg['siteName'] ?? '软件仓库'); ?> · 后台管理系统</p>
<div class="fr-frow" style="text-align:left"><label class="fr-flabel" for="frAdUser">管理员账号</label><input class="fr-inp" id="frAdUser" autocomplete="username" required></div>
<div class="fr-frow" style="text-align:left"><label class="fr-flabel" for="frAdPass">密码</label><input class="fr-inp" id="frAdPass" type="password" autocomplete="current-password" required></div>
<button class="fr-btn fr-btn-p" style="width:100%" id="frAdLoginBtn"><i class="fa-solid fa-key"></i> 登 录</button>
<p style="color:var(--fr-tx3);font-size:11px;margin:16px 0 0">管理员账号在安装向导中设置，如遗忘请查看 README</p>
</form>
<div id="frToast" role="status"></div>
<script>
const frQ=s=>document.querySelector(s);
function frToast(msg,type='ok'){const box=frQ('#frToast');const el=document.createElement('div');el.className='fr-toast '+type;el.innerHTML='<i class="fa-solid '+(type==='ok'?'fa-circle-check':'fa-circle-exclamation')+'"></i><span>'+msg+'</span>';box.appendChild(el);setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),420)},2400)}
frQ('#frAdLoginForm').addEventListener('submit',async e=>{e.preventDefault();const btn=frQ('#frAdLoginBtn');btn.disabled=true;
try{const r=await fetch('?frApi=login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user:frQ('#frAdUser').value.trim(),pass:frQ('#frAdPass').value})});const j=await r.json();
if(j.code===0){frToast('登录成功');setTimeout(()=>location.reload(),400)}else{frToast(j.msg||'登录失败','bad');const f=frQ('#frAdLoginForm');f.classList.remove('shake');void f.offsetWidth;f.classList.add('shake')}}catch(err){frToast('网络异常','bad')}
btn.disabled=false});
</script>
</body>
<?php exit; endif; ?>
<body>
<aside id="frSide" class="fr-glass">
<div class="fr-ad-logo"><span class="fr-logo-ic"><i class="fa-solid fa-cubes"></i></span><div><b><?php echo htmlspecialchars($frCfg['siteName'] ?? '软件仓库'); ?></b><span>管理控制台</span></div></div>
<nav id="frAdMenu" aria-label="后台菜单">
<button data-sec="dash" class="on"><i class="fa-solid fa-chart-pie"></i> 数据看板</button>
<button data-sec="apps"><i class="fa-solid fa-box-open"></i> 软件管理</button>
<button data-sec="cats"><i class="fa-solid fa-tags"></i> 分类标签</button>
<button data-sec="cars"><i class="fa-solid fa-images"></i> 轮播管理</button>
<button data-sec="notices"><i class="fa-solid fa-bullhorn"></i> 公告管理</button>
<button data-sec="cmts"><i class="fa-solid fa-comments"></i> 评论审核 <b class="fr-bdg" id="frBdgC"></b></button>
<button data-sec="users"><i class="fa-solid fa-users"></i> 用户管理</button>
<button data-sec="subs"><i class="fa-solid fa-inbox"></i> 投稿审核 <b class="fr-bdg" id="frBdgS"></b></button>
<button data-sec="logs"><i class="fa-solid fa-clock-rotate-left"></i> 操作日志</button>
<button data-sec="cfg"><i class="fa-solid fa-gear"></i> 网站配置</button>
</nav>
<div class="fr-ad-side-f"><a href="index.php" target="_blank"><i class="fa-solid fa-desktop"></i> 查看前台</a><button id="frAdOut"><i class="fa-solid fa-arrow-right-from-bracket"></i> 退出登录</button></div>
</aside>
<div id="frAdMain">
<header id="frAdTop" class="fr-glass"><button id="frMenuBtn" aria-label="菜单"><i class="fa-solid fa-bars"></i></button><b id="frAdTitle">数据看板</b><div style="margin-left:auto;display:flex;gap:10px;align-items:center"><button class="fr-btn fr-btn-g" id="frAdTheme" style="padding:8px 12px" aria-label="切换主题"><i class="fa-solid fa-moon"></i></button><span class="fr-badge fr-badge-b" style="padding:7px 14px"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($frCfg['adminUser'] ?? 'admin'); ?></span></div></header>
<main id="frAdBox" aria-live="polite"></main>
</div>
<div id="frToast" role="status"></div>
<script>
const frQ=s=>document.querySelector(s);
const frA=s=>document.querySelectorAll(s);
const frEsc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const frNum=n=>{n=+n||0;return n>=10000?(n/10000).toFixed(1).replace(/\.0$/,'')+'万':String(n)};
const frDate=t=>{const d=new Date((+t)*1000),p=x=>String(x).padStart(2,'0');return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+' '+p(d.getHours())+':'+p(d.getMinutes())};
const frStars=r=>{r=+r||0;let h='';for(let i=1;i<=5;i++)h+=r>=i?'<i class="fa-solid fa-star"></i>':r>=i-.5?'<i class="fa-solid fa-star-half-stroke"></i>':'<i class="fa-regular fa-star"></i>';return h};
const frIcons=['fa-cube','fa-briefcase','fa-palette','fa-code','fa-clapperboard','fa-gears','fa-shield-halved','fa-graduation-cap','fa-video','fa-music','fa-camera','fa-pen-nib','fa-bolt','fa-cloud','fa-database','fa-terminal','fa-gamepad','fa-book','fa-heart-pulse','fa-cart-shopping','fa-map-location-dot','fa-language','fa-calculator','fa-wand-magic-sparkles','fa-file-zipper','fa-network-wired','fa-rocket'];
const frOvs=[];
async function frApi(act,data={}){try{const r=await fetch('?frApi='+encodeURIComponent(act),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const j=await r.json();if(j.code===401){location.reload();throw j}if(j.code!==0){if(j.msg)frToast(j.msg,'bad');throw j}return j}catch(e){if(e.code===undefined)frToast('网络异常','bad');throw e}}
function frToast(msg,type='ok'){const box=frQ('#frToast');const el=document.createElement('div');el.className='fr-toast '+type;el.innerHTML='<i class="fa-solid '+(type==='ok'?'fa-circle-check':type==='warn'?'fa-triangle-exclamation':'fa-circle-exclamation')+'"></i><span>'+frEsc(msg)+'</span>';box.appendChild(el);setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),420)},2400)}
function frToastUp(msg){const box=frQ('#frToast');const el=document.createElement('div');el.className='fr-toast';el.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i><span>'+frEsc(msg)+' 0%</span>';box.appendChild(el);return{set:p=>{el.querySelector('span').textContent=msg+' '+p+'%'},off:()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),420)}}}
let frUpTip=null;
function frUpXhr(file,kind){return new Promise((res,rej)=>{const x=new XMLHttpRequest();const fd=new FormData();fd.append('frFile',file);x.open('POST','?frApi=upload&kind='+encodeURIComponent(kind));x.upload.onprogress=e=>{if(e.lengthComputable&&frUpTip)frUpTip.set(Math.round(e.loaded/e.total*100))};x.onload=()=>{try{const j=JSON.parse(x.responseText);if(j.code===0)res(j);else rej(j)}catch(e){rej({msg:'上传失败'})}};x.onerror=()=>rej({msg:'网络异常'});x.send(fd)})}
function frModal(html,o={}){const ov=document.createElement('div');ov.className='fr-ov';ov.innerHTML='<div class="fr-mcard" role="dialog" aria-modal="true"><div class="fr-m-h"><div class="fr-m-t">'+(o.title||'')+'</div><button class="fr-m-x" aria-label="关闭"><i class="fa-solid fa-xmark"></i></button></div><div class="fr-m-b">'+html+'</div></div>';document.body.appendChild(ov);requestAnimationFrame(()=>requestAnimationFrame(()=>ov.classList.add('on')));document.body.style.overflow='hidden';const close=()=>{ov.classList.remove('on');setTimeout(()=>{ov.remove();const i=frOvs.indexOf(close);if(i>-1)frOvs.splice(i,1);if(!frOvs.length)document.body.style.overflow=''},240)};frOvs.push(close);ov.querySelector('.fr-m-x').onclick=close;ov.addEventListener('click',e=>{if(e.target===ov)close()});return{ov,close,body:ov.querySelector('.fr-m-b')}}
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&frOvs.length)frOvs[frOvs.length-1]()});
function frConfirm(msg,cb,title='操作确认'){const m=frModal('<p style="margin:0 0 20px;color:var(--fr-tx2)">'+msg+'</p><div style="display:flex;gap:10px;justify-content:flex-end"><button class="fr-btn fr-btn-g" data-c>取消</button><button class="fr-btn fr-btn-p" data-o>确认</button></div>',{title});m.body.querySelector('[data-c]').onclick=m.close;m.body.querySelector('[data-o]').onclick=()=>{m.close();cb()}}
const frAvaAd=(u,s=36)=>u.ava?'<span class="fr-ava" style="width:'+s+'px;height:'+s+'px"><img src="'+frEsc(u.ava)+'" alt=""></span>':'<span class="fr-ava" style="--h:'+(u.hue??220)+';width:'+s+'px;height:'+s+'px;font-size:'+Math.round(s*.42)+'px">'+frEsc(String(u.name).slice(0,1))+'</span>';
const frAd={sec:'dash',cats:[]};
async function adDash(){const j=await frApi('stats');const box=frQ('#frAdBox');
const ics=['fa-eye','fa-chart-line','fa-download','fa-cloud-arrow-down','fa-users','fa-box-open','fa-comments','fa-inbox'];
const bdgC=j.cards['待审评论'],bdgS=j.cards['待审投稿'];
const bc=frQ('#frBdgC'),bs=frQ('#frBdgS');if(bc){bc.style.display=bdgC?'inline-block':'none';bc.textContent=bdgC}if(bs){bs.style.display=bdgS?'inline-block':'none';bs.textContent=bdgS}
const mx=Math.max(...j.days.map(d=>d.v),1);
box.innerHTML='<div class="fr-stat-g">'+Object.entries(j.cards).map((c,i)=>'<div class="fr-card fr-stat"><span><i class="fa-solid '+ics[i]+'"></i> '+c[0]+'</span><b>'+frNum(c[1])+'</b></div>').join('')+'</div>'
+'<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:14px" class="fr-ad-cols"><div class="fr-card"><b style="display:block;margin-bottom:6px">近 14 日访问趋势</b><div class="fr-chart">'+j.days.map(d=>'<div class="fr-bar" title="'+frEsc(d.d)+'：'+d.v+' 次访问"><i style="height:'+Math.max(3,d.v/mx*100)+'%"></i><span>'+frEsc(d.d)+'</span></div>').join('')+'</div></div>'
+'<div style="display:grid;gap:14px"><div class="fr-card"><b style="display:block;margin-bottom:10px">下载排行 TOP5</b>'+j.top.map((a,i)=>'<div class="fr-log-item"><b style="width:20px;color:'+(i<3?'var(--fr-warn)':'var(--fr-tx3)')+'">'+(i+1)+'</b><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+frEsc(a.name)+'</span><span style="color:var(--fr-tx3);font-size:12px">'+frNum(a.downloads)+'</span></div>').join('')+'</div>'
+'<div class="fr-card"><b style="display:block;margin-bottom:10px">最近操作</b>'+(j.logs.length?j.logs.map(l=>'<div class="fr-log-item" style="display:block"><span style="font-size:12px;color:var(--fr-tx3)">'+l.at+' · '+frEsc(l.who)+'</span><div>'+frEsc(l.act)+(l.detail?'：'+frEsc(l.detail):'')+'</div></div>').join(''):'<span style="color:var(--fr-tx3);font-size:12px">暂无日志</span>')+'</div></div></div>';
if(matchMedia('(max-width:900px)').matches)frA('.fr-ad-cols').forEach(e=>e.style.gridTemplateColumns='1fr')}
function frAppIconCell(a){return a.iconImg?'<span class="fr-ic" style="width:38px;height:38px"><img src="'+frEsc(a.iconImg)+'" alt=""></span>':'<span class="fr-ic" style="--h1:'+a.hue+';--h2:'+a.hue2+';width:38px;height:38px;font-size:15px"><i class="fa-solid '+frEsc(a.icon)+'"></i></span>'}
function adApps(){frApi('apps').then(j=>{frApi('cats').then(cj=>{frAd.cats=cj.cats;const box=frQ('#frAdBox');
box.innerHTML='<div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap"><input class="fr-inp" id="frAppQ" placeholder="搜索软件名称 / 开发者" style="max-width:260px"><button class="fr-btn fr-btn-p" id="frAppAdd" style="margin-left:auto"><i class="fa-solid fa-plus"></i> 新增软件</button></div><div class="fr-card" style="padding:6px 14px"><div class="fr-tb-wrap"><table class="fr-tb"><thead><tr><th>软件</th><th>分类</th><th>下载量</th><th>评分</th><th>置顶</th><th>上架</th><th>操作</th></tr></thead><tbody id="frAppRows"></tbody></table></div></div>';
const draw=(kw='')=>{const list=j.apps.filter(a=>!kw||(a.name+a.dev).toLowerCase().includes(kw.toLowerCase()));frQ('#frAppRows').innerHTML=list.map(a=>'<tr><td><div style="display:flex;gap:10px;align-items:center">'+frAppIconCell(a)+'<div><b>'+frEsc(a.name)+'</b>'+(a.top?' <span class="fr-badge fr-badge-b">顶</span>':'')+((a.iconImg||(a.shotsImg&&a.shotsImg.length))?' <span class="fr-badge fr-badge-n"><i class="fa-regular fa-image"></i></span>':'')+'<div style="color:var(--fr-tx3);font-size:11px">'+frEsc(a.dev)+' · v'+frEsc(a.ver)+'</div></div></div></td><td>'+frEsc(a.catName)+'</td><td>'+frNum(a.downloads)+'</td><td>'+(+a.rating).toFixed(1)+'</td><td><button class="fr-sw '+(a.top?'on':'')+'" data-f="top" data-id="'+a.id+'" aria-label="置顶开关"></button></td><td><button class="fr-sw '+(a.status?'on':'')+'" data-f="status" data-id="'+a.id+'" aria-label="上架开关"></button></td><td style="white-space:nowrap"><button class="fr-mini" data-e="'+a.id+'"><i class="fa-solid fa-pen"></i>编辑</button><button class="fr-mini danger" data-d="'+a.id+'"><i class="fa-regular fa-trash-can"></i>删除</button></td></tr>').join('')||'<tr><td colspan="7" style="text-align:center;color:var(--fr-tx3);padding:30px">暂无数据</td></tr>';
frA('[data-f]').forEach(s=>s.onclick=async()=>{await frApi('appFlag',{id:+s.dataset.id,key:s.dataset.f});s.classList.toggle('on');frToast('已更新')});
frA('[data-e]').forEach(b=>b.onclick=()=>frAppEdit(j.apps.find(x=>x.id===+b.dataset.e),()=>adApps()));
frA('[data-d]').forEach(b=>b.onclick=()=>{const a=j.apps.find(x=>x.id===+b.dataset.d);frConfirm('确定删除「'+frEsc(a.name)+'」吗？相关评论将一并删除，此操作不可恢复。',async()=>{await frApi('appDel',{id:a.id});frToast('已删除');adApps()},'删除确认')})};
draw();frQ('#frAppQ').oninput=e=>draw(e.target.value.trim());frQ('#frAppAdd').onclick=()=>frAppEdit(null,()=>adApps())})})}
function frHueRow(label,id,v){return '<div class="fr-frow"><label class="fr-flabel">'+label+'</label><div style="display:flex;gap:12px;align-items:center"><input type="range" min="0" max="359" value="'+v+'" id="'+id+'" style="flex:1;accent-color:var(--fr-p1)"><span id="'+id+'v" style="color:var(--fr-tx3);font-size:12px;width:34px;text-align:right">'+v+'</span></div></div>'}
function frAppEdit(a,done){const isNew=!a;const m=frModal('<div class="fr-frow"><label class="fr-flabel">软件名称 *</label><input class="fr-inp" id="frEn" value="'+frEsc(a?.name||'')+'" maxlength="20"></div>'
+'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div class="fr-frow"><label class="fr-flabel">分类</label><select class="fr-inp" id="frEc">'+frAd.cats.map(c=>'<option value="'+c.id+'" '+((a&&a.catId===c.id)?'selected':'')+'>'+frEsc(c.name)+'</option>').join('')+'</select></div><div class="fr-frow"><label class="fr-flabel">开发者</label><input class="fr-inp" id="frEd" value="'+frEsc(a?.dev||'')+'"></div></div>'
+'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div class="fr-frow"><label class="fr-flabel">版本</label><input class="fr-inp" id="frEv" value="'+frEsc(a?.ver||'1.0')+'"></div><div class="fr-frow"><label class="fr-flabel">大小</label><input class="fr-inp" id="frEs" value="'+frEsc(a?.size||'')+'" placeholder="如 50 MB"></div></div>'
+'<div class="fr-frow"><label class="fr-flabel">软件图标</label><div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap"><div class="fr-ic-prev" id="frEicPrev"><i class="fa-regular fa-image"></i></div><div style="flex:1;min-width:220px"><div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap"><button type="button" class="fr-mini" id="frEicUp" style="margin:0"><i class="fa-solid fa-cloud-arrow-up"></i> 上传图片图标</button><button type="button" class="fr-mini danger" id="frEicDel" style="margin:0;display:none"><i class="fa-regular fa-trash-can"></i> 移除图片</button></div><div class="fr-icon-grid" id="frEi">'+frIcons.map(ic=>'<button type="button" data-i="'+ic+'" class="'+((a?.icon||'fa-cube')===ic?'on':'')+'"><i class="fa-solid '+ic+'"></i></button>').join('')+'</div><div style="color:var(--fr-tx3);font-size:11px;margin-top:8px">上传图片后优先展示图片，移除后回退为矢量图标 + 主题色</div></div></div><input type="file" id="frEicFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden></div>'
+'<div style="display:flex;gap:12px;align-items:flex-start">'+('<div style="flex:1">'+frHueRow('图标主色相','frEh',a?.hue??220)+frHueRow('图标副色相','frEh2',a?.hue2??260)+'</div><div class="fr-hue-prev" id="frEhp" style="--h1:'+(a?.hue??220)+';--h2:'+(a?.hue2??260)+'"></div>')+'</div>'
+'<div class="fr-frow"><label class="fr-flabel">标签（逗号分隔）</label><input class="fr-inp" id="frEt" value="'+frEsc((a?.tags||[]).join(','))+'"></div>'
+'<div class="fr-frow"><label class="fr-flabel">一句话简介</label><input class="fr-inp" id="frEsh" value="'+frEsc(a?.short||'')+'" maxlength="40"></div>'
+'<div class="fr-frow"><label class="fr-flabel">详细介绍（换行分段，- 开头为列表项）</label><textarea class="fr-inp" id="frEdx" rows="5">'+frEsc(a?.desc||'')+'</textarea></div>'
+'<div class="fr-frow"><label class="fr-flabel">软件截图（最多 8 张，未上传时前台使用主题色模拟图）</label><div id="frEshots" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px"></div><button type="button" class="fr-drop" id="frEShAdd"><i class="fa-solid fa-cloud-arrow-up"></i><span>点击选择或拖拽图片到此处添加</span></button><input type="file" id="frEShFile" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden></div>'
+'<div class="fr-frow"><label class="fr-flabel">下载链接（每行一条：名称|地址）</label><textarea class="fr-inp" id="frEl" rows="3">'+frEsc((a?.links||[]).map(l=>l.label+'|'+l.url).join('\n'))+'</textarea></div>'
+'<button class="fr-btn fr-btn-p" id="frEsv" style="width:100%">'+(isNew?'创建软件':'保存修改')+'</button>',{title:isNew?'新增软件':'编辑软件'});
let icon=a?.icon||'fa-cube';
let iconImg=a?.iconImg||'';
let shots=a?.shotsImg?[...a.shotsImg]:[];
m.body.querySelectorAll('#frEi button').forEach(b=>b.onclick=()=>{icon=b.dataset.i;m.body.querySelectorAll('#frEi button').forEach(x=>x.classList.toggle('on',x===b))});
const sync=()=>{const h1=m.body.querySelector('#frEh').value,h2=m.body.querySelector('#frEh2').value;m.body.querySelector('#frEhv').textContent=h1;m.body.querySelector('#frEh2v').textContent=h2;const pv=m.body.querySelector('#frEhp');pv.style.setProperty('--h1',h1);pv.style.setProperty('--h2',h2)};
m.body.querySelector('#frEh').oninput=sync;m.body.querySelector('#frEh2').oninput=sync;
const drawIc=()=>{const p=m.body.querySelector('#frEicPrev');p.innerHTML=iconImg?'<img src="'+frEsc(iconImg)+'" alt="">':'<i class="fa-regular fa-image"></i>';m.body.querySelector('#frEicDel').style.display=iconImg?'':'none'};
drawIc();
m.body.querySelector('#frEicUp').onclick=()=>m.body.querySelector('#frEicFile').click();
m.body.querySelector('#frEicDel').onclick=()=>{iconImg='';drawIc()};
m.body.querySelector('#frEicFile').onchange=async e=>{const f=e.target.files[0];e.target.value='';if(!f)return;frUpTip=frToastUp('正在上传图标');try{const j=await frUpXhr(f,'icon');frUpTip.off();iconImg=j.url;drawIc();frToast('图标已上传，保存软件后生效')}catch(err){frUpTip.off();frToast(err.msg||'上传失败','bad')}};
const drawShots=()=>{m.body.querySelector('#frEshots').innerHTML=shots.map((u,i)=>'<div class="fr-sh-thumb"><img src="'+frEsc(u)+'" alt=""><button type="button" data-x="'+i+'" aria-label="删除截图"><i class="fa-solid fa-xmark"></i></button></div>').join('')};
drawShots();
m.body.querySelector('#frEshots').onclick=e=>{const b=e.target.closest('[data-x]');if(b){shots.splice(+b.dataset.x,1);drawShots()}};
const dz=m.body.querySelector('#frEShAdd');
dz.onclick=()=>m.body.querySelector('#frEShFile').click();
['dragover','dragenter'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('drag')}));
['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.remove('drag')}));
dz.addEventListener('drop',e=>frAddFiles(e.dataTransfer.files));
m.body.querySelector('#frEShFile').onchange=e=>{frAddFiles(e.target.files);e.target.value=''};
async function frAddFiles(fl){for(const f of fl){if(shots.length>=8){frToast('最多上传 8 张截图','warn');break}if(!/^image\/(jpeg|png|gif|webp)$/.test(f.type)){frToast(f.name+' 格式不支持','bad');continue}frUpTip=frToastUp('正在上传截图');try{const j=await frUpXhr(f,'shot');shots.push(j.url);drawShots()}catch(err){frToast(err.msg||'上传失败','bad')}frUpTip.off()}}
m.body.querySelector('#frEsv').onclick=async()=>{const btn=m.body.querySelector('#frEsv');btn.disabled=true;try{await frApi('appSave',{id:a?.id||0,name:m.body.querySelector('#frEn').value,catId:+m.body.querySelector('#frEc').value,dev:m.body.querySelector('#frEd').value,ver:m.body.querySelector('#frEv').value,size:m.body.querySelector('#frEs').value,icon,hue:+m.body.querySelector('#frEh').value,hue2:+m.body.querySelector('#frEh2').value,iconImg,shotsImg:shots,tags:m.body.querySelector('#frEt').value,short:m.body.querySelector('#frEsh').value,desc:m.body.querySelector('#frEdx').value,links:m.body.querySelector('#frEl').value});frToast('保存成功'+(isNew?'，新软件默认为下架状态':''));m.close();done()}catch(e){}btn.disabled=false}}
function adCats(){frApi('cats').then(j=>{frAd.cats=j.cats;const box=frQ('#frAdBox');
box.innerHTML='<div style="display:flex;justify-content:flex-end;margin-bottom:14px"><button class="fr-btn fr-btn-p" id="frCatAdd"><i class="fa-solid fa-plus"></i> 新增分类</button></div><div class="fr-card" style="padding:6px 14px"><div class="fr-tb-wrap"><table class="fr-tb"><thead><tr><th>图标</th><th>名称</th><th>软件数</th><th>操作</th></tr></thead><tbody>'+j.cats.map(c=>'<tr><td><span class="fr-badge fr-badge-b" style="padding:8px 12px"><i class="fa-solid '+frEsc(c.icon)+'"></i></span></td><td><b>'+frEsc(c.name)+'</b></td><td>'+c.count+'</td><td><button class="fr-mini" data-e="'+c.id+'"><i class="fa-solid fa-pen"></i>编辑</button><button class="fr-mini danger" data-d="'+c.id+'"><i class="fa-regular fa-trash-can"></i>删除</button></td></tr>').join('')+'</tbody></table></div></div>';
const edit=c=>{const m=frModal('<div class="fr-frow"><label class="fr-flabel">分类名称 *</label><input class="fr-inp" id="frCn" value="'+frEsc(c?.name||'')+'" maxlength="10"></div><div class="fr-frow"><label class="fr-flabel">图标</label><div class="fr-icon-grid" id="frCi">'+frIcons.map(ic=>'<button type="button" data-i="'+ic+'" class="'+((c?.icon||'fa-tags')===ic?'on':'')+'"><i class="fa-solid '+ic+'"></i></button>').join('')+'</div></div><button class="fr-btn fr-btn-p" id="frCsave" style="width:100%">保存</button>',{title:c?'编辑分类':'新增分类'});
let ic=c?.icon||'fa-tags';m.body.querySelectorAll('#frCi button').forEach(b=>b.onclick=()=>{ic=b.dataset.i;m.body.querySelectorAll('#frCi button').forEach(x=>x.classList.toggle('on',x===b))});
m.body.querySelector('#frCsave').onclick=async()=>{try{await frApi('catSave',{id:c?.id||0,name:m.body.querySelector('#frCn').value,icon:ic});frToast('已保存');m.close();adCats()}catch(e){}}};
frQ('#frCatAdd').onclick=()=>edit(null);
frA('[data-e]').forEach(b=>b.onclick=()=>edit(j.cats.find(x=>x.id===+b.dataset.e)));
frA('[data-d]').forEach(b=>b.onclick=()=>frConfirm('确定删除该分类吗？',async()=>{try{await frApi('catDel',{id:+b.dataset.d});frToast('已删除');adCats()}catch(e){}},'删除分类'))})}
function adCars(){frApi('cars').then(j=>{const box=frQ('#frAdBox');
box.innerHTML='<div style="display:flex;justify-content:flex-end;margin-bottom:14px"><button class="fr-btn fr-btn-p" id="frCarAdd"><i class="fa-solid fa-plus"></i> 新增轮播</button></div><div style="display:grid;gap:12px">'+j.cars.map(c=>'<div class="fr-card" style="display:flex;gap:14px;align-items:center;padding:14px 18px"><span style="width:110px;height:56px;border-radius:12px;flex:none;'+(c.img?'background:url('+frEsc(c.img)+') center/cover':'background:linear-gradient(120deg,hsl('+c.hue+' 72% 56%),hsl('+((+c.hue+40)%360)+' 70% 46%))')+'"></span><div style="flex:1;min-width:0"><b>'+frEsc(c.title)+'</b>'+(c.top?' <span class="fr-badge fr-badge-b">置顶</span>':'')+(c.img?' <span class="fr-badge fr-badge-n"><i class="fa-regular fa-image"></i> 图片</span>':'')+'<div style="color:var(--fr-tx3);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+frEsc(c.sub)+' · 跳转 '+frEsc(c.link)+'</div></div><button class="fr-mini" data-e="'+c.id+'"><i class="fa-solid fa-pen"></i>编辑</button><button class="fr-mini danger" data-d="'+c.id+'"><i class="fa-regular fa-trash-can"></i></button></div>').join('')+'</div>';
const edit=c=>{const m=frModal('<div class="fr-frow"><label class="fr-flabel">主标题 *</label><input class="fr-inp" id="frRt" value="'+frEsc(c?.title||'')+'"></div><div class="fr-frow"><label class="fr-flabel">副标题</label><input class="fr-inp" id="frRs" value="'+frEsc(c?.sub||'')+'"></div><div class="fr-frow"><label class="fr-flabel">角标文案</label><input class="fr-inp" id="frRb" value="'+frEsc(c?.badge||'')+'" placeholder="如 新版上线"></div>'
+'<div class="fr-frow"><label class="fr-flabel">背景图片（可选，未上传使用主题色渐变）</label><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span id="frRimgPrev" class="fr-car-prev" style="'+(c?.img?'background-image:url('+frEsc(c.img)+')':'')+'"></span><button type="button" class="fr-mini" id="frRimgUp" style="margin:0"><i class="fa-solid fa-cloud-arrow-up"></i> 上传图片</button><button type="button" class="fr-mini danger" id="frRimgDel" style="margin:0;display:'+(c?.img?'':'none')+'"><i class="fa-regular fa-trash-can"></i> 移除</button></div><input type="file" id="frRimgFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden></div>'
+frHueRow('渐变背景色相','frRh',c?.hue??230)+'<div class="fr-frow"><label class="fr-flabel">跳转地址</label><input class="fr-inp" id="frRl" value="'+frEsc(c?.link||'#/')+'" placeholder="如 #/app/1"></div><div class="fr-frow"><label class="fr-flabel">置顶显示</label><button class="fr-sw '+((c?.top)?'on':'')+'" id="frRp" aria-label="置顶"></button></div><button class="fr-btn fr-btn-p" id="frRsv" style="width:100%">保存</button>',{title:c?'编辑轮播':'新增轮播'});
let carImg=c?.img||'';
m.body.querySelector('#frRh').oninput=e=>m.body.querySelector('#frRhv').textContent=e.target.value;
m.body.querySelector('#frRp').onclick=e=>e.target.classList.toggle('on');
m.body.querySelector('#frRimgUp').onclick=()=>m.body.querySelector('#frRimgFile').click();
m.body.querySelector('#frRimgDel').onclick=()=>{carImg='';m.body.querySelector('#frRimgPrev').style.backgroundImage='';m.body.querySelector('#frRimgDel').style.display='none'};
m.body.querySelector('#frRimgFile').onchange=async e=>{const f=e.target.files[0];e.target.value='';if(!f)return;frUpTip=frToastUp('正在上传轮播图');try{const j=await frUpXhr(f,'car');frUpTip.off();carImg=j.url;m.body.querySelector('#frRimgPrev').style.backgroundImage='url('+j.url+')';m.body.querySelector('#frRimgDel').style.display='';frToast('轮播图已上传，保存后生效')}catch(err){frUpTip.off();frToast(err.msg||'上传失败','bad')}};
m.body.querySelector('#frRsv').onclick=async()=>{try{await frApi('carSave',{id:c?.id||0,title:m.body.querySelector('#frRt').value,sub:m.body.querySelector('#frRs').value,badge:m.body.querySelector('#frRb').value,hue:+m.body.querySelector('#frRh').value,link:m.body.querySelector('#frRl').value,top:m.body.querySelector('#frRp').classList.contains('on')?1:0,img:carImg});frToast('已保存');m.close();adCars()}catch(e){}}};
frQ('#frCarAdd').onclick=()=>edit(null);
frA('[data-e]').forEach(b=>b.onclick=()=>edit(j.cars.find(x=>x.id===+b.dataset.e)));
frA('[data-d]').forEach(b=>b.onclick=()=>frConfirm('确定删除该轮播吗？',async()=>{await frApi('carDel',{id:+b.dataset.d});frToast('已删除');adCars()}))})}
function adNotices(){frApi('notices').then(j=>{const box=frQ('#frAdBox');
box.innerHTML='<div style="display:flex;justify-content:flex-end;margin-bottom:14px"><button class="fr-btn fr-btn-p" id="frNAdd"><i class="fa-solid fa-plus"></i> 发布公告</button></div><div class="fr-card" style="padding:6px 14px"><div class="fr-tb-wrap"><table class="fr-tb"><thead><tr><th>标题</th><th>内容</th><th>置顶</th><th>时间</th><th>操作</th></tr></thead><tbody>'+j.notices.map(n=>'<tr><td><b>'+frEsc(n.title)+'</b></td><td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--fr-tx2)">'+frEsc(n.content)+'</td><td>'+(n.top?'<span class="fr-badge fr-badge-b">顶</span>':'-')+'</td><td style="color:var(--fr-tx3);font-size:12px">'+frEsc(n.at)+'</td><td><button class="fr-mini" data-e="'+n.id+'"><i class="fa-solid fa-pen"></i></button><button class="fr-mini danger" data-d="'+n.id+'"><i class="fa-regular fa-trash-can"></i></button></td></tr>').join('')+'</tbody></table></div></div>';
const edit=n=>{const m=frModal('<div class="fr-frow"><label class="fr-flabel">标题 *</label><input class="fr-inp" id="frNt" value="'+frEsc(n?.title||'')+'"></div><div class="fr-frow"><label class="fr-flabel">内容 *</label><textarea class="fr-inp" id="frNc" rows="4">'+frEsc(n?.content||'')+'</textarea></div><div class="fr-frow"><label class="fr-flabel">置顶</label><button class="fr-sw '+((n?.top)?'on':'')+'" id="frNp" aria-label="置顶"></button></div><button class="fr-btn fr-btn-p" id="frNsv" style="width:100%">保存</button>',{title:n?'编辑公告':'发布公告'});
m.body.querySelector('#frNp').onclick=e=>e.target.classList.toggle('on');
m.body.querySelector('#frNsv').onclick=async()=>{try{await frApi('noticeSave',{id:n?.id||0,title:m.body.querySelector('#frNt').value,content:m.body.querySelector('#frNc').value,top:m.body.querySelector('#frNp').classList.contains('on')?1:0});frToast('已保存');m.close();adNotices()}catch(e){}}};
frQ('#frNAdd').onclick=()=>edit(null);
frA('[data-e]').forEach(b=>b.onclick=()=>edit(j.notices.find(x=>x.id===+b.dataset.e)));
frA('[data-d]').forEach(b=>b.onclick=()=>frConfirm('确定删除该公告吗？',async()=>{await frApi('noticeDel',{id:+b.dataset.d});frToast('已删除');adNotices()}))})}
function adCmts(){frApi('cmts').then(j=>{const box=frQ('#frAdBox');let f='all';
box.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px"><div class="fr-seg" id="frCmtSeg"><button data-f="all" class="on">全部</button><button data-f="0">待审核</button><button data-f="1">已通过</button><button data-f="2">已驳回</button></div></div><div id="frCmtList"></div>';
const draw=()=>{const list=j.cmts.filter(c=>f==='all'||String(c.status)===f);frQ('#frCmtList').innerHTML=list.length?list.map(c=>'<div class="fr-card" style="padding:16px 18px;margin-bottom:12px;display:flex;gap:12px;align-items:flex-start">'+(c.ava?'<span class="fr-ava" style="width:36px;height:36px"><img src="'+frEsc(c.ava)+'" alt=""></span>':'<span class="fr-ava" style="--h:'+(c.hue??220)+'">'+frEsc(String(c.user).slice(0,1))+'</span>')+'<div style="flex:1;min-width:0"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>'+frEsc(c.user)+'</b><span class="fr-badge fr-badge-n">'+frEsc(c.appName)+'</span><span class="fr-stars">'+frStars(c.stars)+'</span><span class="fr-badge '+(c.status===1?'fr-badge-g':c.status===0?'fr-badge-w':'fr-badge-r')+'">'+(c.status===1?'已通过':c.status===0?'待审核':'已驳回')+'</span><span style="color:var(--fr-tx3);font-size:11px;margin-left:auto">'+frDate(c.at)+'</span></div><p style="margin:8px 0;color:var(--fr-tx2)">'+frEsc(c.text)+'</p><div>'+(c.status!==1?'<button class="fr-mini" data-ok="'+c.id+'"><i class="fa-solid fa-check"></i>通过</button>':'')+(c.status!==2?'<button class="fr-mini" data-no="'+c.id+'"><i class="fa-solid fa-xmark"></i>驳回</button>':'')+'<button class="fr-mini danger" data-del="'+c.id+'"><i class="fa-regular fa-trash-can"></i>删除</button></div></div></div>').join(''):'<div class="fr-card" style="text-align:center;color:var(--fr-tx3);padding:40px">暂无评论</div>';
frA('[data-ok]').forEach(b=>b.onclick=async()=>{await frApi('cmtSet',{id:+b.dataset.ok,status:1});frToast('已通过，并奖励评论积分');adCmts()});
frA('[data-no]').forEach(b=>b.onclick=async()=>{await frApi('cmtSet',{id:+b.dataset.no,status:2});frToast('已驳回');adCmts()});
frA('[data-del]').forEach(b=>b.onclick=()=>frConfirm('确定删除该评论吗？',async()=>{await frApi('cmtDel',{id:+b.dataset.del});frToast('已删除');adCmts()}))};
draw();
frA('#frCmtSeg button').forEach(b=>b.onclick=()=>{f=b.dataset.f;frA('#frCmtSeg button').forEach(x=>x.classList.toggle('on',x===b));draw()})})}
function adUsers(){frApi('users').then(j=>{const box=frQ('#frAdBox');
box.innerHTML='<div class="fr-card" style="padding:6px 14px"><div class="fr-tb-wrap"><table class="fr-tb"><thead><tr><th>用户</th><th>积分</th><th>注册时间</th><th>状态</th><th>操作</th></tr></thead><tbody>'+j.users.map(u=>'<tr><td><div style="display:flex;gap:10px;align-items:center">'+frAvaAd(u,36)+'<div><b>'+frEsc(u.name)+'</b><div style="color:var(--fr-tx3);font-size:11px">'+frEsc(u.email)+'</div></div></div></td><td><button class="fr-mini" data-m="'+u.id+'"><i class="fa-solid fa-minus"></i></button><b style="margin:0 6px">'+u.points+'</b><button class="fr-mini" data-p="'+u.id+'"><i class="fa-solid fa-plus"></i></button></td><td style="color:var(--fr-tx3);font-size:12px">'+frEsc(u.at)+'</td><td>'+(u.role==='banned'?'<span class="fr-badge fr-badge-r">已停用</span>':'<span class="fr-badge fr-badge-g">正常</span>')+'</td><td><button class="fr-mini" data-b="'+u.id+'"><i class="fa-solid '+(u.role==='banned'?'fa-rotate-left':'fa-ban')+'"></i>'+(u.role==='banned'?'恢复':'停用')+'</button><button class="fr-mini danger" data-d="'+u.id+'"><i class="fa-regular fa-trash-can"></i>删除</button></td></tr>').join('')||'<tr><td colspan="5" style="text-align:center;color:var(--fr-tx3);padding:30px">暂无用户</td></tr>'+'</tbody></table></div></div>';
frA('[data-p]').forEach(b=>b.onclick=async()=>{await frApi('userPts',{id:+b.dataset.p,delta:10});frToast('积分 +10');adUsers()});
frA('[data-m]').forEach(b=>b.onclick=async()=>{await frApi('userPts',{id:+b.dataset.m,delta:-10});frToast('积分 -10');adUsers()});
frA('[data-b]').forEach(b=>b.onclick=async()=>{await frApi('userBan',{id:+b.dataset.b});frToast('已更新');adUsers()});
frA('[data-d]').forEach(b=>b.onclick=()=>{const u=j.users.find(x=>x.id===+b.dataset.d);frConfirm('确定删除用户「'+frEsc(u.name)+'」吗？',async()=>{await frApi('userDel',{id:u.id});frToast('已删除');adUsers()},'删除用户')})})}
function adSubs(){frApi('subs').then(j=>{const box=frQ('#frAdBox');
box.innerHTML=j.subs.length?j.subs.map(s=>'<div class="fr-card" style="padding:16px 18px;margin-bottom:12px"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>'+frEsc(s.name)+'</b><span class="fr-badge fr-badge-n">v'+frEsc(s.ver)+'</span><span class="fr-badge fr-badge-n">'+frEsc(s.catName)+'</span><span class="fr-badge '+(s.status===1?'fr-badge-g':s.status===0?'fr-badge-w':'fr-badge-r')+'">'+(s.status===1?'已收录':s.status===0?'待审核':'已驳回')+'</span><span style="color:var(--fr-tx3);font-size:11px;margin-left:auto">'+frEsc(s.user)+' · '+frDate(s.at)+'</span></div><p style="margin:10px 0;color:var(--fr-tx2)">'+frEsc(s.desc)+'</p><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="fr-mini" href="'+frEsc(s.link)+'" target="_blank" rel="nofollow noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i>来源链接</a>'+(s.status===0?'<button class="fr-mini" data-ok="'+s.id+'" style="color:var(--fr-ok)"><i class="fa-solid fa-check"></i>通过并生成草稿</button><button class="fr-mini danger" data-no="'+s.id+'"><i class="fa-solid fa-xmark"></i>驳回</button>':'')+'</div></div>').join(''):'<div class="fr-card" style="text-align:center;color:var(--fr-tx3);padding:40px">暂无投稿</div>';
frA('[data-ok]').forEach(b=>b.onclick=async()=>{await frApi('subSet',{id:+b.dataset.ok,status:1});frToast('已生成软件草稿，请到软件管理中完善并上架');adSubs()});
frA('[data-no]').forEach(b=>b.onclick=()=>frConfirm('确定驳回该投稿吗？',async()=>{await frApi('subSet',{id:+b.dataset.no,status:2});frToast('已驳回');adSubs()}))})}
function adLogs(){frApi('logs').then(j=>{const box=frQ('#frAdBox');
box.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px"><span style="color:var(--fr-tx3);font-size:12px">共 '+j.logs.length+' 条（最多展示 200 条）</span><button class="fr-btn fr-btn-d" id="frLogClear"><i class="fa-regular fa-trash-can"></i> 清空日志</button></div><div class="fr-card" style="padding:6px 14px"><div class="fr-tb-wrap"><table class="fr-tb"><thead><tr><th>时间</th><th>操作人</th><th>动作</th><th>详情</th><th>IP</th></tr></thead><tbody>'+j.logs.map(l=>'<tr><td style="white-space:nowrap;color:var(--fr-tx3);font-size:12px">'+l.at+'</td><td>'+frEsc(l.who)+'</td><td><span class="fr-badge fr-badge-b">'+frEsc(l.act)+'</span></td><td style="color:var(--fr-tx2)">'+frEsc(l.detail)+'</td><td style="color:var(--fr-tx3);font-size:12px">'+frEsc(l.ip)+'</td></tr>').join('')||'<tr><td colspan="5" style="text-align:center;color:var(--fr-tx3);padding:30px">暂无日志</td></tr>'+'</tbody></table></div></div>';
frQ('#frLogClear').onclick=()=>frConfirm('确定清空全部操作日志吗？',async()=>{await frApi('logClear');frToast('已清空');adLogs()})})}
function adCfg(){frApi('cfg').then(j=>{const c=j.cfg;const box=frQ('#frAdBox');
box.innerHTML='<form id="frCfgForm" style="display:grid;gap:14px;max-width:720px">'
+'<div class="fr-card"><b style="display:block;margin-bottom:14px">基础信息</b><div class="fr-frow"><label class="fr-flabel">网站名称</label><input class="fr-inp" name="siteName" value="'+frEsc(c.siteName)+'"></div><div class="fr-frow"><label class="fr-flabel">标语</label><input class="fr-inp" name="slogan" value="'+frEsc(c.slogan)+'"></div><div class="fr-frow"><label class="fr-flabel">SEO 关键词</label><input class="fr-inp" name="keywords" value="'+frEsc(c.keywords)+'"></div><div class="fr-frow"><label class="fr-flabel">网站描述</label><textarea class="fr-inp" name="desc" rows="2">'+frEsc(c.desc)+'</textarea></div><div class="fr-frow"><label class="fr-flabel">ICP 备案号</label><input class="fr-inp" name="icp" value="'+frEsc(c.icp)+'"></div><div class="fr-frow"><label class="fr-flabel">页脚说明</label><textarea class="fr-inp" name="footer" rows="2">'+frEsc(c.footer)+'</textarea></div><div class="fr-frow" style="margin:0"><label class="fr-flabel">联系邮箱</label><input class="fr-inp" name="contact" value="'+frEsc(c.contact)+'"></div></div>'
+'<div class="fr-card"><b style="display:block;margin-bottom:14px">积分规则</b><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px"><div class="fr-frow" style="margin:0"><label class="fr-flabel">注册赠送</label><input class="fr-inp" type="number" min="0" name="pReg" value="'+c.pReg+'"></div><div class="fr-frow" style="margin:0"><label class="fr-flabel">评论通过</label><input class="fr-inp" type="number" min="0" name="pComment" value="'+c.pComment+'"></div><div class="fr-frow" style="margin:0"><label class="fr-flabel">每日签到</label><input class="fr-inp" type="number" min="0" name="pSign" value="'+c.pSign+'"></div><div class="fr-frow" style="margin:0"><label class="fr-flabel">下载消耗（0 为免费）</label><input class="fr-inp" type="number" min="0" name="pDl" value="'+c.pDl+'"></div></div></div>'
+'<div class="fr-card"><b style="display:block;margin-bottom:14px">安全与功能</b><div class="fr-frow" style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px">防爬限流（拦截脚本 UA + 频率限制）</span><button type="button" class="fr-sw '+(c.antiCrawler?'on':'')+'" id="frCfgAc" aria-label="防爬开关"></button></div><div class="fr-frow" style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px">开放用户投稿</span><button type="button" class="fr-sw '+(c.enableSubmit?'on':'')+'" id="frCfgSub" aria-label="投稿开关"></button></div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:10px"><div class="fr-frow" style="margin:0"><label class="fr-flabel">限流阈值（次 / 10 秒）</label><input class="fr-inp" type="number" min="5" name="rateLimit" value="'+c.rateLimit+'"></div><div class="fr-frow" style="margin:0"><label class="fr-flabel">上传大小限制（MB）</label><input class="fr-inp" type="number" min="1" max="10" name="upMax" value="'+c.upMax+'"></div></div></div>'
+'<div class="fr-card"><b style="display:block;margin-bottom:14px">打赏二维码</b><div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><img id="frRwPrev" src="'+(c.rewardUrl?frEsc(c.rewardUrl):'fr.png')+'" alt="打赏码" style="width:96px;height:96px;border-radius:14px;object-fit:cover;border:1px solid var(--fr-bd);background:#fff"><div style="display:grid;gap:8px"><input type="hidden" name="rewardUrl" id="frRwUrl" value="'+frEsc(c.rewardUrl)+'"><button type="button" class="fr-mini" id="frRwUp" style="margin:0"><i class="fa-solid fa-cloud-arrow-up"></i> 上传收款码</button><button type="button" class="fr-mini danger" id="frRwDef" style="margin:0;display:'+(c.rewardUrl?'':'none')+'"><i class="fa-solid fa-rotate-left"></i> 恢复默认 fr.png</button><span style="color:var(--fr-tx3);font-size:11px">前台「打赏作者」弹窗将展示此图片</span></div></div><input type="file" id="frRwFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden></div>'
+'<div class="fr-card"><b style="display:block;margin-bottom:14px">管理员账号</b><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div class="fr-frow" style="margin:0"><label class="fr-flabel">管理账号</label><input class="fr-inp" name="adminUser" value="'+frEsc(c.adminUser)+'"></div><div class="fr-frow" style="margin:0"><label class="fr-flabel">新密码（留空不修改）</label><input class="fr-inp" type="password" name="newPass" autocomplete="new-password" placeholder="至少 6 位"></div></div></div>'
+'<button class="fr-btn fr-btn-p" style="justify-self:start"><i class="fa-solid fa-floppy-disk"></i> 保存配置</button></form>';
frQ('#frCfgAc').onclick=e=>e.target.classList.toggle('on');
frQ('#frCfgSub').onclick=e=>e.target.classList.toggle('on');
frQ('#frRwUp').onclick=()=>frQ('#frRwFile').click();
frQ('#frRwFile').onchange=async e=>{const f=e.target.files[0];e.target.value='';if(!f)return;frUpTip=frToastUp('正在上传收款码');try{const j=await frUpXhr(f,'reward');frUpTip.off();frQ('#frRwUrl').value=j.url;frQ('#frRwPrev').src=j.url;frQ('#frRwDef').style.display='';frToast('收款码已上传并生效')}catch(err){frUpTip.off();frToast(err.msg||'上传失败','bad')}};
frQ('#frRwDef').onclick=()=>{frQ('#frRwUrl').value='';frQ('#frRwPrev').src='fr.png';frQ('#frRwDef').style.display='none';frToast('已恢复默认，保存配置后生效')};
frQ('#frCfgForm').onsubmit=async e=>{e.preventDefault();const fd=new FormData(e.target);const data={};fd.forEach((v,k)=>data[k]=v);data.antiCrawler=frQ('#frCfgAc').classList.contains('on')?1:0;data.enableSubmit=frQ('#frCfgSub').classList.contains('on')?1:0;
try{await frApi('cfgSave',data);frToast('配置已保存')}catch(err){}}})}
const frAdRender={dash:adDash,apps:adApps,cats:adCats,cars:adCars,notices:adNotices,cmts:adCmts,users:adUsers,subs:adSubs,logs:adLogs,cfg:adCfg};
const frAdTitles={dash:'数据看板',apps:'软件管理',cats:'分类标签',cars:'轮播管理',notices:'公告管理',cmts:'评论审核',users:'用户管理',subs:'投稿审核',logs:'操作日志',cfg:'网站配置'};
function adGo(sec){frAd.sec=sec;frA('#frAdMenu button').forEach(b=>b.classList.toggle('on',b.dataset.sec===sec));frQ('#frAdTitle').textContent=frAdTitles[sec];frQ('#frSide').classList.remove('open');frAdRender[sec]()}
frA('#frAdMenu button').forEach(b=>b.onclick=()=>adGo(b.dataset.sec));
frQ('#frMenuBtn').onclick=()=>frQ('#frSide').classList.toggle('open');
frQ('#frAdOut').onclick=()=>frConfirm('确定退出管理后台吗？',async()=>{await frApi('logout');location.reload()});
frQ('#frAdTheme').onclick=()=>{const t=document.documentElement.dataset.frTheme==='dark'?'light':'dark';document.documentElement.dataset.frTheme=t;localStorage.setItem('frTheme',t);frQ('#frAdTheme i').className=t==='dark'?'fa-solid fa-sun':'fa-solid fa-moon'};
frQ('#frAdTheme i').className=document.documentElement.dataset.frTheme==='dark'?'fa-solid fa-sun':'fa-solid fa-moon';
adGo('dash');
</script>
</body>
</html>
