<?php
require_once __DIR__ . '/frCore.php';
function frHandleApi($frCfg) {
  frGuard($frCfg);
  $frAct = $_GET['frApi'] ?? '';
  $frIn = json_decode((string)file_get_contents('php://input'), true) ?: [];
  $frUsers = frLoad('users', []);
  $frApps = frLoad('apps', []);
  $frUid = (int)($_SESSION['frUid'] ?? 0);
  $frMe = -1;
  foreach ($frUsers as $frI => $frU) if ((int)$frU['id'] === $frUid) { $frMe = $frI; break; }
  $frSaveU = function () use (&$frUsers) { frSave('users', $frUsers); };
  $frSaveA = function () use (&$frApps) { frSave('apps', $frApps); };
  $frCatMap = [];
  foreach (frLoad('cats', []) as $frC) $frCatMap[$frC['id']] = $frC['name'];
  $frPub = array_values(array_filter($frApps, fn($frA) => (int)$frA['status'] === 1));
  $frOut = fn($frA) => ['id' => $frA['id'], 'name' => $frA['name'], 'icon' => $frA['icon'], 'hue' => $frA['hue'], 'hue2' => $frA['hue2'], 'iconImg' => $frA['iconImg'] ?? '', 'shotsImg' => $frA['shotsImg'] ?? [], 'catId' => $frA['catId'], 'cat' => $frCatMap[$frA['catId']] ?? '', 'tags' => $frA['tags'], 'ver' => $frA['ver'], 'size' => $frA['size'], 'dev' => $frA['dev'], 'short' => $frA['short'], 'desc' => $frA['desc'], 'links' => $frA['links'], 'downloads' => $frA['downloads'], 'favs' => $frA['favs'], 'rating' => $frA['rating'], 'top' => $frA['top'] ?? 0, 'updated' => date('Y-m-d', $frA['updated'])];
  switch ($frAct) {
    case 'boot':
      $frHot = $frPub; usort($frHot, fn($frA, $frB) => $frB['downloads'] <=> $frA['downloads']);
      $frNew = $frPub; usort($frNew, fn($frA, $frB) => $frB['updated'] <=> $frA['updated']);
      $frCars = frLoad('cars', []); usort($frCars, fn($frA, $frB) => ($frB['top'] ?? 0) <=> ($frA['top'] ?? 0));
      $frNotices = frLoad('notices', []); usort($frNotices, fn($frA, $frB) => ($frB['top'] ?? 0) <=> ($frA['top'] ?? 0));
      frOk(['site' => ['name' => $frCfg['siteName'] ?? '软件仓库', 'slogan' => $frCfg['slogan'] ?? '', 'icp' => $frCfg['icp'] ?? '', 'footer' => $frCfg['footer'] ?? '', 'contact' => $frCfg['contact'] ?? '', 'enableSubmit' => (int)($frCfg['enableSubmit'] ?? 1), 'rewardUrl' => $frCfg['rewardUrl'] ?? ''], 'user' => $frMe >= 0 ? ['name' => $frUsers[$frMe]['name'], 'points' => $frUsers[$frMe]['points'], 'hue' => $frUsers[$frMe]['hue'], 'sign' => $frUsers[$frMe]['sign'] ?? '', 'ava' => $frUsers[$frMe]['ava'] ?? ''] : null, 'cats' => frLoad('cats', []), 'cars' => $frCars, 'notices' => $frNotices, 'hot' => array_map($frOut, array_slice($frHot, 0, 6)), 'new' => array_map($frOut, array_slice($frNew, 0, 8)), 'rank' => array_map($frOut, array_slice($frHot, 0, 10)), 'total' => count($frPub)]);
    case 'apps':
      $frQ = trim((string)($frIn['q'] ?? '')); $frCat = (int)($frIn['cat'] ?? 0); $frSort = $frIn['sort'] ?? 'hot'; $frPage = max(1, (int)($frIn['page'] ?? 1)); $frPer = 12;
      $frList = $frPub;
      if ($frCat) $frList = array_values(array_filter($frList, fn($frA) => (int)$frA['catId'] === $frCat));
      if ($frQ !== '') $frList = array_values(array_filter($frList, fn($frA) => str_contains($frA['name'] . $frA['dev'] . implode('', $frA['tags']) . $frA['short'], $frQ)));
      if ($frSort === 'new') usort($frList, fn($frA, $frB) => $frB['updated'] <=> $frA['updated']);
      elseif ($frSort === 'rating') usort($frList, fn($frA, $frB) => $frB['rating'] <=> $frA['rating']);
      else usort($frList, fn($frA, $frB) => $frB['downloads'] <=> $frA['downloads']);
      $frTotal = count($frList); $frPages = max(1, (int)ceil($frTotal / $frPer));
      frOk(['items' => array_map($frOut, array_slice($frList, ($frPage - 1) * $frPer, $frPer)), 'total' => $frTotal, 'pages' => $frPages, 'page' => $frPage]);
    case 'app':
      $frId = (int)($frIn['id'] ?? 0); $frApp = null; $frAi = -1;
      foreach ($frApps as $frI => $frA) if ((int)$frA['id'] === $frId && (int)$frA['status'] === 1) { $frAi = $frI; break; }
      if ($frAi < 0) frErr('软件不存在或已下架', 404);
      $frApps[$frAi]['views'] = (int)($frApps[$frAi]['views'] ?? 0) + 1; $frSaveA(); $frApp = $frApps[$frAi];
      $frAll = frLoad('comments', []);
      $frCms = array_values(array_filter($frAll, fn($frC) => (int)$frC['appId'] === $frId && (int)$frC['status'] === 1));
      usort($frCms, fn($frA, $frB) => $frB['at'] <=> $frA['at']);
      $frPend = $frMe >= 0 ? array_values(array_filter($frAll, fn($frC) => (int)$frC['appId'] === $frId && (int)$frC['userId'] === $frUid && (int)$frC['status'] === 0)) : [];
      $frRel = array_values(array_filter($frPub, fn($frA) => (int)$frA['catId'] === (int)$frApp['catId'] && (int)$frA['id'] !== $frId));
      usort($frRel, fn($frA, $frB) => $frB['downloads'] <=> $frA['downloads']);
      frOk(['app' => $frOut($frApp) + ['createdAt' => date('Y-m-d', $frApp['created']), 'views' => $frApp['views']], 'comments' => array_slice($frCms, 0, 30), 'pending' => $frPend, 'related' => array_map($frOut, array_slice($frRel, 0, 4)), 'faved' => $frMe >= 0 && in_array($frId, $frUsers[$frMe]['favs'] ?? [])]);
    case 'login':
      $frName = trim((string)($frIn['name'] ?? '')); $frPass = (string)($frIn['pass'] ?? '');
      foreach ($frUsers as $frU) if ($frU['name'] === $frName || $frU['email'] === $frName) { $frF = $frU; break; }
      if (!isset($frF) || !password_verify($frPass, $frF['pass'])) frErr('账号或密码错误');
      if (($frF['role'] ?? '') === 'banned') frErr('账号已被停用', 403);
      $_SESSION['frUid'] = $frF['id'];
      frLog('登录', $frF['name'] . ' 登录成功', $frF['name']);
      frOk(['user' => ['name' => $frF['name'], 'points' => $frF['points'], 'hue' => $frF['hue'], 'sign' => $frF['sign'] ?? '', 'ava' => $frF['ava'] ?? '']]);
    case 'reg':
      $frName = trim((string)($frIn['name'] ?? '')); $frEmail = trim((string)($frIn['email'] ?? '')); $frPass = (string)($frIn['pass'] ?? '');
      if (mb_strlen($frName) < 2 || mb_strlen($frName) > 12) frErr('昵称需 2-12 个字符');
      if (!filter_var($frEmail, FILTER_VALIDATE_EMAIL)) frErr('邮箱格式不正确');
      if (strlen($frPass) < 6) frErr('密码至少 6 位');
      foreach ($frUsers as $frU) if ($frU['name'] === $frName || $frU['email'] === $frEmail) frErr('昵称或邮箱已被注册');
      $frNu = ['id' => frNid($frUsers), 'name' => $frName, 'email' => $frEmail, 'pass' => password_hash($frPass, PASSWORD_DEFAULT), 'hue' => mt_rand(0, 359), 'points' => (int)($frCfg['pReg'] ?? 10), 'role' => 'user', 'at' => date('Y-m-d'), 'sign' => '', 'favs' => [], 'ava' => ''];
      $frUsers[] = $frNu; $frSaveU();
      $_SESSION['frUid'] = $frNu['id'];
      frLog('注册', '新用户 ' . $frName . ' 注册', $frName);
      frOk(['user' => ['name' => $frNu['name'], 'points' => $frNu['points'], 'hue' => $frNu['hue'], 'sign' => '', 'ava' => '']]);
    case 'logout':
      unset($_SESSION['frUid']); frOk();
    case 'upload':
      if ($frMe < 0) frErr('请先登录', 401);
      if (($_GET['kind'] ?? 'avatar') !== 'avatar') frErr('上传类型错误');
      $frUrl = frUploadHandle('avatar', $frCfg);
      $frUsers[$frMe]['ava'] = $frUrl; $frSaveU();
      frOk(['url' => $frUrl]);
    case 'fav':
      if ($frMe < 0) frErr('请先登录', 401);
      $frId = (int)($frIn['id'] ?? 0);
      $frFavs = $frUsers[$frMe]['favs'] ?? [];
      if (in_array($frId, $frFavs)) { $frFavs = array_values(array_diff($frFavs, [$frId])); $frNow = false; } else { $frFavs[] = $frId; $frNow = true; }
      $frUsers[$frMe]['favs'] = $frFavs; $frSaveU();
      $frCnt = 0;
      foreach ($frApps as $frI => $frA) if ((int)$frA['id'] === $frId) { $frApps[$frI]['favs'] = max(0, (int)$frA['favs'] + ($frNow ? 1 : -1)); $frCnt = $frApps[$frI]['favs']; $frSaveA(); break; }
      frOk(['faved' => $frNow, 'favs' => $frCnt]);
    case 'comment':
      if ($frMe < 0) frErr('请先登录', 401);
      $frId = (int)($frIn['appId'] ?? 0); $frText = trim((string)($frIn['text'] ?? '')); $frStars = min(5, max(1, (int)($frIn['stars'] ?? 5)));
      if (mb_strlen($frText) < 2 || mb_strlen($frText) > 200) frErr('评论内容需 2-200 字');
      $frCms = frLoad('comments', []);
      $frRow = ['id' => frNid($frCms), 'appId' => $frId, 'userId' => $frUid, 'user' => $frUsers[$frMe]['name'], 'hue' => $frUsers[$frMe]['hue'], 'ava' => $frUsers[$frMe]['ava'] ?? '', 'text' => $frText, 'stars' => $frStars, 'status' => 0, 'at' => time()];
      $frCms[] = $frRow; frSave('comments', $frCms);
      frLog('评论', '对软件#' . $frId . ' 提交评论', $frUsers[$frMe]['name']);
      frOk(['comment' => $frRow]);
    case 'download':
      $frId = (int)($frIn['id'] ?? 0); $frAi = -1;
      foreach ($frApps as $frI => $frA) if ((int)$frA['id'] === $frId && (int)$frA['status'] === 1) { $frAi = $frI; break; }
      if ($frAi < 0) frErr('软件不存在', 404);
      $frCost = (int)($frCfg['pDl'] ?? 0);
      if ($frCost > 0) {
        if ($frMe < 0) frErr('该软件需登录后消耗 ' . $frCost . ' 积分下载', 403);
        if ((int)$frUsers[$frMe]['points'] < $frCost) frErr('积分不足，可通过签到与评论获取', 403);
        $frUsers[$frMe]['points'] -= $frCost; $frSaveU();
      }
      $frApps[$frAi]['downloads'] = (int)$frApps[$frAi]['downloads'] + 1; $frSaveA();
      $frDls = frLoad('dls', []);
      array_unshift($frDls, ['id' => frNid($frDls), 'appId' => $frId, 'name' => $frApps[$frAi]['name'], 'uid' => $frUid, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'at' => time()]);
      frSave('dls', array_slice($frDls, 0, 800));
      $frS = frLoad('stats', []); $frD = date('Y-m-d');
      $frS['dTotal'] = ($frS['dTotal'] ?? 0) + 1;
      $frS['dToday'] = (($frS['dTodayDay'] ?? '') === $frD) ? ($frS['dToday'] ?? 0) + 1 : 1;
      $frS['dTodayDay'] = $frD;
      $frS['dDays'][$frD] = ($frS['dDays'][$frD] ?? 0) + 1;
      frSave('stats', $frS);
      frOk(['downloads' => $frApps[$frAi]['downloads'], 'cost' => $frCost]);
    case 'signin':
      if ($frMe < 0) frErr('请先登录', 401);
      $frToday = date('Y-m-d');
      if (($frUsers[$frMe]['sign'] ?? '') === $frToday) frErr('今日已签到');
      $frUsers[$frMe]['points'] = (int)$frUsers[$frMe]['points'] + (int)($frCfg['pSign'] ?? 5);
      $frUsers[$frMe]['sign'] = $frToday; $frSaveU();
      frOk(['points' => $frUsers[$frMe]['points']]);
    case 'mine':
      if ($frMe < 0) frErr('请先登录', 401);
      $frAppMap = []; foreach ($frApps as $frA) $frAppMap[(int)$frA['id']] = $frA;
      $frFavList = [];
      foreach ($frUsers[$frMe]['favs'] ?? [] as $frFid) if (isset($frAppMap[$frFid]) && (int)$frAppMap[$frFid]['status'] === 1) $frFavList[] = $frOut($frAppMap[$frFid]);
      $frMyDls = array_values(array_filter(frLoad('dls', []), fn($frD) => (int)$frD['uid'] === $frUid));
      $frMyCms = array_values(array_filter(frLoad('comments', []), fn($frC) => (int)$frC['userId'] === $frUid));
      usort($frMyCms, fn($frA, $frB) => $frB['at'] <=> $frA['at']);
      foreach ($frMyCms as $frI => $frC) $frMyCms[$frI]['appName'] = $frAppMap[$frC['appId']]['name'] ?? '';
      $frMySubs = array_values(array_filter(frLoad('subs', []), fn($frS) => (int)$frS['uid'] === $frUid));
      frOk(['favs' => $frFavList, 'dls' => array_slice($frMyDls, 0, 30), 'comments' => $frMyCms, 'subs' => $frMySubs]);
    case 'submit':
      if ($frMe < 0) frErr('请先登录', 401);
      if (empty($frCfg['enableSubmit'])) frErr('投稿通道暂未开放');
      $frName = trim((string)($frIn['name'] ?? '')); $frLink = trim((string)($frIn['link'] ?? '')); $frDesc = trim((string)($frIn['desc'] ?? ''));
      if ($frName === '' || $frLink === '' || mb_strlen($frDesc) < 10) frErr('请完整填写名称、链接与至少 10 字的推荐理由');
      $frSubs = frLoad('subs', []);
      $frRow = ['id' => frNid($frSubs), 'uid' => $frUid, 'user' => $frUsers[$frMe]['name'], 'name' => $frName, 'ver' => trim((string)($frIn['ver'] ?? '1.0')), 'catId' => (int)($frIn['catId'] ?? 1), 'link' => $frLink, 'desc' => $frDesc, 'status' => 0, 'at' => time()];
      $frSubs[] = $frRow; frSave('subs', $frSubs);
      frLog('投稿', $frName, $frUsers[$frMe]['name']);
      frOk();
    case 'report':
      $frId = (int)($frIn['appId'] ?? 0); $frWhy = trim((string)($frIn['why'] ?? ''));
      if ($frWhy === '') frErr('请填写举报原因');
      frLog('举报', '软件#' . $frId . '：' . mb_substr($frWhy, 0, 80), $frMe >= 0 ? $frUsers[$frMe]['name'] : '游客');
      frOk();
  }
  frErr('未知请求', 404);
}
$frCfg = frLoad('config', []);
if (isset($_GET['frApi'])) frHandleApi($frCfg);
frTrackVisit();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-fr-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?php echo htmlspecialchars($frCfg['siteName'] ?? '软件仓库'); ?> - <?php echo htmlspecialchars($frCfg['slogan'] ?? ''); ?></title>
<meta name="keywords" content="<?php echo htmlspecialchars($frCfg['keywords'] ?? ''); ?>">
<meta name="description" content="<?php echo htmlspecialchars($frCfg['desc'] ?? ''); ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%235b6cff'/%3E%3Ctext x='16' y='22' font-size='16' fill='white' text-anchor='middle' font-family='sans-serif' font-weight='bold'%3EFR%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/normalize/8.0.1/normalize.min.css">
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<script>!function(){try{var t=localStorage.getItem('frTheme');if(!t)t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.dataset.frTheme=t}catch(e){}}();</script>
<style>
*{box-sizing:border-box}
:root{--fr-p1:#5b6cff;--fr-p1b:#7b8aff;--fr-p2:#18c0b2;--fr-bg:#f3f4fa;--fr-card:rgba(255,255,255,.78);--fr-solid:#fff;--fr-tx:#191c26;--fr-tx2:#5d6470;--fr-tx3:#9aa1b0;--fr-bd:rgba(25,28,38,.08);--fr-sh:0 10px 30px rgba(25,28,38,.08);--fr-r:18px;--fr-rs:12px;--fr-fz1:clamp(22px,4vw,30px);--fr-fz2:19px;--fr-fz3:16px;--fr-fz4:14px;--fr-fz5:12px;--fr-nav:64px;--fr-warn:#e8a33d;--fr-bad:#e25c5c;--fr-ok:#3dbb7e;--fr-star:#f5a94b}
html[data-fr-theme="dark"]{--fr-bg:#0e1016;--fr-card:rgba(28,31,42,.72);--fr-solid:#1b1e29;--fr-tx:#eceef4;--fr-tx2:#a7adbd;--fr-tx3:#6d7484;--fr-bd:rgba(255,255,255,.08);--fr-sh:0 10px 30px rgba(0,0,0,.35)}
html{scroll-behavior:smooth}
body{margin:0;background:var(--fr-bg);color:var(--fr-tx);font:400 14px/1.65 -apple-system,BlinkMacSystemFont,"PingFang SC","HarmonyOS Sans SC","Microsoft YaHei",sans-serif;-webkit-font-smoothing:antialiased;transition:background .3s,color .3s}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(620px 320px at 88% -6%,rgba(91,108,255,.14),transparent 60%),radial-gradient(520px 280px at 6% 10%,rgba(24,192,178,.10),transparent 60%)}
a{color:inherit;text-decoration:none}
button{font:inherit;cursor:pointer;border:none;background:none;color:inherit;padding:0}
input,select,textarea{font:inherit;color:inherit}
.fr-glass,.fr-card{background:var(--fr-card);backdrop-filter:blur(20px) saturate(1.5);-webkit-backdrop-filter:blur(20px) saturate(1.5)}
.fr-card{border:1px solid var(--fr-bd);border-radius:var(--fr-r);box-shadow:var(--fr-sh)}
.fr-wrap{max-width:1080px;margin:0 auto;padding:0 20px}
.fr-inp{width:100%;background:var(--fr-solid);border:1px solid var(--fr-bd);border-radius:var(--fr-rs);padding:11px 14px;outline:none;transition:border-color .2s,box-shadow .2s}
.fr-inp:focus{border-color:var(--fr-p1);box-shadow:0 0 0 3px rgba(91,108,255,.15)}
.fr-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 22px;border-radius:var(--fr-rs);font-weight:600;font-size:var(--fr-fz4);white-space:nowrap;transition:transform .15s,box-shadow .2s,opacity .2s}
.fr-btn:active{transform:scale(.96)}
.fr-btn-p{background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));color:#fff;box-shadow:0 6px 18px rgba(91,108,255,.35)}
.fr-btn-p:hover{box-shadow:0 8px 24px rgba(91,108,255,.45)}
.fr-btn-g{background:var(--fr-solid);border:1px solid var(--fr-bd)}
.fr-btn-s{background:rgba(91,108,255,.12);color:var(--fr-p1)}
.fr-btn[disabled]{opacity:.5;pointer-events:none}
.fr-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:999px;font-size:var(--fr-fz5);background:var(--fr-solid);border:1px solid var(--fr-bd);color:var(--fr-tx2)}
.fr-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:11px}
.fr-badge-w{background:rgba(232,163,61,.14);color:var(--fr-warn)}
.fr-badge-g{background:rgba(61,187,126,.14);color:var(--fr-ok)}
.fr-badge-r{background:rgba(226,92,92,.14);color:var(--fr-bad)}
.fr-badge-b{background:rgba(91,108,255,.12);color:var(--fr-p1)}
#frNav{position:sticky;top:0;z-index:50;border-bottom:1px solid var(--fr-bd)}
.fr-nav-in{max-width:1080px;margin:auto;display:flex;align-items:center;gap:14px;padding:0 20px;height:var(--fr-nav)}
.fr-logo{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:800;flex:none}
.fr-logo-ic{width:34px;height:34px;border-radius:11px;background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));display:grid;place-items:center;color:#fff;font-size:15px;box-shadow:0 4px 12px rgba(91,108,255,.4)}
.fr-nav-links{display:flex;gap:4px;margin-left:6px}
.fr-nav-links a{padding:8px 14px;border-radius:10px;color:var(--fr-tx2);font-size:var(--fr-fz4)}
.fr-nav-links a.on,.fr-nav-links a:hover{color:var(--fr-p1);background:rgba(91,108,255,.1)}
.fr-search{flex:1;max-width:330px;position:relative;margin-left:auto}
.fr-search input{width:100%;background:var(--fr-solid);border:1px solid var(--fr-bd);border-radius:999px;padding:9px 16px 9px 38px;outline:none;font-size:13px;transition:border-color .2s}
.fr-search input:focus{border-color:var(--fr-p1)}
.fr-search i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--fr-tx3);font-size:12px}
.fr-icon-btn{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;color:var(--fr-tx2);background:var(--fr-solid);border:1px solid var(--fr-bd);transition:.2s;flex:none}
.fr-icon-btn:hover{color:var(--fr-p1);border-color:rgba(91,108,255,.4)}
.fr-ava{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;flex:none;background:linear-gradient(135deg,hsl(var(--h,220) 75% 60%),hsl(calc(var(--h,220) + 32) 70% 48%))}
.fr-ava img{width:100%;height:100%;object-fit:cover;border-radius:inherit}
#frTab{position:fixed;left:0;right:0;bottom:0;z-index:40;display:none;border-top:1px solid var(--fr-bd);padding:6px 8px calc(6px + env(safe-area-inset-bottom))}
.fr-wrap2{display:grid;grid-template-columns:repeat(4,1fr);max-width:520px;margin:auto}
.fr-tab-b{display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 0;font-size:11px;color:var(--fr-tx3);border-radius:12px}
.fr-tab-b.on{color:var(--fr-p1)}
.fr-tab-b i{font-size:18px}
#frSplash{position:fixed;inset:0;z-index:99;background:var(--fr-bg);display:grid;place-items:center;transition:opacity .5s}
#frSplash.off{opacity:0;pointer-events:none}
.fr-sp-ic{width:70px;height:70px;border-radius:22px;background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));display:grid;place-items:center;color:#fff;font-size:28px;position:relative;animation:frPop .9s ease}
.fr-sp-ic::after{content:"";position:absolute;inset:-12px;border-radius:28px;border:2px solid rgba(91,108,255,.35);animation:frRing 1.6s ease-out infinite}
.fr-sp-t{margin-top:18px;font-weight:800;font-size:17px;text-align:center}
.fr-sp-bar{width:120px;height:4px;border-radius:4px;background:var(--fr-bd);overflow:hidden;margin:16px auto 0}
.fr-sp-bar i{display:block;height:100%;width:40%;background:var(--fr-p1);border-radius:4px;animation:frLoad 1.1s ease-in-out infinite}
@keyframes frRing{0%{transform:scale(.8);opacity:1}100%{transform:scale(1.28);opacity:0}}
@keyframes frPop{0%{transform:scale(.6);opacity:0}60%{transform:scale(1.08)}100%{transform:scale(1)}}
@keyframes frLoad{0%{transform:translateX(-100%)}100%{transform:translateX(320%)}}
#frMain{min-height:60vh}
#frMain.fr-fade{animation:frFade .35s ease}
@keyframes frFade{from{opacity:0;transform:translateY(10px)}}
.fr-car{position:relative;border-radius:var(--fr-r);overflow:hidden;box-shadow:var(--fr-sh);touch-action:pan-y;margin-top:20px}
.fr-car-track{display:flex;transition:transform .55s cubic-bezier(.22,.8,.3,1)}
.fr-car-it{min-width:100%;position:relative;aspect-ratio:21/8;background-color:#39415c;background-size:cover;background-position:center;display:flex;align-items:center;padding:0 clamp(24px,6vw,56px)}
.fr-car-grad{position:absolute;inset:0;background:linear-gradient(120deg,hsl(var(--h1) 72% 56%),hsl(var(--h2) 70% 46%))}
.fr-car-dim{position:absolute;inset:0;background:linear-gradient(rgba(12,14,24,.28),rgba(12,14,24,.42))}
.fr-car-it::after{content:"";position:absolute;width:120px;height:120px;right:130px;bottom:-55px;border-radius:50%;background:rgba(255,255,255,.14)}
.fr-car-txt{position:relative;color:#fff;z-index:1;max-width:72%}
.fr-car-badge{display:inline-block;padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.22);backdrop-filter:blur(6px);font-size:12px;margin-bottom:10px}
.fr-car-t1{font-size:clamp(18px,3.4vw,28px);font-weight:800;margin:0 0 6px}
.fr-car-t2{margin:0;opacity:.9;font-size:clamp(12px,1.8vw,14px)}
.fr-car-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:6px;z-index:2}
.fr-car-dots b{width:8px;height:8px;border-radius:4px;background:rgba(255,255,255,.45);transition:.3s;cursor:pointer}
.fr-car-dots b.on{width:22px;background:#fff}
.fr-car-btn{position:absolute;top:50%;transform:translateY(-50%);z-index:2;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.25);backdrop-filter:blur(8px);color:#fff;display:grid;place-items:center;opacity:0;transition:.25s}
.fr-car:hover .fr-car-btn{opacity:1}
.fr-car-btn.prev{left:12px}.fr-car-btn.next{right:12px}
.fr-notice{display:flex;align-items:center;gap:12px;padding:12px 18px;margin-top:16px;border-radius:var(--fr-rs);cursor:pointer}
.fr-n-ic{color:var(--fr-p1)}
.fr-n-view{flex:1;height:22px;overflow:hidden;position:relative;min-width:0}
.fr-n-item{position:absolute;inset:0;display:flex;align-items:center;gap:8px;font-size:var(--fr-fz4);color:var(--fr-tx2);transition:transform .5s,opacity .5s;opacity:0;transform:translateY(100%);min-width:0}
.fr-n-item.on{opacity:1;transform:translateY(0)}
.fr-n-item.up{opacity:0;transform:translateY(-100%)}
.fr-n-item b{color:var(--fr-p1);flex:none}
.fr-n-item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fr-search-sec{margin-top:24px}
.fr-bigsearch{display:flex;align-items:center;gap:10px;padding:10px 10px 10px 18px;border-radius:var(--fr-r);border:1px solid var(--fr-bd);box-shadow:var(--fr-sh)}
.fr-bigsearch>i{color:var(--fr-tx3)}
.fr-bigsearch input{flex:1;background:none;border:none;outline:none;font-size:15px;color:var(--fr-tx);min-width:0}
.fr-cats{display:flex;gap:10px;overflow-x:auto;padding:4px 2px;scrollbar-width:none}
.fr-cats::-webkit-scrollbar{display:none}
.fr-cat-b{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;border:1px solid var(--fr-bd);background:var(--fr-card);backdrop-filter:blur(14px);font-size:var(--fr-fz4);color:var(--fr-tx2);transition:.2s;flex:none}
.fr-cat-b i{color:var(--fr-p1)}
.fr-cat-b:hover,.fr-cat-b.on{background:rgba(91,108,255,.12);color:var(--fr-p1);border-color:transparent}
.fr-sec{margin:36px 0}
.fr-sec-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.fr-sec-t{font-size:var(--fr-fz2);font-weight:800;display:flex;align-items:center;gap:10px;margin:0}
.fr-sec-t::before{content:"";width:5px;height:20px;border-radius:3px;background:linear-gradient(var(--fr-p1),var(--fr-p1b));flex:none}
.fr-more{color:var(--fr-tx3);font-size:var(--fr-fz5)}
.fr-more:hover{color:var(--fr-p1)}
.fr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.fr-app-card{display:flex;flex-direction:column;gap:10px;padding:18px;transition:transform .25s,box-shadow .25s;position:relative;overflow:hidden}
.fr-app-card:hover{transform:translateY(-4px);box-shadow:0 16px 34px rgba(25,28,38,.14)}
.fr-app-top{display:flex;gap:12px;align-items:center}
.fr-ic{display:grid;place-items:center;border-radius:26%;color:#fff;flex:none;background:linear-gradient(135deg,hsl(var(--h1) 78% 60%),hsl(var(--h2) 72% 48%));box-shadow:0 6px 14px hsla(var(--h1),70%,50%,.35);overflow:hidden}
.fr-ic img{width:100%;height:100%;object-fit:cover;display:block}
.fr-app-name{font-weight:700;font-size:var(--fr-fz3)}
.fr-app-sub{color:var(--fr-tx3);font-size:var(--fr-fz5);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px}
.fr-app-desc{color:var(--fr-tx2);font-size:var(--fr-fz5);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
.fr-app-foot{display:flex;align-items:center;gap:8px;margin-top:auto}
.fr-stars{color:var(--fr-star);font-size:11px;display:inline-flex;gap:1px;align-items:center}
.fr-stars b{color:var(--fr-tx2);margin-left:5px;font-weight:600}
.fr-dl{color:var(--fr-tx3);font-size:var(--fr-fz5);display:inline-flex;gap:5px;align-items:center}
.fr-rank-b{position:absolute;top:0;left:0;min-width:26px;padding:3px 10px 3px 8px;font-size:11px;font-weight:800;color:#fff;background:linear-gradient(135deg,#9aa3b5,#7d8698);border-radius:0 0 12px 0;z-index:1}
.fr-rank-b.r1{background:linear-gradient(135deg,#f7b955,#f08c3a)}
.fr-rank-b.r2{background:linear-gradient(135deg,#b9c2cf,#8f9aab)}
.fr-rank-b.r3{background:linear-gradient(135deg,#d9a06b,#b97a45)}
.fr-rank-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:var(--fr-rs);transition:background .2s}
.fr-rank-row:hover{background:rgba(91,108,255,.07)}
.fr-rank-n{width:26px;text-align:center;font-weight:800;color:var(--fr-tx3);font-size:15px;flex:none}
.fr-rank-n.r1{color:#f08c3a}.fr-rank-n.r2{color:#98a2b3}.fr-rank-n.r3{color:#c98d55}
.fr-seg{display:inline-flex;background:var(--fr-solid);border:1px solid var(--fr-bd);border-radius:999px;padding:4px;gap:2px}
.fr-seg button{padding:7px 16px;border-radius:999px;font-size:var(--fr-fz5);color:var(--fr-tx2);transition:.2s}
.fr-seg button.on{background:var(--fr-p1);color:#fff;box-shadow:0 4px 10px rgba(91,108,255,.3)}
.fr-page-h{margin:26px 0 18px}
.fr-page-t{font-size:var(--fr-fz1);margin:0;display:flex;align-items:center;gap:12px}
.fr-page-s{color:var(--fr-tx3);margin:8px 0 0;font-size:var(--fr-fz4)}
.fr-filter{position:sticky;top:var(--fr-nav);z-index:30;display:flex;gap:10px;align-items:center;padding:12px 0;flex-wrap:wrap}
.fr-crumb{display:flex;gap:8px;align-items:center;color:var(--fr-tx3);font-size:var(--fr-fz5);margin:20px 0 14px;flex-wrap:wrap}
.fr-crumb a:hover{color:var(--fr-p1)}
.fr-hero{padding:26px;display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
.fr-hero h1{margin:0;font-size:clamp(20px,3.4vw,26px);display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.fr-hero-sub{color:var(--fr-tx2);margin:6px 0 12px;font-size:var(--fr-fz4)}
.fr-hero-meta{flex:1;min-width:240px}
.fr-stats{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin:4px 0 16px}
.fr-acts{display:flex;gap:10px;flex-wrap:wrap}
.fr-shots{display:flex;gap:12px;overflow-x:auto;padding:6px 2px 12px;scroll-snap-type:x mandatory;scrollbar-width:none}
.fr-shots::-webkit-scrollbar{display:none}
.fr-shot{flex:none;width:min(420px,78vw);aspect-ratio:16/10;border-radius:var(--fr-r);position:relative;overflow:hidden;scroll-snap-align:start;background:linear-gradient(135deg,hsl(var(--h1) 70% 58%),hsl(var(--h2) 66% 44%))}
.fr-shot-img{flex:none;width:min(420px,78vw);aspect-ratio:16/10;object-fit:cover;border-radius:var(--fr-r);scroll-snap-align:start;background:var(--fr-solid);border:1px solid var(--fr-bd)}
.fr-shot-ui{position:absolute;inset:16px;border-radius:12px;background:rgba(255,255,255,.16);backdrop-filter:blur(4px);padding:14px;display:flex;flex-direction:column;gap:10px}
.fr-shot-top{display:flex;gap:6px}
.fr-shot-top i{width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.75)}
.fr-shot-l{height:9px;border-radius:5px;background:rgba(255,255,255,.5)}
.fr-shot-l.s{width:55%}.fr-shot-l.m{width:78%}
.fr-shot-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-top:auto}
.fr-shot-grid i{aspect-ratio:1;border-radius:9px;background:rgba(255,255,255,.32)}
.fr-cols{display:flex;gap:18px;margin-top:4px;align-items:flex-start}
.fr-cols>div:first-child{min-width:0;flex:1.7}
.fr-cols aside{min-width:260px;flex:1;display:grid;gap:16px}
@media(max-width:860px){.fr-cols{flex-direction:column}.fr-cols aside{width:100%}}
.fr-rich p{margin:0 0 10px;color:var(--fr-tx2);font-size:var(--fr-fz4)}
.fr-li{display:flex;gap:8px;align-items:baseline}
.fr-li i{color:var(--fr-p2);font-size:12px}
.fr-side-t{margin:0 0 14px;font-size:15px}
.fr-info-t{width:100%;font-size:13px;border-collapse:collapse}
.fr-info-t td{padding:8px 0;border-bottom:1px solid var(--fr-bd)}
.fr-info-t td:first-child{color:var(--fr-tx3);width:76px}
.fr-info-t tr:last-child td{border:none}
.fr-mirror{display:flex;align-items:center;gap:12px;padding:13px 15px;border:1px solid var(--fr-bd);border-radius:var(--fr-rs);cursor:pointer;transition:.2s;margin-bottom:9px;background:var(--fr-solid)}
.fr-mirror:last-child{margin-bottom:0}
.fr-mirror:hover,.fr-mirror.on{border-color:var(--fr-p1);background:rgba(91,108,255,.08)}
.fr-radio{width:18px;height:18px;border-radius:50%;border:2px solid var(--fr-tx3);flex:none;display:grid;place-items:center}
.fr-mirror.on .fr-radio{border-color:var(--fr-p1)}
.fr-mirror.on .fr-radio::after{content:"";width:9px;height:9px;border-radius:50%;background:var(--fr-p1)}
.fr-cmt{display:flex;gap:12px;padding:16px 0;border-bottom:1px solid var(--fr-bd)}
.fr-cmt:last-child{border:none}
.fr-cmt-b{flex:1;min-width:0}
.fr-cmt-h{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fr-cmt-n{font-weight:600;font-size:var(--fr-fz4)}
.fr-cmt-t{color:var(--fr-tx3);font-size:var(--fr-fz5)}
.fr-cmt-x{color:var(--fr-tx2);margin-top:6px;font-size:var(--fr-fz4);word-break:break-all}
.fr-cmt-post{display:flex;gap:12px;margin-bottom:8px}
.fr-star-in{display:flex;gap:4px;margin-bottom:8px}
.fr-star-in button{color:var(--fr-tx3);font-size:16px;transition:transform .15s}
.fr-star-in button.on{color:var(--fr-star)}
.fr-star-in button:active{transform:scale(1.2)}
.fr-login-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px;border-radius:var(--fr-rs);background:rgba(91,108,255,.07);margin-bottom:6px;flex-wrap:wrap}
.fr-prof{display:flex;gap:16px;align-items:center;padding:24px;flex-wrap:wrap;margin-top:22px}
.fr-prof .fr-ava{width:64px;height:64px;font-size:24px;border-radius:20px}
.fr-tabs{display:flex;gap:8px;margin:18px 0;overflow-x:auto;scrollbar-width:none}
.fr-tabs::-webkit-scrollbar{display:none}
.fr-tab-i{padding:9px 18px;border-radius:999px;font-size:var(--fr-fz4);color:var(--fr-tx2);background:var(--fr-card);border:1px solid var(--fr-bd);flex:none;display:inline-flex;gap:7px;align-items:center;backdrop-filter:blur(12px)}
.fr-tab-i.on{background:var(--fr-p1);color:#fff;border-color:transparent;box-shadow:0 6px 14px rgba(91,108,255,.3)}
.fr-cta{background-image:linear-gradient(135deg,rgba(91,108,255,.16),rgba(24,192,178,.12));display:flex;align-items:center;justify-content:space-between;gap:18px;padding:26px;margin-top:38px;flex-wrap:wrap}
#frFoot{margin-top:60px;border-top:1px solid var(--fr-bd);padding:40px 0 96px;color:var(--fr-tx3);font-size:var(--fr-fz5)}
.fr-f-t{font-weight:700;color:var(--fr-tx);margin-bottom:10px;font-size:var(--fr-fz4)}
.fr-f-l{display:block;color:var(--fr-tx3);margin:7px 0;font-size:var(--fr-fz5);text-align:left}
button.fr-f-l:hover{color:var(--fr-p1)}
.fr-ov{position:fixed;inset:0;z-index:80;background:rgba(15,17,26,.45);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;transition:opacity .25s}
.fr-ov.on{opacity:1}
.fr-mcard{width:100%;max-width:440px;max-height:86vh;overflow:auto;border-radius:22px;padding:26px;transform:translateY(24px) scale(.97);transition:transform .3s cubic-bezier(.2,.9,.3,1.15);background:var(--fr-card);backdrop-filter:blur(26px) saturate(1.5);-webkit-backdrop-filter:blur(26px) saturate(1.5);border:1px solid var(--fr-bd);box-shadow:0 24px 60px rgba(10,12,20,.3)}
.fr-ov.on .fr-mcard{transform:none}
.fr-m-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:10px}
.fr-m-t{font-size:17px;font-weight:800}
.fr-m-x{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;color:var(--fr-tx3);background:rgba(128,132,150,.12);flex:none}
.fr-frow{margin-bottom:13px}
.fr-flabel{display:block;font-size:var(--fr-fz5);color:var(--fr-tx2);margin-bottom:6px}
#frToast{position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:120;display:flex;flex-direction:column;gap:8px;align-items:center;pointer-events:none;width:max-content;max-width:90vw}
.fr-toast{display:flex;align-items:center;gap:9px;padding:11px 20px;border-radius:999px;font-size:var(--fr-fz4);box-shadow:var(--fr-sh);border:1px solid var(--fr-bd);background:var(--fr-card);backdrop-filter:blur(18px);animation:frTin .3s ease;max-width:100%}
.fr-toast i{color:var(--fr-p1)}
.fr-toast.warn i{color:var(--fr-warn)}
.fr-toast.bad i{color:var(--fr-bad)}
@keyframes frTin{from{opacity:0;transform:translateY(-12px)}}
.fr-sk{position:relative;overflow:hidden;background:rgba(128,132,150,.12);border-radius:var(--fr-rs)}
.fr-sk::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);transform:translateX(-100%);animation:frSk 1.3s infinite}
html[data-fr-theme="dark"] .fr-sk::after{background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent)}
@keyframes frSk{to{transform:translateX(100%)}}
.fr-rv{opacity:0;transform:translateY(16px);transition:opacity .5s ease,transform .5s ease}
.fr-rv.fr-in{opacity:1;transform:none}
.fr-dl-stage{text-align:center;padding:16px 0 4px}
.fr-dl-num{font-size:46px;font-weight:800;color:var(--fr-p1);line-height:1}
.fr-dl-ring{width:120px;height:120px;margin:10px auto;border-radius:50%;display:grid;place-items:center;background:conic-gradient(var(--fr-p1) calc(var(--p,0)*1%),rgba(128,132,150,.15) 0)}
.fr-dl-bar{height:9px;border-radius:6px;background:rgba(128,132,150,.15);overflow:hidden;margin:14px 0 8px}
.fr-dl-bar i{display:block;height:100%;width:0;border-radius:6px;background:linear-gradient(90deg,var(--fr-p1),var(--fr-p1b));transition:width .2s}
.fr-qr{position:relative;width:210px;height:210px;margin:6px auto;border-radius:18px;overflow:hidden;background:#fff;box-shadow:inset 0 0 0 1px rgba(0,0,0,.06)}
.fr-qr-p{position:absolute;inset:14px;background:repeating-conic-gradient(#232741 0 25%,#fff 0 50%) 0 0/18px 18px;border-radius:8px}
.fr-qr img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1}
.fr-qr-c{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--fr-p1),var(--fr-p1b));color:#fff;display:grid;place-items:center;box-shadow:0 0 0 5px #fff;z-index:2}
.fr-amt:hover{border-color:var(--fr-p1);color:var(--fr-p1)}
:focus-visible{outline:2px solid var(--fr-p1);outline-offset:2px}
@media(max-width:760px){
.fr-nav-links,.fr-search{display:none}
#frTab{display:block}
body{padding-bottom:66px}
#frFoot{padding-bottom:110px}
.fr-car-it{aspect-ratio:16/9}
}
@media(max-width:640px){
.fr-grid{grid-template-columns:repeat(2,1fr);gap:12px}
.fr-app-card{padding:14px}
.fr-app-sub{max-width:110px}
.fr-ov{align-items:flex-end;padding:0}
.fr-mcard{max-width:none;border-radius:22px 22px 0 0;max-height:88vh;transform:translateY(100%)}
.fr-ov.on .fr-mcard{transform:none}
.fr-hero{padding:20px}
}
@media(max-width:400px){.fr-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}.fr-rv{opacity:1;transform:none}}
</style>
</head>
<body>
<div id="frSplash"><div><div class="fr-sp-ic"><i class="fa-solid fa-cubes"></i></div><div class="fr-sp-t"><?php echo htmlspecialchars($frCfg['siteName'] ?? '软件仓库'); ?></div><div class="fr-sp-bar"><i></i></div></div></div>
<header id="frNav" class="fr-glass">
<div class="fr-nav-in">
<a class="fr-logo" href="#/" data-nav="home" aria-label="返回首页"><span class="fr-logo-ic"><i class="fa-solid fa-cubes"></i></span><span id="frSiteName"><?php echo htmlspecialchars($frCfg['siteName'] ?? '软件仓库'); ?></span></a>
<nav class="fr-nav-links" aria-label="主导航"><a href="#/" data-nav="home">首页</a><a href="#/list" data-nav="list">软件库</a><a href="#/list/sort-rating">榜单</a><a href="#/submit" data-nav="submit">投稿</a></nav>
<div class="fr-search"><i class="fa-solid fa-magnifying-glass"></i><input id="frNavQ" type="search" placeholder="搜索软件" aria-label="搜索软件"></div>
<button id="frThemeBtn" class="fr-icon-btn" aria-label="切换深浅模式"><i class="fa-solid fa-moon"></i></button>
<div id="frUserBox"></div>
</div>
</header>
<main id="frMain" aria-live="polite"></main>
<footer id="frFoot"></footer>
<nav id="frTab" class="fr-glass" aria-label="底部导航"><div class="fr-wrap2"><a class="fr-tab-b" data-nav="home" href="#/"><i class="fa-solid fa-house"></i><span>首页</span></a><a class="fr-tab-b" data-nav="list" href="#/list"><i class="fa-solid fa-table-cells"></i><span>软件库</span></a><a class="fr-tab-b" data-nav="submit" href="#/submit"><i class="fa-solid fa-circle-plus"></i><span>投稿</span></a><a class="fr-tab-b" data-nav="mine" href="#/mine"><i class="fa-regular fa-user"></i><span>我的</span></a></div></nav>
<div id="frToast" role="status" aria-live="polite"></div>
<script>
const frS={site:{},user:null,cats:[],cars:[],notices:[],hot:[],nw:[],rank:[],total:0,route:{name:'home'},list:{q:'',cat:0,sort:'hot',page:1,pages:1},catMap:{},carI:0,carT:0,nT:0,rankTab:'downloads',appCur:null};
let frMineData=null,frBooted=false;
const frOvs=[];
const frQ=s=>document.querySelector(s);
const frA=s=>document.querySelectorAll(s);
const frEsc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const frNum=n=>{n=+n||0;return n>=10000?(n/10000).toFixed(1).replace(/\.0$/,'')+'万':String(n)};
const frDate=t=>{const d=new Date((+t)*1000),p=x=>String(x).padStart(2,'0');return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+' '+p(d.getHours())+':'+p(d.getMinutes())};
const frToday=()=>{const d=new Date(),p=x=>String(x).padStart(2,'0');return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())};
async function frApi(act,data={}){try{const r=await fetch('?frApi='+encodeURIComponent(act),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const j=await r.json();if(j.code!==0){if(j.msg)frToast(j.msg,j.code===429?'warn':'bad');throw j}return j}catch(e){if(e.code===undefined)frToast('网络异常，请稍后再试','bad');throw e}}
function frToast(msg,type='ok'){const box=frQ('#frToast');const el=document.createElement('div');el.className='fr-toast '+type;const ic={ok:'fa-circle-check',bad:'fa-circle-exclamation',warn:'fa-triangle-exclamation'}[type]||'fa-circle-info';el.innerHTML='<i class="fa-solid '+ic+'"></i><span>'+frEsc(msg)+'</span>';box.appendChild(el);setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),420)},2400)}
function frToastUp(msg){const box=frQ('#frToast');const el=document.createElement('div');el.className='fr-toast';el.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i><span>'+frEsc(msg)+' 0%</span>';box.appendChild(el);return{set:p=>{el.querySelector('span').textContent=msg+' '+p+'%'},off:()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),420)}}}
function frUpXhr(file,kind){return new Promise((res,rej)=>{const x=new XMLHttpRequest();const fd=new FormData();fd.append('frFile',file);x.open('POST','?frApi=upload&kind='+encodeURIComponent(kind));x.upload.onprogress=e=>{if(e.lengthComputable&&frUpTip)frUpTip.set(Math.round(e.loaded/e.total*100))};x.onload=()=>{try{const j=JSON.parse(x.responseText);if(j.code===0)res(j);else rej(j)}catch(e){rej({msg:'上传失败'})}};x.onerror=()=>rej({msg:'网络异常'});x.send(fd)})}
let frUpTip=null;
function frModal(html,o={}){const ov=document.createElement('div');ov.className='fr-ov';ov.innerHTML='<div class="fr-mcard '+(o.cls||'')+'" role="dialog" aria-modal="true">'+((o.title||o.x===undefined)?'<div class="fr-m-h"><div class="fr-m-t">'+(o.title||'')+'</div>'+(o.x!==false?'<button class="fr-m-x" aria-label="关闭"><i class="fa-solid fa-xmark"></i></button>':'')+'</div>':'')+'<div class="fr-m-b">'+html+'</div></div>';document.body.appendChild(ov);requestAnimationFrame(()=>requestAnimationFrame(()=>ov.classList.add('on')));document.body.style.overflow='hidden';const close=()=>{ov.classList.remove('on');setTimeout(()=>{ov.remove();const i=frOvs.indexOf(close);if(i>-1)frOvs.splice(i,1);if(!frOvs.length)document.body.style.overflow='';if(o.onClose)o.onClose()},260)};frOvs.push(close);const xb=ov.querySelector('.fr-m-x');if(xb)xb.onclick=close;ov.addEventListener('click',e=>{if(e.target===ov)close()});return{ov,close,body:ov.querySelector('.fr-m-b')}}
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&frOvs.length)frOvs[frOvs.length-1]()});
function frConfirm(msg,cb,title='操作确认'){const m=frModal('<p style="margin:0 0 20px;color:var(--fr-tx2)">'+msg+'</p><div style="display:flex;gap:10px;justify-content:flex-end"><button class="fr-btn fr-btn-g" data-c>取消</button><button class="fr-btn fr-btn-p" data-o>确认</button></div>',{title});m.body.querySelector('[data-c]').onclick=m.close;m.body.querySelector('[data-o]').onclick=()=>{m.close();cb()}}
const frIcon=(a,s=48,fs=20)=>a.iconImg?'<span class="fr-ic" style="width:'+s+'px;height:'+s+'px"><img src="'+frEsc(a.iconImg)+'" alt=""></span>':'<span class="fr-ic" style="--h1:'+a.hue+';--h2:'+a.hue2+';width:'+s+'px;height:'+s+'px;font-size:'+fs+'px" aria-hidden="true"><i class="fa-solid '+frEsc(a.icon)+'"></i></span>';
const frStars=r=>{r=+r||0;let h='';for(let i=1;i<=5;i++)h+=r>=i?'<i class="fa-solid fa-star"></i>':r>=i-.5?'<i class="fa-solid fa-star-half-stroke"></i>':'<i class="fa-regular fa-star"></i>';return h};
const frAva=(name,hue,s=36,ava='')=>ava?'<span class="fr-ava" style="width:'+s+'px;height:'+s+'px"><img src="'+frEsc(ava)+'" alt=""></span>':'<span class="fr-ava" style="--h:'+(hue??220)+';width:'+s+'px;height:'+s+'px;font-size:'+Math.round(s*.42)+'px">'+frEsc(String(name).slice(0,1))+'</span>';
const frShot=(a,k)=>{const h1=(+a.hue+k*23)%360,h2=(+a.hue2+k*31)%360;return '<div class="fr-shot" style="--h1:'+h1+';--h2:'+h2+'" role="img" aria-label="软件截图 '+(k+1)+'"><div class="fr-shot-ui"><div class="fr-shot-top"><i></i><i></i><i></i></div><div class="fr-shot-l m"></div><div class="fr-shot-l s"></div><div class="fr-shot-grid"><i></i><i></i><i></i><i></i><i></i><i></i></div></div></div>'};
function frAppCard(a,badge=''){return '<a class="fr-card fr-app-card fr-rv" href="#/app/'+a.id+'" aria-label="'+frEsc(a.name)+'">'+badge+'<div class="fr-app-top">'+frIcon(a,48,19)+'<div style="min-width:0"><div class="fr-app-name">'+frEsc(a.name)+'</div><div class="fr-app-sub">'+frEsc(a.dev)+' · v'+frEsc(a.ver)+'</div></div></div><div class="fr-app-desc">'+frEsc(a.short)+'</div><div class="fr-app-foot"><span class="fr-stars">'+frStars(a.rating)+'<b>'+(+a.rating).toFixed(1)+'</b></span><span class="fr-dl" style="margin-left:auto"><i class="fa-solid fa-download"></i>'+frNum(a.downloads)+'</span></div></a>'}
const frSkCard='<div class="fr-card" style="padding:18px"><div style="display:flex;gap:12px;align-items:center"><div class="fr-sk" style="width:48px;height:48px;border-radius:14px"></div><div style="flex:1"><div class="fr-sk" style="height:13px"></div><div class="fr-sk" style="height:10px;margin-top:8px;width:60%"></div></div></div><div class="fr-sk" style="height:10px;margin-top:14px"></div><div class="fr-sk" style="height:10px;margin-top:8px;width:75%"></div><div class="fr-sk" style="height:10px;margin-top:8px;width:45%"></div></div>';
const frEmpty=(ic,t,s)=>'<div class="fr-card fr-rv fr-in" style="padding:52px 20px;text-align:center"><div style="width:64px;height:64px;margin:0 auto 14px;border-radius:20px;background:rgba(91,108,255,.1);display:grid;place-items:center;color:var(--fr-p1);font-size:22px"><i class="fa-solid '+ic+'"></i></div><b>'+t+'</b><p style="color:var(--fr-tx3);font-size:12px;margin:6px 0 0">'+s+'</p></div>';
function frReveal(){const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('fr-in');io.unobserve(e.target)}}),{threshold:.06});frA('.fr-rv:not(.fr-in)').forEach(el=>io.observe(el))}
function frNavUser(){const box=frQ('#frUserBox');if(frS.user){box.innerHTML='<button style="--h:'+frS.user.hue+'" id="frAvaBtn" aria-label="用户中心">'+frAva(frS.user.name,frS.user.hue,36,frS.user.ava||'')+'</button>';frQ('#frAvaBtn').onclick=()=>location.hash='#/mine'}else{box.innerHTML='<button class="fr-btn fr-btn-p" id="frLoginB" style="padding:9px 18px"><i class="fa-regular fa-user"></i> 登录</button>';frQ('#frLoginB').onclick=()=>frLoginModal()}}
function frFooter(){frQ('#frFoot').innerHTML='<div class="fr-wrap"><div style="display:flex;gap:26px;flex-wrap:wrap;justify-content:space-between;align-items:flex-start"><div style="max-width:430px"><div class="fr-logo" style="margin-bottom:10px"><span class="fr-logo-ic"><i class="fa-solid fa-cubes"></i></span>'+frEsc(frS.site.name)+'</div><p style="margin:0">'+frEsc(frS.site.footer||'')+'</p></div><div style="display:flex;gap:36px;flex-wrap:wrap"><div><div class="fr-f-t">快速导航</div><a class="fr-f-l" href="#/">首页</a><a class="fr-f-l" href="#/list">软件库</a><a class="fr-f-l" href="#/submit">软件投稿</a><a class="fr-f-l" href="#/mine">个人中心</a></div><div><div class="fr-f-t">支持作者</div><button class="fr-f-l" id="frFootReward"><i class="fa-solid fa-mug-hot"></i> 打赏曾先生</button><span class="fr-f-l"><i class="fa-regular fa-envelope"></i> '+frEsc(frS.site.contact||'')+'</span></div></div></div><div style="border-top:1px solid var(--fr-bd);margin-top:26px;padding-top:16px;display:flex;gap:8px;flex-wrap:wrap;justify-content:space-between"><span>© 2026 '+frEsc(frS.site.name)+' · 开发者 曾先生</span><span>'+frEsc(frS.site.icp||'')+'</span></div></div>';frQ('#frFootReward').onclick=frReward}
async function frBoot(){if(frBooted)return;const j=await frApi('boot');Object.assign(frS,{site:j.site,user:j.user,cats:j.cats,cars:j.cars,notices:j.notices,hot:j.hot,nw:j.new,rank:j.rank,total:j.total});frS.catMap={};frS.cats.forEach(c=>frS.catMap[c.id]=c.name);frBooted=true;frNavUser();frFooter();frQ('#frSiteName').textContent=frS.site.name;document.title=frS.site.name+' · '+frS.site.slogan}
function frCarHtml(){if(!frS.cars.length)return '';return '<div class="fr-car-track" id="frCarTrack">'+frS.cars.map(c=>'<a class="fr-car-it" style="--h1:'+c.hue+';--h2:'+((+c.hue+40)%360)+';'+(c.img?'background-image:linear-gradient(rgba(12,14,24,.30),rgba(12,14,24,.45)),url('+frEsc(c.img)+')':'')+'" href="'+frEsc(c.link||'#/')+'" aria-label="'+frEsc(c.title)+'">'+(c.img?'':'<span class="fr-car-grad"></span>')+'<div class="fr-car-txt">'+(c.badge?'<span class="fr-car-badge">'+frEsc(c.badge)+'</span>':'')+'<h3 class="fr-car-t1">'+frEsc(c.title)+'</h3><p class="fr-car-t2">'+frEsc(c.sub)+'</p></div></a>').join('')+'</div><button class="fr-car-btn prev" aria-label="上一张"><i class="fa-solid fa-angle-left"></i></button><button class="fr-car-btn next" aria-label="下一张"><i class="fa-solid fa-angle-right"></i></button><div class="fr-car-dots">'+frS.cars.map((c,i)=>'<b data-i="'+i+'" class="'+(i===0?'on':'')+'" aria-label="第'+(i+1)+'张"></b>').join('')+'</div>'}
function frCarInit(){const el=frQ('#frCar');if(!el)return;const n=frS.cars.length,track=frQ('#frCarTrack'),dots=frA('#frCar .fr-car-dots b');const go=k=>{frS.carI=(k+n)%n;track.style.transform='translateX(-'+frS.carI*100+'%)';dots.forEach((d,i)=>d.classList.toggle('on',i===frS.carI))};el.querySelector('.prev').onclick=e=>{e.preventDefault();go(frS.carI-1)};el.querySelector('.next').onclick=e=>{e.preventDefault();go(frS.carI+1)};dots.forEach(d=>d.onclick=()=>go(+d.dataset.i));let sx=0,dx=0;el.addEventListener('pointerdown',e=>{sx=e.clientX;dx=0});el.addEventListener('pointermove',e=>{if(sx)dx=e.clientX-sx});const end=()=>{if(sx&&Math.abs(dx)>46)go(frS.carI+(dx<0?1:-1));sx=0;dx=0};el.addEventListener('pointerup',end);el.addEventListener('pointercancel',end);const auto=()=>frS.carT=setInterval(()=>go(frS.carI+1),4600);auto();el.addEventListener('mouseenter',()=>clearInterval(frS.carT));el.addEventListener('mouseleave',auto)}
function frNoticeModal(n){if(!n)return;frModal('<p style="color:var(--fr-tx2);margin:0 0 10px">'+frEsc(n.content)+'</p><div style="color:var(--fr-tx3);font-size:12px">'+(n.at||'')+'</div>',{title:n.title})}
function frNInit(){const items=frA('#frNView .fr-n-item');const box=frQ('#frNotice');if(!box)return;let i=0;box.onclick=()=>frNoticeModal(frS.notices[i]||frS.notices[0]);box.onkeydown=e=>{if(e.key==='Enter')box.click()};if(items.length<2)return;frS.nT=setInterval(()=>{items[i].classList.remove('on');items[i].classList.add('up');const prev=i;i=(i+1)%items.length;setTimeout(()=>items[prev].classList.remove('up'),500);items[i].classList.add('on')},3200)}
function frRankRow(a,i){const t=frS.rankTab;const val=t==='downloads'?frNum(a.downloads)+' 次下载':t==='favs'?frNum(a.favs)+' 人收藏':(+a.rating).toFixed(1)+' 分';return '<a class="fr-rank-row" href="#/app/'+a.id+'"><span class="fr-rank-n '+(i<3?'r'+(i+1):'')+'">'+(i+1)+'</span>'+frIcon(a,40,16)+'<div style="min-width:0;flex:1"><div style="font-weight:600">'+frEsc(a.name)+'</div><div style="color:var(--fr-tx3);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+frEsc(a.short)+'</div></div><span style="color:var(--fr-tx3);font-size:12px;flex:none">'+val+'</span><i class="fa-solid fa-angle-right" style="color:var(--fr-tx3)"></i></a>'}
function frRank(){const box=frQ('#frRankBox');if(!box)return;const draw=()=>{const arr=[...frS.rank].sort((a,b)=>frS.rankTab==='rating'?(+b.rating)-(+a.rating):(+b[frS.rankTab])-(+a[frS.rankTab]));box.innerHTML=arr.slice(0,8).map((a,i)=>frRankRow(a,i)).join('')};draw();frA('#frRankSeg button').forEach(b=>b.onclick=()=>{frS.rankTab=b.dataset.k;frA('#frRankSeg button').forEach(x=>x.classList.toggle('on',x===b));draw()})}
async function frHomePage(){const main=frQ('#frMain');
main.innerHTML='<div class="fr-wrap">'
+'<section class="fr-car fr-rv fr-in" id="frCar" aria-roledescription="轮播" aria-label="精选推荐">'+frCarHtml()+'</section>'
+(frS.notices.length?'<div class="fr-card fr-notice fr-rv" id="frNotice" role="button" tabindex="0" aria-label="站点公告"><i class="fa-solid fa-bullhorn fr-n-ic"></i><div class="fr-n-view" id="frNView">'+frS.notices.map((n,i)=>'<div class="fr-n-item'+(i===0?' on':'')+'"><b>['+frEsc(n.title)+']</b><span>'+frEsc(n.content)+'</span></div>').join('')+'</div><i class="fa-solid fa-angle-right" style="color:var(--fr-tx3)"></i></div>':'')
+'<section class="fr-search-sec fr-rv"><div class="fr-bigsearch"><i class="fa-solid fa-magnifying-glass"></i><input id="frHomeQ" type="search" placeholder="搜索 '+frS.total+' 款优质软件，试试关键词" aria-label="搜索软件"><button class="fr-btn fr-btn-p" id="frHomeGo">搜索</button></div><div class="fr-cats" style="margin-top:14px"><button class="fr-cat-b on" data-cat="0"><i class="fa-solid fa-border-all"></i>全部</button>'+frS.cats.map(c=>'<button class="fr-cat-b" data-cat="'+c.id+'"><i class="fa-solid '+frEsc(c.icon)+'"></i>'+frEsc(c.name)+'</button>').join('')+'</div></section>'
+'<section class="fr-sec"><div class="fr-sec-h"><h2 class="fr-sec-t">热门下载</h2><a class="fr-more" href="#/list/sort-hot">更多 <i class="fa-solid fa-angle-right"></i></a></div><div class="fr-grid">'+frS.hot.map((a,i)=>frAppCard(a,'<span class="fr-rank-b '+(i<3?'r'+(i+1):'')+'">TOP '+(i+1)+'</span>')).join('')+'</div></section>'
+'<section class="fr-sec"><div class="fr-sec-h"><h2 class="fr-sec-t">最新更新</h2><a class="fr-more" href="#/list/sort-new">更多 <i class="fa-solid fa-angle-right"></i></a></div><div class="fr-grid">'+frS.nw.map(a=>frAppCard(a)).join('')+'</div></section>'
+'<section class="fr-sec"><div class="fr-sec-h"><h2 class="fr-sec-t">排行榜</h2><div class="fr-seg" id="frRankSeg"><button data-k="downloads" class="on">下载榜</button><button data-k="favs">收藏榜</button><button data-k="rating">好评榜</button></div></div><div class="fr-card" style="padding:8px" id="frRankBox"></div></section>'
+(frS.site.enableSubmit?'<section class="fr-cta fr-card fr-rv"><div><h3 style="margin:0 0 6px">发现了好软件？分享给大家</h3><p style="margin:0;color:var(--fr-tx2);font-size:var(--fr-fz5)">投稿通过审核后可获得会员积分，并入选首页精选</p></div><a class="fr-btn fr-btn-p" href="#/submit"><i class="fa-solid fa-paper-plane"></i> 立即投稿</a></section>':'')
+'</div>';
frCarInit();frNInit();frRank();
const hq=frQ('#frHomeQ');const goList=()=>{const v=hq.value.trim();location.hash=v?'#/list/q-'+encodeURIComponent(v):'#/list'};
frQ('#frHomeGo').onclick=goList;hq.onkeydown=e=>{if(e.key==='Enter')goList()};
frA('.fr-cat-b').forEach(b=>b.onclick=()=>{const c=+b.dataset.cat;location.hash=c?'#/list/cat-'+c:'#/list'})}
function frListParse(p){const st={q:'',cat:0,sort:'hot',page:1};String(p||'').split('_').forEach(s=>{if(s.startsWith('cat-'))st.cat=+s.slice(4);else if(s.startsWith('sort-'))st.sort=s.slice(5);else if(s.startsWith('q-'))st.q=decodeURIComponent(s.slice(2));else if(s.startsWith('p-'))st.page=Math.max(1,+s.slice(2))});return st}
function frListHash(st){const p=[];if(st.cat)p.push('cat-'+st.cat);if(st.sort&&st.sort!=='hot')p.push('sort-'+st.sort);if(st.q)p.push('q-'+encodeURIComponent(st.q));if(st.page>1)p.push('p-'+st.page);return '#/list'+(p.length?'_'+p.join('_'):'')}
function frListMoreRender(j){const box=frQ('#frListMore');if(!box)return;const st=frS.list;if(j.page<j.pages)box.innerHTML='<button class="fr-btn fr-btn-g" id="frListMoreBtn"><i class="fa-solid fa-angles-down"></i> 加载更多（还有 '+Math.max(0,j.total-j.page*12)+' 款）</button>';else box.innerHTML='<p style="color:var(--fr-tx3);font-size:12px">已加载全部 '+j.total+' 款软件</p>';const b=frQ('#frListMoreBtn');if(b)b.onclick=frListMoreFn}
async function frListMoreFn(){const st=frS.list;if(st.page>=st.pages)return;st.page++;const b=frQ('#frListMoreBtn');if(b){b.disabled=true;b.innerHTML='加载中...'}const j=await frApi('apps',{q:st.q,cat:st.cat,sort:st.sort,page:st.page});frQ('#frListBox').insertAdjacentHTML('beforeend',j.items.map(a=>frAppCard(a)).join(''));frS.list.pages=j.pages;history.replaceState(null,'',frListHash(st));frListMoreRender(j);frReveal()}
async function frListPage(){const st=frListParse(frS.route.p);frS.list=st;const main=frQ('#frMain');
main.innerHTML='<div class="fr-wrap"><div class="fr-page-h fr-rv fr-in"><h1 class="fr-page-t"><i class="fa-solid fa-layer-group" style="color:var(--fr-p1)"></i> 软件库</h1><p class="fr-page-s">共 '+frS.total+' 款精选应用 · 每周持续更新</p></div>'
+'<div class="fr-filter"><div class="fr-cats" style="flex:1;min-width:0"><button class="fr-cat-b '+(!st.cat?'on':'')+'" data-cat="0"><i class="fa-solid fa-border-all"></i>全部</button>'+frS.cats.map(c=>'<button class="fr-cat-b '+(st.cat===c.id?'on':'')+'" data-cat="'+c.id+'"><i class="fa-solid '+frEsc(c.icon)+'"></i>'+frEsc(c.name)+'</button>').join('')+'</div><div class="fr-seg" id="frSortSeg">'+[['hot','热门'],['new','最新'],['rating','评分']].map(s=>'<button data-s="'+s[0]+'" class="'+(st.sort===s[0]?'on':'')+'">'+s[1]+'</button>').join('')+'</div></div>'
+'<div class="fr-glass" style="border-radius:var(--fr-rs);padding:10px 16px;display:flex;align-items:center;gap:10px;margin-bottom:18px;border:1px solid var(--fr-bd)"><i class="fa-solid fa-magnifying-glass" style="color:var(--fr-tx3)"></i><input id="frListQ" style="flex:1;background:none;border:none;outline:none;min-width:0" placeholder="搜索软件名称、标签或开发者" value="'+frEsc(st.q)+'" aria-label="搜索"></div>'
+'<div id="frListBox" class="fr-grid">'+frSkCard.repeat(8)+'</div>'
+'<div style="text-align:center;margin-top:22px" id="frListMore"></div></div>';
frA('.fr-cat-b').forEach(b=>b.onclick=()=>{st.cat=+b.dataset.cat;st.page=1;history.replaceState(null,'',frListHash(st));frA('.fr-cat-b').forEach(x=>x.classList.toggle('on',x===b));frListLoad()});
frA('#frSortSeg button').forEach(b=>b.onclick=()=>{st.sort=b.dataset.s;st.page=1;history.replaceState(null,'',frListHash(st));frA('#frSortSeg button').forEach(x=>x.classList.toggle('on',x===b));frListLoad()});
let frLt;const qi=frQ('#frListQ');qi.oninput=()=>{clearTimeout(frLt);frLt=setTimeout(()=>{st.q=qi.value.trim();st.page=1;history.replaceState(null,'',frListHash(st));frListLoad()},420)};qi.onkeydown=e=>{if(e.key==='Enter'){clearTimeout(frLt);st.q=qi.value.trim();st.page=1;frListLoad()}};
await frListLoad()}
async function frListLoad(){const st=frS.list;const box=frQ('#frListBox');if(!box)return;box.innerHTML=frSkCard.repeat(8);const j=await frApi('apps',{q:st.q,cat:st.cat,sort:st.sort,page:st.page});frS.list.pages=j.pages;box.innerHTML=j.items.length?j.items.map(a=>frAppCard(a)).join(''):'<div style="grid-column:1/-1">'+frEmpty('fa-box-open','没有找到相关软件','换个关键词或分类试试')+'</div>';frListMoreRender(j);frReveal()}
const frRich=t=>frEsc(t).split(/\n+/).filter(Boolean).map(x=>/^[-·]/.test(x.trim())?'<p class="fr-li"><i class="fa-solid fa-circle-check"></i><span>'+x.trim().replace(/^[-·]\s*/,'')+'</span></p>':'<p>'+x+'</p>').join('');
const frCmtItem=c=>'<div class="fr-cmt">'+frAva(c.user,c.hue??220,40,c.ava||'')+'<div class="fr-cmt-b"><div class="fr-cmt-h"><span class="fr-cmt-n">'+frEsc(c.user)+'</span><span class="fr-stars">'+frStars(c.stars)+'</span>'+(c.status===0?'<span class="fr-badge fr-badge-w">审核中</span>':'')+'<span class="fr-cmt-t">'+frDate(c.at)+'</span></div><div class="fr-cmt-x">'+frEsc(c.text)+'</div></div></div>';
function frCmtsHtml(j){return '<div class="fr-cmt-post" style="flex-wrap:wrap">'+(frS.user?frAva(frS.user.name,frS.user.hue,40,frS.user.ava||'')+'<div style="flex:1;min-width:220px"><div class="fr-star-in" id="frStarIn" role="radiogroup" aria-label="评分">'+[1,2,3,4,5].map(i=>'<button data-v="'+i+'" class="on" aria-label="'+i+'星"><i class="fa-solid fa-star"></i></button>').join('')+'</div><textarea id="frCmtTx" class="fr-inp" rows="2" maxlength="200" placeholder="说点什么吧，友善的评论更容易通过审核" aria-label="评论内容"></textarea><div style="display:flex;justify-content:flex-end;margin-top:8px"><button class="fr-btn fr-btn-p" id="frCmtGo" style="padding:8px 20px">发表评论</button></div></div>':'<div class="fr-login-cta" style="width:100%"><span style="color:var(--fr-tx2)">登录后即可参与讨论</span><button class="fr-btn fr-btn-s" id="frCmtLogin">立即登录</button></div>')+'</div><div id="frCmtPend">'+(j.pending||[]).map(frCmtItem).join('')+'</div><div id="frCmtList">'+(j.comments.length?j.comments.map(frCmtItem).join(''):frEmpty('fa-comments','暂无评论','来抢沙发，发表第一条评论'))+'</div>'}
function frBindCmt(){const si=frQ('#frStarIn');let stars=5;if(si)si.querySelectorAll('button').forEach(b=>b.onclick=()=>{stars=+b.dataset.v;si.querySelectorAll('button').forEach(x=>{const v=+x.dataset.v;x.classList.toggle('on',v<=stars);x.innerHTML='<i class="fa-'+(v<=stars?'solid':'regular')+' fa-star"></i>'})});
const go=frQ('#frCmtGo');if(go)go.onclick=async()=>{const tx=frQ('#frCmtTx').value.trim();if(tx.length<2)return frToast('评论内容太短啦','warn');go.disabled=true;try{const j=await frApi('comment',{appId:frS.appCur.id,text:tx,stars});frToast('评论已提交，通过审核后展示');frQ('#frCmtTx').value='';frQ('#frCmtPend').insertAdjacentHTML('beforeend',frCmtItem(j.comment))}catch(e){}go.disabled=false};
const lg=frQ('#frCmtLogin');if(lg)lg.onclick=()=>frLoginModal(()=>frAppPage(frS.appCur.id))}
async function frAppPage(id){const main=frQ('#frMain');frS.appCur=null;main.innerHTML='<div class="fr-wrap"><div class="fr-grid">'+frSkCard.repeat(6)+'</div></div>';let j;try{j=await frApi('app',{id})}catch(e){main.innerHTML='<div class="fr-wrap" style="padding-top:60px">'+frEmpty('fa-circle-exclamation','软件不存在或已下架','去首页逛逛')+'</div>';return}
const a=j.app;frS.appCur={...a,faved:j.faved};
const shots=(a.shotsImg&&a.shotsImg.length)?a.shotsImg.map(u=>'<img class="fr-shot-img" src="'+frEsc(u)+'" alt="软件截图" loading="lazy">').join(''):[0,1,2,3,4].map(k=>frShot(a,k)).join('');
main.innerHTML='<div class="fr-wrap">'
+'<div class="fr-crumb fr-rv fr-in"><a href="#/">首页</a><i class="fa-solid fa-angle-right"></i><a href="#/list/cat-'+a.catId+'">'+frEsc(a.cat)+'</a><i class="fa-solid fa-angle-right"></i><span>'+frEsc(a.name)+'</span></div>'
+'<section class="fr-card fr-hero fr-rv fr-in">'+frIcon(a,86,34)+'<div class="fr-hero-meta"><h1>'+frEsc(a.name)+(a.top?' <span class="fr-badge fr-badge-b">置顶</span>':'')+'</h1><div class="fr-hero-sub">'+frEsc(a.dev)+' · v'+frEsc(a.ver)+' · '+frEsc(a.size)+' · '+frEsc(a.cat)+'</div><div class="fr-stats"><span class="fr-stars" style="font-size:14px">'+frStars(a.rating)+'<b>'+(+a.rating).toFixed(1)+' 分</b></span><span class="fr-dl"><i class="fa-regular fa-eye"></i>'+frNum(a.views||0)+' 浏览</span><span class="fr-dl"><i class="fa-solid fa-download"></i><b id="frDlCnt">'+frNum(a.downloads)+'</b>&nbsp;下载</span><span class="fr-dl"><i class="fa-regular fa-heart"></i>'+frNum(a.favs)+' 收藏</span></div><div class="fr-acts"><button class="fr-btn fr-btn-p" id="frDlBtn"><i class="fa-solid fa-download"></i> 立即下载</button><button class="fr-btn '+(j.faved?'fr-btn-s':'fr-btn-g')+'" id="frFavBtn"><i class="fa-'+(j.faved?'solid':'regular')+' fa-heart"></i> '+(j.faved?'已收藏':'收藏')+'</button><button class="fr-btn fr-btn-g" id="frShareBtn"><i class="fa-solid fa-share-nodes"></i> 分享</button><button class="fr-btn fr-btn-g" id="frRepBtn" aria-label="举报"><i class="fa-regular fa-flag"></i></button></div></div></section>'
+'<section class="fr-sec"><div class="fr-sec-h"><h2 class="fr-sec-t">软件截图</h2></div><div class="fr-shots">'+shots+'</div></section>'
+'<div class="fr-cols"><div><section class="fr-card fr-rv" style="padding:22px"><h2 class="fr-sec-t" style="margin-bottom:14px">软件介绍</h2><div class="fr-rich">'+frRich(a.desc)+'</div><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">'+a.tags.map(t=>'<span class="fr-chip"><i class="fa-solid fa-hashtag" style="font-size:10px"></i>'+frEsc(t)+'</span>').join('')+'</div></section>'
+'<section class="fr-card fr-rv" style="padding:22px;margin-top:16px"><div class="fr-sec-h"><h2 class="fr-sec-t">用户评论</h2><span class="fr-chip"><i class="fa-regular fa-comments"></i> '+j.comments.length+' 条</span></div>'+frCmtsHtml(j)+'</section></div>'
+'<aside><section class="fr-card fr-rv" style="padding:20px"><h3 class="fr-side-t">版本信息</h3><table class="fr-info-t"><tr><td>当前版本</td><td>v'+frEsc(a.ver)+'</td></tr><tr><td>文件大小</td><td>'+frEsc(a.size)+'</td></tr><tr><td>所属分类</td><td>'+frEsc(a.cat)+'</td></tr><tr><td>更新时间</td><td>'+frEsc(a.updated)+'</td></tr><tr><td>首发时间</td><td>'+frEsc(a.createdAt)+'</td></tr><tr><td>软件授权</td><td>免费软件</td></tr></table></section>'
+'<section class="fr-card fr-rv" style="padding:20px"><h3 class="fr-side-t">下载渠道</h3>'+a.links.map((l,i)=>'<div class="fr-mirror" data-l="'+i+'"><i class="fa-solid '+['fa-server','fa-network-wired','fa-database'][i%3]+'" style="color:var(--fr-p1)"></i><div style="flex:1;min-width:0"><div style="font-weight:600;font-size:13px">'+frEsc(l.label)+'</div><div style="color:var(--fr-tx3);font-size:11px">官方认证 · 安全无毒</div></div><i class="fa-solid fa-angle-right" style="color:var(--fr-tx3)"></i></div>').join('')+'</section>'
+(j.related.length?'<section class="fr-card fr-rv" style="padding:20px"><h3 class="fr-side-t">相关推荐</h3>'+j.related.map(r=>'<a class="fr-rank-row" style="padding:9px 6px" href="#/app/'+r.id+'">'+frIcon(r,38,15)+'<div style="min-width:0;flex:1"><div style="font-weight:600;font-size:13px">'+frEsc(r.name)+'</div><div style="color:var(--fr-tx3);font-size:11px"><i class="fa-solid fa-download"></i> '+frNum(r.downloads)+'</div></div></a>').join('')+'</section>':'')
+'<section class="fr-card fr-rv" style="padding:20px;text-align:center"><h3 class="fr-side-t">支持开发者</h3><p style="color:var(--fr-tx2);font-size:12px;margin:6px 0 12px">你的支持是持续更新的动力</p><button class="fr-btn fr-btn-s" id="frSideReward"><i class="fa-solid fa-mug-hot"></i> 打赏曾先生</button></section></aside></div></div>';
frQ('#frDlBtn').onclick=()=>frDownloadModal(0);
frA('.fr-mirror').forEach(el=>el.onclick=()=>frDownloadModal(+el.dataset.l));
frQ('#frFavBtn').onclick=frFavToggle;
frQ('#frShareBtn').onclick=frShareModal;
frQ('#frRepBtn').onclick=frReportModal;
frQ('#frSideReward').onclick=frReward;
frBindCmt();frReveal()}
async function frFavToggle(){const a=frS.appCur;if(!a)return;if(!frS.user)return frLoginModal(()=>frFavToggle());try{const j=await frApi('fav',{id:a.id});a.faved=j.faved;a.favs=j.favs;const b=frQ('#frFavBtn');b.className='fr-btn '+(j.faved?'fr-btn-s':'fr-btn-g');b.innerHTML='<i class="fa-'+(j.faved?'solid':'regular')+' fa-heart"></i> '+(j.faved?'已收藏':'收藏');frToast(j.faved?'已加入收藏':'已取消收藏')}catch(e){}}
function frDownloadModal(li=0){const a=frS.appCur;if(!a)return;const m=frModal('<p style="margin:0 0 12px;color:var(--fr-tx2);font-size:13px"><b style="color:var(--fr-tx)">'+frEsc(a.name)+'</b> v'+frEsc(a.ver)+' · '+frEsc(a.size)+'</p><div id="frMirrors">'+a.links.map((l,i)=>'<div class="fr-mirror '+(i===li?'on':'')+'" data-i="'+i+'"><span class="fr-radio"></span><i class="fa-solid '+['fa-server','fa-network-wired','fa-database'][i%3]+'" style="color:var(--fr-p1)"></i><div style="flex:1;min-width:0"><div style="font-weight:600;font-size:13px">'+frEsc(l.label)+'</div><div style="color:var(--fr-tx3);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+frEsc(l.url)+'</div></div><span class="fr-badge fr-badge-g">可用</span></div>').join('')+'</div><div id="frDlStage"></div><button class="fr-btn fr-btn-p" id="frDlGo" style="width:100%"><i class="fa-solid fa-bolt"></i> 开始安全下载</button><p style="color:var(--fr-tx3);font-size:11px;margin:12px 0 0;text-align:center"><i class="fa-solid fa-shield-halved"></i> 该下载通道已通过安全检测</p>',{title:'下载软件'});
let sel=li;m.body.querySelectorAll('.fr-mirror').forEach(el=>el.onclick=()=>{m.body.querySelectorAll('.fr-mirror').forEach(x=>x.classList.remove('on'));el.classList.add('on');sel=+el.dataset.i});
m.body.querySelector('#frDlGo').onclick=()=>frDlRun(m,a,sel)}
function frDlRun(m,a,li){const stage=m.body.querySelector('#frDlStage'),btn=m.body.querySelector('#frDlGo');btn.disabled=true;btn.innerHTML='正在准备资源...';let c=3;stage.innerHTML='<div class="fr-dl-stage"><div class="fr-dl-ring"><b class="fr-dl-num">'+c+'</b></div><div style="color:var(--fr-tx2);font-size:13px">正在匹配最优下载节点...</div></div>';const num=stage.querySelector('.fr-dl-num');const tk=setInterval(()=>{c--;if(c>0){num.textContent=c;num.style.animation='none';void num.offsetWidth;num.style.animation='frPop .45s'}else{clearInterval(tk);frDlProg(m,a,li)}},700)}
function frDlProg(m,a,li){const stage=m.body.querySelector('#frDlStage');stage.innerHTML='<div class="fr-dl-stage"><div style="display:flex;justify-content:space-between;font-size:12px;color:var(--fr-tx2);margin-bottom:6px"><span id="frDlPct">0%</span><span id="frDlSpd">-- MB/s</span></div><div class="fr-dl-bar"><i id="frDlBarI"></i></div><div style="color:var(--fr-tx3);font-size:11px" id="frDlTip">正在建立加密连接...</div></div>';let p=0;const bar=stage.querySelector('#frDlBarI'),pct=stage.querySelector('#frDlPct'),spd=stage.querySelector('#frDlSpd'),tip=stage.querySelector('#frDlTip');const tips=['正在建立加密连接...','正在校验文件签名...','高速下载中...','即将完成...'];const tk=setInterval(()=>{p=Math.min(100,p+Math.random()*7+2);bar.style.width=p+'%';pct.textContent=Math.floor(p)+'%';spd.textContent=(Math.random()*8+6).toFixed(1)+' MB/s';tip.textContent=tips[Math.min(3,Math.floor(p/28))];if(p>=100){clearInterval(tk);frDlDone(m,a,li)}},90)}
function frDlDone(m,a,li){const stage=m.body.querySelector('#frDlStage');stage.innerHTML='<div class="fr-dl-stage"><div style="width:64px;height:64px;margin:6px auto;border-radius:50%;background:rgba(61,187,126,.15);display:grid;place-items:center;color:var(--fr-ok);font-size:26px;animation:frPop .5s"><i class="fa-solid fa-check"></i></div><div style="font-weight:700;margin-top:10px">下载包已就绪，正在打开链接</div><div style="color:var(--fr-tx3);font-size:12px;margin-top:4px">若未自动打开，请点击下方按钮重试</div></div>';const btn=m.body.querySelector('#frDlGo');btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-arrow-up-right-from-square"></i> 重新打开下载链接';btn.onclick=()=>window.open(a.links[li].url,'_blank');window.open(a.links[li].url,'_blank');frApi('download',{id:a.id,link:li}).then(j=>{if(frS.appCur&&frS.appCur.id===a.id){frS.appCur.downloads=j.downloads;const c=frQ('#frDlCnt');if(c)c.textContent=frNum(j.downloads)}}).catch(()=>{})}
function frLoginModal(cb){const m=frModal('<div class="fr-seg" style="width:100%;display:flex;margin-bottom:18px" id="frLTab"><button class="on" data-t="login" style="flex:1">密码登录</button><button data-t="reg" style="flex:1">注册账号</button></div><form id="frLForm"><div class="fr-frow"><label class="fr-flabel" for="frLName">用户名 / 邮箱</label><input class="fr-inp" id="frLName" required autocomplete="username"></div><div class="fr-frow" id="frLEmailRow" style="display:none"><label class="fr-flabel" for="frLEmail">邮箱</label><input class="fr-inp" id="frLEmail" type="email" autocomplete="email"></div><div class="fr-frow"><label class="fr-flabel" for="frLPass">密码</label><input class="fr-inp" id="frLPass" type="password" required autocomplete="current-password"></div><button class="fr-btn fr-btn-p" style="width:100%" id="frLGo">登 录</button></form><p style="color:var(--fr-tx3);font-size:11px;text-align:center;margin:14px 0 0">登录即代表同意平台用户协议与隐私政策</p>',{title:'欢迎回来'});
let mode='login';const tab=m.body.querySelector('#frLTab');tab.querySelectorAll('button').forEach(b=>b.onclick=()=>{mode=b.dataset.t;tab.querySelectorAll('button').forEach(x=>x.classList.toggle('on',x===b));m.body.querySelector('#frLEmailRow').style.display=mode==='reg'?'':'none';m.body.querySelector('#frLGo').textContent=mode==='reg'?'注册并登录':'登 录'});
m.body.querySelector('#frLForm').onsubmit=async e=>{e.preventDefault();const btn=m.body.querySelector('#frLGo');btn.disabled=true;try{const j=mode==='login'?await frApi('login',{name:m.body.querySelector('#frLName').value.trim(),pass:m.body.querySelector('#frLPass').value}):await frApi('reg',{name:m.body.querySelector('#frLName').value.trim(),email:m.body.querySelector('#frLEmail').value.trim(),pass:m.body.querySelector('#frLPass').value});frS.user=j.user;frMineData=null;frNavUser();frToast(mode==='reg'?'注册成功，已赠送 '+j.user.points+' 新人积分':'欢迎回来，'+j.user.name);m.close();if(cb)cb()}catch(e){}btn.disabled=false}}
function frReward(){const rw=frS.site.rewardUrl||'fr.png';const m=frModal('<div style="text-align:center"><div class="fr-qr"><div class="fr-qr-p"></div><img src="'+frEsc(rw)+'" alt="打赏二维码" onerror="this.style.display=\'none\'"><span class="fr-qr-c"><i class="fa-solid fa-heart"></i></span></div><div style="font-weight:700;margin-top:14px">打赏开发者 曾先生</div><p style="color:var(--fr-tx2);font-size:12px;margin:6px 0 14px">你的每一份支持，都是开源与分享的动力</p><div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">'+['6.66','8.88','18.88','66.66'].map(v=>'<button class="fr-chip fr-amt">'+v+' 元</button>').join('')+'</div><p style="color:var(--fr-tx3);font-size:11px;margin-top:14px">'+(frS.site.rewardUrl?'已使用后台上传的收款码':'可在后台「网站配置」上传收款码，或替换根目录 fr.png')+'</p></div>',{title:'打赏作者'});m.body.querySelectorAll('.fr-amt').forEach(b=>b.onclick=()=>frToast('感谢支持（演示展示）'))}
function frShareModal(){const a=frS.appCur;if(!a)return;const url=location.href;const m=frModal('<div style="text-align:center"><div class="fr-qr" style="width:170px;height:170px"><div class="fr-qr-p"></div><span class="fr-qr-c"><i class="fa-solid fa-link"></i></span></div><p style="color:var(--fr-tx2);font-size:12px;margin:12px 0">复制链接，分享给好友</p><div style="display:flex;gap:8px"><input class="fr-inp" id="frShareUrl" readonly value="'+frEsc(url)+'" style="flex:1;font-size:12px"><button class="fr-btn fr-btn-p" id="frShareCopy" style="padding:10px 16px">复制</button></div><div style="display:flex;gap:14px;justify-content:center;margin-top:16px">'+[['fa-weibo','微博'],['fa-qq','QQ'],['fa-weixin','微信']].map(s=>'<button class="fr-share-ic" data-n="'+s[1]+'" style="display:flex;flex-direction:column;align-items:center;gap:6px;color:var(--fr-tx2);font-size:11px"><span style="width:42px;height:42px;border-radius:14px;background:rgba(91,108,255,.1);display:grid;place-items:center;color:var(--fr-p1);font-size:17px"><i class="fa-brands '+s[0]+'"></i></span>'+s[1]+'</button>').join('')+'</div></div>',{title:'分享软件'});
m.body.querySelector('#frShareCopy').onclick=async()=>{try{await navigator.clipboard.writeText(url)}catch(e){const inp=m.body.querySelector('#frShareUrl');inp.select();document.execCommand('copy')}frToast('链接已复制')};
m.body.querySelectorAll('.fr-share-ic').forEach(b=>b.onclick=()=>frToast('已复制链接，请粘贴到 '+b.dataset.n+' 分享'))}
function frReportModal(){const a=frS.appCur;if(!a)return;const m=frModal('<div class="fr-frow"><label class="fr-flabel">举报对象</label><input class="fr-inp" value="'+frEsc(a.name)+'" disabled></div><div class="fr-frow"><label class="fr-flabel" for="frRepWhy">举报原因 *</label><textarea class="fr-inp" id="frRepWhy" rows="3" maxlength="100" placeholder="如：携带病毒、侵权、无法下载等"></textarea></div><button class="fr-btn fr-btn-p" id="frRepGo" style="width:100%">提交举报</button>',{title:'举报软件'});
m.body.querySelector('#frRepGo').onclick=async()=>{const why=m.body.querySelector('#frRepWhy').value.trim();if(!why)return frToast('请填写举报原因','warn');try{await frApi('report',{appId:a.id,why});frToast('举报已提交，我们会尽快处理');m.close()}catch(e){}}}
function frMineTab(k){frA('#frMineTabs .fr-tab-i').forEach(b=>b.classList.toggle('on',b.dataset.k===k));const d=frMineData;const box=frQ('#frMineBox');if(!d||!box)return;
if(k==='fav')box.innerHTML=d.favs.length?'<div class="fr-grid">'+d.favs.map(a=>frAppCard(a)).join('')+'</div>':frEmpty('fa-heart','还没有收藏','去发现一些好软件吧');
if(k==='dl')box.innerHTML=d.dls.length?'<div class="fr-card" style="padding:6px 14px">'+d.dls.map(r=>'<div class="fr-rank-row"><i class="fa-solid fa-file-arrow-down" style="color:var(--fr-p1);font-size:18px"></i><div style="flex:1;min-width:0"><div style="font-weight:600">'+frEsc(r.name)+'</div><div style="color:var(--fr-tx3);font-size:12px">'+frDate(r.at)+'</div></div><a class="fr-btn fr-btn-s" style="padding:7px 14px" href="#/app/'+r.appId+'">查看</a></div>').join('')+'</div>':frEmpty('fa-download','暂无下载记录','去下载第一款软件吧');
if(k==='cmt')box.innerHTML=d.comments.length?'<div class="fr-card" style="padding:6px 14px">'+d.comments.map(c=>'<div class="fr-rank-row" style="align-items:flex-start"><div style="flex:1;min-width:0"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>'+(c.appName?frEsc(c.appName):'已删除软件')+'</b><span class="fr-stars">'+frStars(c.stars)+'</span><span class="fr-badge '+(c.status===1?'fr-badge-g':c.status===0?'fr-badge-w':'fr-badge-r')+'">'+(c.status===1?'已发布':c.status===0?'审核中':'未通过')+'</span></div><div style="color:var(--fr-tx2);font-size:13px;margin-top:4px">'+frEsc(c.text)+'</div><div style="color:var(--fr-tx3);font-size:11px;margin-top:3px">'+frDate(c.at)+'</div></div></div>').join('')+'</div>':frEmpty('fa-comments','还没有评论','去给喜欢的软件写点评价吧');
if(k==='sub')box.innerHTML=d.subs.length?'<div class="fr-card" style="padding:6px 14px">'+d.subs.map(s=>'<div class="fr-rank-row"><i class="fa-solid fa-paper-plane" style="color:var(--fr-p2);font-size:16px"></i><div style="flex:1;min-width:0"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>'+frEsc(s.name)+'</b><span class="fr-badge '+(s.status===1?'fr-badge-g':s.status===0?'fr-badge-w':'fr-badge-r')+'">'+(s.status===1?'已收录':s.status===0?'审核中':'已驳回')+'</span></div><div style="color:var(--fr-tx3);font-size:12px;margin-top:3px">v'+frEsc(s.ver)+' · '+frDate(s.at)+'</div></div></div>').join('')+'</div>':frEmpty('fa-paper-plane','还没有投稿','把好用的软件分享给大家');
frReveal()}
async function frMinePage(){const main=frQ('#frMain');
if(!frS.user){main.innerHTML='<div class="fr-wrap"><div class="fr-card fr-rv fr-in" style="padding:60px 24px;text-align:center;max-width:420px;margin:60px auto"><div style="width:74px;height:74px;margin:0 auto 16px;border-radius:24px;background:rgba(91,108,255,.12);display:grid;place-items:center;color:var(--fr-p1);font-size:28px"><i class="fa-regular fa-user"></i></div><h2 style="margin:0 0 8px">你还没有登录</h2><p style="color:var(--fr-tx2);margin:0 0 20px;font-size:13px">登录后可使用收藏、评论、下载记录与签到积分</p><button class="fr-btn fr-btn-p" id="frMineLogin">登录 / 注册</button></div></div>';frQ('#frMineLogin').onclick=()=>frLoginModal(frMinePage);return}
main.innerHTML='<div class="fr-wrap"><section class="fr-card fr-prof fr-rv fr-in">'+frAva(frS.user.name,frS.user.hue,64,frS.user.ava||'')+'<div style="flex:1;min-width:180px"><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><b style="font-size:19px">'+frEsc(frS.user.name)+'</b><span class="fr-badge fr-badge-b"><i class="fa-solid fa-coins"></i> '+frS.user.points+' 积分</span></div><div style="color:var(--fr-tx3);font-size:12px;margin-top:4px">'+frEsc(frS.site.name)+' 会员 · 每日签到可得积分</div><div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap"><button class="fr-btn fr-btn-s" id="frSignBtn" style="padding:8px 16px" '+(frS.user.sign===frToday()?'disabled':'')+'><i class="fa-solid fa-calendar-check"></i> '+(frS.user.sign===frToday()?'今日已签到':'每日签到')+'</button><button class="fr-btn fr-btn-g" id="frAvaUp" style="padding:8px 16px"><i class="fa-solid fa-camera"></i> 更换头像</button><button class="fr-btn fr-btn-g" id="frOutBtn" style="padding:8px 16px"><i class="fa-solid fa-arrow-right-from-bracket"></i> 退出登录</button><input type="file" id="frAvaFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden></div></div></section><div class="fr-tabs" id="frMineTabs">'+[['fav','我的收藏','fa-heart'],['dl','下载记录','fa-download'],['cmt','我的评论','fa-comments'],['sub','我的投稿','fa-paper-plane']].map((s,i)=>'<button class="fr-tab-i '+(i===0?'on':'')+'" data-k="'+s[0]+'"><i class="fa-solid '+s[2]+'"></i> '+s[1]+'</button>').join('')+'</div><div id="frMineBox"><div class="fr-grid">'+frSkCard.repeat(4)+'</div></div></div>';
frA('#frMineTabs .fr-tab-i').forEach(b=>b.onclick=()=>frMineTab(b.dataset.k));
frQ('#frSignBtn').onclick=async()=>{try{const j=await frApi('signin');frS.user.points=j.points;frS.user.sign=frToday();frToast('签到成功，积分已到账');frMinePage()}catch(e){}};
frQ('#frAvaUp').onclick=()=>frQ('#frAvaFile').click();
frQ('#frAvaFile').onchange=async e=>{const f=e.target.files[0];e.target.value='';if(!f)return;frUpTip=frToastUp('正在上传头像');try{const j=await frUpXhr(f,'avatar');frUpTip.off();frS.user.ava=j.url;frNavUser();frMineData=null;frMinePage();frToast('头像已更新')}catch(err){frUpTip.off();frToast(err.msg||'上传失败','bad')}};
frQ('#frOutBtn').onclick=()=>frConfirm('确定要退出当前账号吗？',async()=>{await frApi('logout');frS.user=null;frMineData=null;frNavUser();frMinePage()});
if(!frMineData){try{frMineData=await frApi('mine')}catch(e){return}}
frMineTab('fav')}
function frSubOk(){const m=frModal('<div style="text-align:center;padding:6px 0"><div style="width:64px;height:64px;margin:0 auto;border-radius:50%;background:rgba(61,187,126,.14);display:grid;place-items:center;color:var(--fr-ok);font-size:24px;animation:frPop .5s"><i class="fa-solid fa-check"></i></div><b style="font-size:17px;display:block;margin-top:12px">投稿成功</b><p style="color:var(--fr-tx2);font-size:12px;margin:6px 0 18px">我们已收到你的投稿，可在个人中心跟踪进度</p><div style="text-align:left;max-width:270px;margin:0 auto">'+[['已提交','投稿成功，进入审核队列',1],['安全审核','人工核查软件安全与合规性',0],['精选上线','通过后正式收录并展示',0]].map((s,i)=>'<div style="display:flex;gap:12px"><div style="display:flex;flex-direction:column;align-items:center"><span style="width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:10px;flex:none;'+(s[2]?'background:var(--fr-ok);color:#fff':'background:rgba(128,132,150,.15);color:var(--fr-tx3)')+'"><i class="fa-solid '+(s[2]?'fa-check':'fa-clock')+'"></i></span>'+(i<2?'<span style="flex:1;width:2px;background:var(--fr-bd);margin:4px 0"></span>':'')+'</div><div style="padding-bottom:16px"><b style="font-size:13px">'+s[0]+'</b><p style="margin:2px 0 0;color:var(--fr-tx3);font-size:11px">'+s[1]+'</p></div></div>').join('')+'</div><button class="fr-btn fr-btn-p" style="width:100%;margin-top:6px" id="frSubOkGo">查看进度</button></div>',{title:'投稿状态'});
m.body.querySelector('#frSubOkGo').onclick=()=>{m.close();location.hash='#/mine'}}
function frSubmitPage(){const main=frQ('#frMain');
if(!frS.site.enableSubmit){main.innerHTML='<div class="fr-wrap" style="padding-top:50px">'+frEmpty('fa-box','投稿通道暂未开放','敬请期待')+'</div>';return}
if(!frS.user){main.innerHTML='<div class="fr-wrap"><div class="fr-card fr-rv fr-in" style="padding:60px 24px;text-align:center;max-width:420px;margin:60px auto"><div style="width:74px;height:74px;margin:0 auto 16px;border-radius:24px;background:rgba(91,108,255,.12);display:grid;place-items:center;color:var(--fr-p1);font-size:28px"><i class="fa-solid fa-paper-plane"></i></div><h2 style="margin:0 0 8px">登录后即可投稿</h2><p style="color:var(--fr-tx2);margin:0 0 20px;font-size:13px">分享你私藏的好软件，审核通过可获得积分</p><button class="fr-btn fr-btn-p" id="frSubLogin">登录 / 注册</button></div></div>';frQ('#frSubLogin').onclick=()=>frLoginModal(frSubmitPage);return}
main.innerHTML='<div class="fr-wrap" style="max-width:660px"><div class="fr-page-h fr-rv fr-in"><h1 class="fr-page-t"><i class="fa-solid fa-paper-plane" style="color:var(--fr-p1)"></i> 软件投稿</h1><p class="fr-page-s">分享你喜欢的好工具 · 审核收录后可获得积分奖励</p></div><form class="fr-card fr-rv fr-in" id="frSubForm" style="padding:26px;display:grid;gap:16px"><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frSubName">软件名称 *</label><input class="fr-inp" id="frSubName" maxlength="20" required></div><div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frSubVer">版本号</label><input class="fr-inp" id="frSubVer" placeholder="如 1.0.0"></div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frSubCat">分类 *</label><select class="fr-inp" id="frSubCat">'+frS.cats.map(c=>'<option value="'+c.id+'">'+frEsc(c.name)+'</option>').join('')+'</select></div><div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frSubLink">官网 / 下载链接 *</label><input class="fr-inp" id="frSubLink" placeholder="https://" required></div></div><div class="fr-frow" style="margin:0"><label class="fr-flabel" for="frSubDesc">推荐理由 *</label><textarea class="fr-inp" id="frSubDesc" rows="4" maxlength="300" placeholder="介绍一下这款软件的亮点与使用场景（至少 10 个字）" required></textarea></div><div style="display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:var(--fr-rs);background:rgba(24,192,178,.08);color:var(--fr-tx2);font-size:12px"><i class="fa-solid fa-circle-info" style="color:var(--fr-p2)"></i>投稿将进入人工审核，预计 48 小时内完成</div><button class="fr-btn fr-btn-p" style="width:100%"><i class="fa-solid fa-paper-plane"></i> 提交审核</button></form></div>';
frQ('#frSubForm').onsubmit=async e=>{e.preventDefault();const btn=e.target.querySelector('.fr-btn');btn.disabled=true;try{await frApi('submit',{name:frQ('#frSubName').value.trim(),ver:frQ('#frSubVer').value.trim(),catId:+frQ('#frSubCat').value,link:frQ('#frSubLink').value.trim(),desc:frQ('#frSubDesc').value.trim()});frSubOk()}catch(err){}btn.disabled=false}}
function frParse(){const h=(location.hash||'#/').replace(/^#\//,'');const seg=h.split('/');return{name:seg[0]||'home',p:decodeURIComponent(seg[1]||'')}}
function frNavOn(){const n=frS.route.name;const map={home:'home',list:'list',submit:'submit',mine:'mine'};frA('[data-nav]').forEach(a=>a.classList.toggle('on',a.dataset.nav===map[n]))}
async function frRoute(){clearInterval(frS.carT);clearInterval(frS.nT);frS.route=frParse();const main=frQ('#frMain');main.classList.remove('fr-fade');void main.offsetWidth;main.classList.add('fr-fade');window.scrollTo({top:0});try{await frBoot()}catch(e){return}
const r=frS.route;
if(r.name==='app'&&r.p)await frAppPage(+r.p);
else if(r.name==='list')await frListPage();
else if(r.name==='mine')await frMinePage();
else if(r.name==='submit')frSubmitPage();
else await frHomePage();
frNavOn();frReveal();setTimeout(()=>{const sp=frQ('#frSplash');if(sp)sp.classList.add('off')},500)}
function frSetTheme(t){document.documentElement.dataset.frTheme=t;localStorage.setItem('frTheme',t);const i=frQ('#frThemeBtn i');if(i)i.className=t==='dark'?'fa-solid fa-sun':'fa-solid fa-moon'}
frQ('#frThemeBtn').onclick=()=>frSetTheme(document.documentElement.dataset.frTheme==='dark'?'light':'dark');
frSetTheme(document.documentElement.dataset.frTheme||'light');
frQ('#frNavQ').addEventListener('keydown',e=>{if(e.key==='Enter'){const v=e.target.value.trim();location.hash=v?'#/list/q-'+encodeURIComponent(v):'#/list'}});
window.addEventListener('hashchange',frRoute);
frRoute();
</script>
</body>
</html>
