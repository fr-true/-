<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
if (!defined('FR_ROOT')) define('FR_ROOT', __DIR__);
if (!defined('FR_DATA')) define('FR_DATA', __DIR__ . '/frData');
$frEnv = @include FR_ROOT . '/frEnv.php';
if (!is_array($frEnv)) {
  if (defined('FR_INSTALLING')) $frEnv = ['driver' => 'json'];
  elseif (is_file(FR_DATA . '/config.json')) $frEnv = ['driver' => 'json'];
  else { header('Location: install.php'); exit; }
}
if (!empty($frEnv['timezone'])) date_default_timezone_set($frEnv['timezone']);
if (($frEnv['driver'] ?? 'json') === 'json') @mkdir(FR_DATA, 0755, true);

function frDb() {
  global $frEnv;
  static $frPdo = null;
  if ($frPdo === null) {
    $frD = $frEnv['db'] ?? [];
    $frPdo = new PDO('mysql:host=' . ($frD['host'] ?? '127.0.0.1') . ';port=' . (int)($frD['port'] ?? 3306) . ';dbname=' . ($frD['name'] ?? '') . ';charset=utf8mb4', $frD['user'] ?? '', $frD['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
  }
  return $frPdo;
}
function frTable() { global $frEnv; return ($frEnv['db']['prefix'] ?? 'fr_') . 'store'; }
function frLoad($frName, $frDef = []) {
  global $frEnv;
  if (($frEnv['driver'] ?? 'json') === 'mysql') {
    try {
      $frSt = frDb()->prepare('SELECT fr_val FROM `' . frTable() . '` WHERE fr_key = ? LIMIT 1');
      $frSt->execute([$frName]);
      $frRow = $frSt->fetch(PDO::FETCH_ASSOC);
      if (!$frRow) return $frDef;
      $frD = json_decode($frRow['fr_val'], true);
      return is_array($frD) ? $frD : $frDef;
    } catch (Throwable $frE) { return $frDef; }
  }
  $frPath = FR_DATA . '/' . $frName . '.json';
  if (!is_file($frPath)) return $frDef;
  $frD = json_decode((string)@file_get_contents($frPath), true);
  return is_array($frD) ? $frD : $frDef;
}
function frSave($frName, $frData) {
  global $frEnv;
  $frJson = json_encode($frData, JSON_UNESCAPED_UNICODE);
  if (($frEnv['driver'] ?? 'json') === 'mysql') {
    try {
      $frSt = frDb()->prepare('INSERT INTO `' . frTable() . '` (fr_key, fr_val, fr_updated) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE fr_val = VALUES(fr_val), fr_updated = NOW()');
      $frSt->execute([$frName, $frJson]);
    } catch (Throwable $frE) {}
    return;
  }
  $frFp = @fopen(FR_DATA . '/' . $frName . '.json', 'c');
  if (!$frFp) return;
  if (flock($frFp, LOCK_EX)) { ftruncate($frFp, 0); fwrite($frFp, $frJson); fflush($frFp); flock($frFp, LOCK_UN); }
  fclose($frFp);
}
function frNid($frArr) { $frM = 0; foreach ((array)$frArr as $frR) $frM = max($frM, (int)($frR['id'] ?? 0)); return $frM + 1; }
function frJson($frData) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($frData, JSON_UNESCAPED_UNICODE); exit; }
function frOk($frData = []) { frJson(['code' => 0] + $frData); }
function frErr($frMsg, $frCode = 1) { frJson(['code' => $frCode, 'msg' => $frMsg]); }
function frLog($frAct, $frDetail = '', $frWho = '游客') {
  $frLogs = frLoad('logs', []);
  array_unshift($frLogs, ['id' => frNid($frLogs), 'who' => $frWho, 'act' => $frAct, 'detail' => $frDetail, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'at' => date('Y-m-d H:i:s')]);
  frSave('logs', array_slice($frLogs, 0, 600));
}
function frTrackVisit() {
  $frS = frLoad('stats', ['days' => [], 'dDays' => []]);
  $frD = date('Y-m-d');
  $frS['total'] = ($frS['total'] ?? 0) + 1;
  $frS['today'] = (($frS['todayDay'] ?? '') === $frD) ? ($frS['today'] ?? 0) + 1 : 1;
  $frS['todayDay'] = $frD;
  $frS['days'][$frD] = ($frS['days'][$frD] ?? 0) + 1;
  if (count($frS['days']) > 45) $frS['days'] = array_slice($frS['days'], -45, null, true);
  frSave('stats', $frS);
}
function frGuard($frCfg) {
  if (!empty($frCfg['antiCrawler'])) {
    $frUa = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($frUa === '' || preg_match('/curl|wget|python|scrapy|httpclient|spider|crawler|java\//i', $frUa)) frErr('访问受限', 429);
    $frT = time();
    if (empty($_SESSION['frWin']) || $frT - $_SESSION['frWin'] > 10) { $_SESSION['frWin'] = $frT; $_SESSION['frHit'] = 0; }
    $_SESSION['frHit']++;
    if ($_SESSION['frHit'] > (int)($frCfg['rateLimit'] ?? 40)) frErr('请求过于频繁，请稍后再试', 429);
  }
}
function frUploadInit() {
  $frDir = FR_ROOT . '/frUploads';
  if (!is_dir($frDir)) @mkdir($frDir, 0755, true);
  if (!is_file($frDir . '/.htaccess')) @file_put_contents($frDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phar|pl|py|cgi|sh)$\">\nRequire all denied\n</FilesMatch>\n");
  if (!is_file($frDir . '/index.html')) @file_put_contents($frDir . '/index.html', '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title></head><body>403 Forbidden</body></html>');
  return $frDir;
}
function frUploadHandle($frKind, $frCfg) {
  $frMb = max(1, min(10, (int)($frCfg['upMax'] ?? 2)));
  $frMax = $frMb * 1048576;
  $frAllow = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
  $frF = $_FILES['frFile'] ?? null;
  $frErrMap = [1 => '超出服务器 PHP 上传限制', 2 => '超出大小限制', 3 => '文件上传不完整', 4 => '未选择文件', 6 => '服务器缺少临时目录', 7 => '文件写入失败', 8 => '上传被扩展阻止'];
  if (!$frF || ($frF['error'] ?? 4) !== UPLOAD_ERR_OK) frErr($frErrMap[$frF['error'] ?? 4] ?? '上传失败');
  if ($frF['size'] > $frMax) frErr('图片大小超出限制（' . $frMb . ' MB）');
  $frExt = strtolower(pathinfo($frF['name'], PATHINFO_EXTENSION));
  if (!isset($frAllow[$frExt])) frErr('仅支持 JPG / PNG / GIF / WEBP 格式');
  if (extension_loaded('fileinfo')) {
    $frMime = (new finfo(FILEINFO_MIME_TYPE))->file($frF['tmp_name']);
    if (!in_array($frMime, $frAllow, true)) frErr('文件内容校验未通过');
  }
  $frInfo = @getimagesize($frF['tmp_name']);
  if (!$frInfo) frErr('不是有效的图片文件');
  if ($frInfo[0] > 4000 || $frInfo[1] > 4000) frErr('图片尺寸过大（最长边 4000px）');
  $frDir = frUploadInit() . '/' . date('Ym');
  @mkdir($frDir, 0755, true);
  $frName = $frKind . '_' . date('dHis') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . ($frExt === 'jpeg' ? 'jpg' : $frExt);
  if (!move_uploaded_file($frF['tmp_name'], $frDir . '/' . $frName)) frErr('保存失败，请检查目录权限');
  return 'frUploads/' . date('Ym') . '/' . $frName;
}
function frUpPath($frUrl) { return preg_match('#^frUploads/\d{6}/[A-Za-z0-9_]+\.(jpg|jpeg|png|gif|webp)$#i', (string)$frUrl) ? (string)$frUrl : ''; }
function frSeed($frDemo = true) {
  $frCfg = frLoad('config', []);
  frSave('cats', [
    ['id' => 1, 'name' => '效率办公', 'icon' => 'fa-briefcase'],
    ['id' => 2, 'name' => '图形设计', 'icon' => 'fa-palette'],
    ['id' => 3, 'name' => '开发工具', 'icon' => 'fa-code'],
    ['id' => 4, 'name' => '影音媒体', 'icon' => 'fa-clapperboard'],
    ['id' => 5, 'name' => '系统工具', 'icon' => 'fa-gears'],
    ['id' => 6, 'name' => '网络安全', 'icon' => 'fa-shield-halved'],
    ['id' => 7, 'name' => '教育教学', 'icon' => 'fa-graduation-cap'],
  ]);
  frSave('subs', []);
  frSave('dls', []);
  if (!$frDemo) {
    frSave('apps', []); frSave('users', []); frSave('comments', []);
    frSave('cars', [['id' => 1, 'badge' => '欢迎', 'title' => $frCfg['siteName'] ?? '软件仓库', 'sub' => $frCfg['slogan'] ?? '发现好软件 · 分享高效体验', 'hue' => 243, 'link' => '#/', 'top' => 1, 'img' => '']]);
    frSave('notices', [['id' => 1, 'title' => '欢迎', 'content' => '站点初始化完成，进入后台发布你的第一条公告吧。', 'top' => 1, 'at' => date('Y-m-d H:i')]]);
    frSave('stats', ['total' => 0, 'today' => 0, 'todayDay' => date('Y-m-d'), 'dTotal' => 0, 'dToday' => 0, 'dTodayDay' => date('Y-m-d'), 'days' => [], 'dDays' => []]);
    return;
  }
  $frSeed = [
    [1, '星图 Pro', 'fa-diagram-project', 243, 268, 1, ['思维导图', '头脑风暴', '协作'], '3.8.2', '86.4 MB', '星舰科技', 'AI 驱动的思维导图与头脑风暴效率工具', "轻量却强大的思维导图工具，帮助你把零散灵感整理成清晰结构。\n内置 AI 自动布局引擎，支持多人实时协作、云端同步与一键导出多种格式。\n- AI 智能布局，一键自动整理节点\n- 多人实时协作，支持评论与任务指派\n- 支持导出 PNG / SVG / PDF / Markdown\n- 跨设备云同步，历史版本自动保存", 128400, 3220, 4.9, 1, 1],
    [2, '轻羽录屏', 'fa-video', 190, 210, 4, ['录屏', '直播', '教程'], '2.5.0', '42.1 MB', '轻羽工作室', '高清流畅的屏幕录制与教程制作工具', "低占用高画质的屏幕录制工具，长时间录制也不卡顿。\n内置光标特效、按键显示与音画同步引擎，录制完成即可快速剪辑分享。\n- 1080P / 60FPS 高清录制不掉帧\n- 智能降噪，人声清晰自然\n- 一键导出 MP4 / GIF，体积更小", 96200, 2140, 4.8, 0, 3],
    [3, '清风压缩', 'fa-box-archive', 152, 170, 5, ['压缩', '解压', '加密'], '5.1.4', '18.2 MB', '清风实验室', '极速无广告的万能压缩解压工具', "支持 30 余种压缩格式，极速内核让大文件压缩快人一步。\n全程无广告无弹窗，还内置分卷压缩与 AES-256 加密。\n- 支持 RAR / 7Z / ZIP 等主流格式\n- 右键菜单一键压缩解压\n- 加密压缩包，隐私更安全", 152300, 4100, 4.7, 1, 6],
    [4, '像素工坊', 'fa-wand-magic-sparkles', 325, 350, 2, ['修图', '设计', '滤镜'], '8.0.1', '312.5 MB', '像素视觉', '专业级图像处理与创意设计套件', "从照片精修到海报设计的一站式创作平台。\n全新图层引擎与 AI 抠图让复杂操作一步到位，内置千款滤镜与模板。\n- AI 一键抠图与智能补全\n- 非破坏性图层编辑，随时回退\n- 海量正版模板，海报封面快速产出", 88400, 3510, 4.9, 0, 2],
    [5, '代码领航员', 'fa-code', 212, 232, 3, ['编辑器', '编程', '调试'], '1.9.7', '128.0 MB', '极光环开源社区', '为现代开发者打造的智能编程环境', "启动飞快、插件丰富的现代代码编辑器。\n内置智能补全、代码导航与终端集成，覆盖主流语言与框架。\n- 毫秒级智能补全与片段建议\n- 内置调试器与 Git 可视化\n- 插件市场，生态持续扩展", 74500, 2860, 4.8, 1, 4],
    [6, '声波实验室', 'fa-wave-square', 268, 288, 4, ['音频', '剪辑', '降噪'], '4.2.3', '96.7 MB', '声波工场', '专业音频剪辑降噪与混音工作站', "面向播客与音乐创作者的音频工作台。\n多轨混音、频谱编辑与 AI 降噪一应俱全，导出格式丰富。\n- 多轨时间线，拖拽即可剪辑\n- AI 一键降噪与人声增强\n- 支持 WAV / FLAC / MP3 批量转换", 45200, 1230, 4.6, 0, 9],
    [7, '暗影保险箱', 'fa-shield-halved', 228, 248, 6, ['密码', '加密', '隐私'], '3.3.0', '24.6 MB', '暗影安全', '军事级加密的密码与隐私管理工具', "本地优先的密码管理器，AES-256 全库加密。\n支持自动填充、两步验证令牌与泄露监测，账号安全一手掌握。\n- 端到端加密，密钥只属于你\n- 浏览器与移动端自动填充\n- 暗网泄露监测实时预警", 60100, 1980, 4.9, 0, 7],
    [8, '极简日历', 'fa-calendar-days', 24, 40, 1, ['日程', '待办', '提醒'], '6.4.2', '32.0 MB', '简致科技', '回归本真的日程与待办管理应用', "没有冗余功能的日程管理工具，把时间还给重要的事。\n支持农历、订阅日历与自然语言快速创建日程。\n- 输入下周三开会自动识别时间\n- 农历节气与节假日一览\n- 桌面小组件与多端同步提醒", 53800, 1620, 4.5, 0, 11],
    [9, '雷霆同步', 'fa-cloud-arrow-up', 200, 220, 5, ['备份', '同步', '网盘'], '2.8.8', '64.3 MB', '雷霆网络', '多端文件自动同步与增量备份专家', "把手机、电脑与网盘串成一张同步网络。\n增量传输与断点续传让大文件备份省时省心，传输全程加密。\n- 文件夹双向 / 单向同步策略\n- 增量备份，只传改动部分\n- 历史版本找回，误删不慌", 71300, 2260, 4.6, 0, 8],
    [10, '快剪 FilmKit', 'fa-film', 348, 10, 4, ['视频剪辑', '字幕', '特效'], '7.7.0', '428.9 MB', 'FilmKit 工作室', '人人可用的智能视频剪辑创作平台', "拖拖拽拽就能出片的智能剪辑工具。\nAI 字幕、智能配乐与海量转场特效，让创作门槛降到极低。\n- AI 语音转字幕，准确率行业领先\n- 一键智能配乐与卡点\n- 4K 导出，多平台尺寸预设", 118600, 3980, 4.8, 1, 5],
    [11, '题速解', 'fa-square-root-variable', 46, 60, 7, ['数学', '解题', '学习'], '3.0.5', '58.1 MB', '启明灯教育', '拍照即解的智能数学学习助手', "覆盖小学到高中的数学解题助手。\n拍照识别题目，逐步讲解思路而不只给答案，帮助孩子真正学会。\n- 拍照秒出分步解析\n- 错题本自动归集，考前突击\n- 知识点图谱，薄弱项一目了然", 39400, 980, 4.4, 0, 13],
    [12, '轻羽清理', 'fa-broom', 164, 184, 5, ['清理', '加速', '优化'], '1.6.9', '21.4 MB', '轻羽工作室', '一键深度清理，让设备快如新机', "智能识别缓存垃圾、重复文件与大文件，一键释放空间。\n所有操作均可预览后再执行，不误删任何重要数据。\n- 智能分类，垃圾一目了然\n- 重复文件与相似照片检测\n- 启动项管理，开机更快", 49800, 1450, 4.5, 0, 10],
  ];
  $frApps = [];
  foreach ($frSeed as $frS) {
    $frApps[] = ['id' => $frS[0], 'name' => $frS[1], 'icon' => $frS[2], 'hue' => $frS[3], 'hue2' => $frS[4], 'catId' => $frS[5], 'tags' => $frS[6], 'ver' => $frS[7], 'size' => $frS[8], 'dev' => $frS[9], 'short' => $frS[10], 'desc' => $frS[11], 'downloads' => $frS[12], 'favs' => $frS[13], 'rating' => $frS[14], 'top' => $frS[15], 'status' => 1, 'views' => mt_rand(3000, 26000), 'created' => time() - mt_rand(90, 320) * 86400, 'updated' => time() - $frS[16] * 86400, 'iconImg' => '', 'shotsImg' => [], 'links' => [['label' => '官方镜像', 'url' => 'https://example.com/dl/' . $frS[0] . '/official'], ['label' => '电信线路', 'url' => 'https://example.com/dl/' . $frS[0] . '/ct'], ['label' => '备用下载', 'url' => 'https://example.com/dl/' . $frS[0] . '/bak']]];
  }
  frSave('apps', $frApps);
  $frUsers = [];
  foreach ([['清风徐来', 'qing@fr.cn', 210], ['代码诗人', 'code@fr.cn', 262], ['白桃乌龙', 'peach@fr.cn', 330], ['南山南', 'nan@fr.cn', 160]] as $frI => $frU) {
    $frUsers[] = ['id' => $frI + 1, 'name' => $frU[0], 'email' => $frU[1], 'pass' => password_hash('123456', PASSWORD_DEFAULT), 'hue' => $frU[2], 'points' => mt_rand(30, 180), 'role' => 'user', 'at' => date('Y-m-d', time() - ($frI + 9) * 86400), 'sign' => '', 'favs' => [], 'ava' => ''];
  }
  frSave('users', $frUsers);
  $frCS = [[1, 1, 1, '用了一周，AI 布局是真的好用，会议纪要整理效率翻倍。', 5, 1, 3], [2, 1, 2, '多人协作很流畅，导出 Markdown 偶尔丢图标，期待修复。', 4, 1, 5], [3, 3, 3, '良心软件，完全没广告，解压速度比某些老牌工具快多了。', 5, 1, 2], [4, 10, 4, '剪辑新手也能快速上手，智能字幕识别准确率很高。', 5, 1, 6], [5, 4, 2, '滤镜质感一流，图层编辑很顺手，就是安装包有点大。', 4, 1, 8], [6, 5, 1, '插件生态越来越丰富，启动速度这版优化明显。', 5, 1, 4], [7, 7, 3, '密码管理就图个安心，本地加密加云端备份很稳。', 5, 1, 7], [8, 2, 4, '录屏不掉帧，导出体积小，做教程的利器。', 5, 0, 1]];
  $frCmts = [];
  foreach ($frCS as $frC) {
    $frCmts[] = ['id' => $frC[0], 'appId' => $frC[1], 'userId' => $frC[2], 'user' => $frUsers[$frC[2] - 1]['name'], 'hue' => $frUsers[$frC[2] - 1]['hue'], 'ava' => '', 'text' => $frC[3], 'stars' => $frC[4], 'status' => $frC[5], 'at' => time() - $frC[6] * 86400];
  }
  frSave('comments', $frCmts);
  frSave('cars', [
    ['id' => 1, 'badge' => '新版上线', 'title' => '星图 Pro 3.8 全新发布', 'sub' => 'AI 布局引擎，让思维导图效率翻倍', 'hue' => 243, 'link' => '#/app/1', 'top' => 1, 'img' => ''],
    ['id' => 2, 'badge' => '编辑精选', 'title' => '效率工具精选：让工作事半功倍', 'sub' => '每周更新高分效率应用专题', 'hue' => 190, 'link' => '#/list/cat-1', 'top' => 0, 'img' => ''],
    ['id' => 3, 'badge' => '安全专区', 'title' => '安全软件专题周', 'sub' => '守护设备与数据隐私，从此安心', 'hue' => 152, 'link' => '#/list/cat-6', 'top' => 0, 'img' => ''],
    ['id' => 4, 'badge' => '活动', 'title' => '投稿通道现已开启', 'sub' => '分享你私藏的好软件，赚取会员积分', 'hue' => 330, 'link' => '#/submit', 'top' => 0, 'img' => ''],
  ]);
  frSave('notices', [
    ['id' => 1, 'title' => '欢迎', 'content' => '欢迎来到软件仓库，浏览、下载、评分一站式完成，注册即送新人积分。', 'top' => 1, 'at' => date('Y-m-d H:i')],
    ['id' => 2, 'title' => '投稿规范', 'content' => '投稿需附带官网链接与真实介绍，审核周期约 48 小时，重复或侵权内容将被驳回。', 'top' => 0, 'at' => date('Y-m-d H:i')],
    ['id' => 3, 'title' => '安全提示', 'content' => '下载后请核对文件签名，如发现异常请在详情页点击举报，我们会尽快处理。', 'top' => 0, 'at' => date('Y-m-d H:i')],
  ]);
  $frDays = []; $frDDays = []; $frTotal = 38216; $frDT = 9120;
  for ($frI = 13; $frI >= 0; $frI--) {
    $frD = date('Y-m-d', strtotime("-$frI day"));
    $frV = $frI === 0 ? mt_rand(60, 180) : mt_rand(320, 980);
    $frDV = $frI === 0 ? mt_rand(20, 80) : mt_rand(150, 600);
    $frDays[$frD] = $frV; $frDDays[$frD] = $frDV; $frTotal += $frV; $frDT += $frDV;
  }
  frSave('stats', ['total' => $frTotal, 'today' => $frDays[date('Y-m-d')], 'todayDay' => date('Y-m-d'), 'dTotal' => $frDT, 'dToday' => $frDDays[date('Y-m-d')], 'dTodayDay' => date('Y-m-d'), 'days' => $frDays, 'dDays' => $frDDays]);
}
