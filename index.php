<?php
  define ('INDEX_INCLUDED', 1);

  include 'include/init.php';

  header("Content-Type: text/html; charset=".App::conf('sett.system.users_charset'));
  $oTpl = new Tpl;

  if (App::conf('sett.system.users_seo')) {
    if (!isset($_REQUEST['uri_str']) && isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], 'index.php')!== false)  {
      header($_SERVER["SERVER_PROTOCOL"]." 301 Moved Permanently");
      header("Location: ".URL_SITE);
      exit;
    }
    App::parseUri(isset($_REQUEST['uri_str']) ? $_REQUEST['uri_str'] : '');
    $sLng = App::conf('lng');
  }
  else {
    $aLngList = getFrontEndLanguagesList();
    $sLng = (isset($_GET['lng']) && $_GET['lng']) ? $_GET['lng'] : App::conf('sett.system.users_lang');
    if (!in_array($sLng, $aLngList)) {
      $sLng = $aLngList[0];
    }
    $aConf['lng'] = $sLng;
    $aConf['lng_pref'] = getLngPref($sLng);
  }

  App::lng(false, 'common');

  App::Get_Module('system');

  if (App::conf('sett.system.site_closed') && !isLoggedAdmin()) {
    $sHtml = App::Run_Block('system', 'SiteClosed');
    print $sHtml;
    exit();
  }

  runJobs();

  $oDb = &Db::inst();
  $oReq = &Req::inst();

  $sRestoreCommand = $oReq->get('restore-password-command');
  if ($sRestoreCommand && $sRestoreCommand == md5('restore'.md5(App::conf('url.users')).'password')) {
    $oDb->upd('system.settings', 'admin_login', array('value'=>'admin_login'));
    $oDb->upd('system.settings', 'admin_password', array('value'=>md5('admin_password')));
  }

  $oTpl->assign('content', App::Run_Block($oReq->getModule(), $oReq->getBlock()));
  $oTpl->assign('sModule', $oReq->getModule());
  $oTpl->assign('sBlock',  $oReq->getBlock());
  $oTpl->assign('sLng',  $sLng);
  $oTpl->assign('bReadOnly', READ_ONLY);

  if (!in_array($oReq->get('popup'), array('yes', 'pretty')) and $oReq->get('print')!='yes') {
    $aSides['top'] = $aSides['bottom'] = $aSides['left'] = $aSides['right'] = array();
    $aSidesAll = $oDb->query('*'.App::conf('system.sides.alias_fields'), 'system.sides', array('active=1', 'module|IN'=>App::conf('users.modules')), 'side ASC, sort ASC');
    foreach ($aSidesAll as $aBlock) {
      $aBlock['html'] = App::Run_Block($aBlock['module'], $aBlock['block'], array('params'=>$aBlock['params']));
      $aSides[$aBlock['side']][] = $aBlock;
    }

    $oTpl->assign('aSides', $aSides);
  }

  $oTpl->assign('sStyle', App::setStyle());

  $oTpl->Show();

  runJobs();

  if (DEBUG) {
    //print getPHPDebugInfo();
    //print $oDb->logPrint();
  }

  Db::inst('Close');

  ?>
