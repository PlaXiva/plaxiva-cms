<?php
  define ('INDEX_INCLUDED', 1);
  define ('INSTALL_INCLUDED', 1);

  function set_config_value($config_content, $var, $value)
  {
    return preg_replace("!define\(\s*'$var'\s*,[^)]+\)!si", "define('$var', $value)", $config_content);
  }


  function isProductInstalled($sHost, $sUser, $sPass, $sDbName, $sPref)
  {
    // initiate DB

    $oDb = &Db::inst($sHost, $sUser, $sPass, $sDbName, $sPref);

    $aTbls = $oDb->ListTables($sDbName);

    foreach ($aTbls as $kkk => $sTbl) {
      if (preg_match("!^{$sPref}system.*$!", $sTbl)) {
        return true;
      }
    }

    return false;
  }

  function createHtaccess($sUrl)
  {
    $aUrl = parse_url($sUrl);
    $sPath  = trim($aUrl['path']);

    if (substr($sPath, -1, 1) == '/') {
      $sPath = substr($sPath, 0, -1);
    }

    $sFile = file_get_contents(App::conf('path.docs').'htaccess.template.txt');

    $sFile = str_replace('/path', $sPath, $sFile);

    $fh = fopen(App::conf('path.docs').'.htaccess', 'w');
    fwrite($fh, $sFile);
    fclose($fh);
  }

  include 'include/init.php';

  $aConf['lng'] = (isset($_GET['lng']) && $_GET['lng'] && in_array($_GET['lng'], array_keys(App::conf('langs.list')))) ? $_GET['lng'] : 'en';
  $aConf['lng_pref'] = getLngPref(App::conf('lng'));

  App::lng(false, 'common');

  $oTpl = new Tpl;

  $aForm = array(
    'url_site' => array(
      'title'=>App::lng('pi_Url_Site'),
      'valid'=>'url',
      'value'=>'http://' . preg_replace('!install.php.*!', '', $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
      'require'=>'yes',
    ),

    'db_type' => array(
      'title'=>App::lng('pi_Db_Type'),
      'type'=>'select',
      'options'=>array('mysql'=>'MySql'),
      'value'=>'mysql',
      'require'=>'yes',
    ),

    'db_name' => array(
      'title'=>App::lng('pi_Db_Name'),
      'value'=>'mydb',
      'require'=>'yes',
    ),

    'db_host' => array(
      'title'=>App::lng('pi_Db_Host'),
      'value'=>'localhost',
      'require'=>'yes',
    ),

    'db_pref' => array(
      'title'=>App::lng('pi_Db_Prefix'),
      'value'=>'xiva__',
      'require'=>'yes',
    ),


    'db_user' => array(
      'title'=>App::lng('pi_Db_User'),
      'value'=>'root',
      'require'=>'yes',
    ),

    'db_pass' => array(
      'title'=>App::lng('pi_Db_Password'),
      'value'=>'',
      'require'=>'no',
    ),

    'admin_email' => array(
      'title'=>App::lng('pi_Admin_Email'),
      'value'=>'admin@'.str_replace('www.', '', $_SERVER['HTTP_HOST']),
      'require'=>'yes',
      'valid'=>'email',
    ),

    'create_db' => array(
      'type'=>'radio',
      'options'=> 'auto:yes_no',
      'title'=>App::lng('pi_Create_Tables'),
      'value'=>'1',
      'require'=>'yes',
      'layout'=>'horizontal',
    ),

    );

  $oForm = Forms::get('install', $aForm);

  $msg = '';

  $bReady = true;
  $bWarns = false;
  $bInstalled = false;

  $bDone = true;
  $aLog = array();

  $iStep = 1;

  App::Get_Module('system');

  $aModulesList = array_merge(array('system'), getInstallModulesList(array('system')));
  $aModules = array();

  foreach ($aModulesList as $sModule) {
    $oModule = App::Get_Module($sModule);

    $aModules[$sModule]['name'] = $sModule;
    $aModules[$sModule]['description'] = $oModule->Get_Description();
    $aModules[$sModule]['version'] = $oModule->Get_Version();
    $aModules[$sModule]['checked'] = (isset($_POST['modules'][$sModule]) or $sModule=='system' or empty($_POST)) ? 1 : 0;

    list($aModules[$sModule]['aReq'],
         $aModules[$sModule]['bReady'],
         $aModules[$sModule]['bWarns']) = $oModule->checkRequirenments();

    if ((isset($_POST['modules'][$sModule]) or $sModule=='system') && !$aModules[$sModule]['bReady']) {
      $bReady = false;
    }
  }


  if (!empty($_POST) && $oForm->Validate($_POST) && $bReady) {

    // check db
    $conn = @mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass']);
    if (!$conn) {
      $msg = App::lng('pi_err_db_connection').' '. @mysql_error();
    }
    elseif (! @mysql_select_db($_POST['db_name'], $conn)) {
      $msg = App::lng('pi_err_db_select').' '.$_POST['db_name'];
    }

    if (!$msg) {

      $bInstalled = isProductInstalled($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], $_POST['db_pref']);

      if (isset($_POST['create_db']) && $_POST['create_db'] && !$bInstalled) {

        // install selected modules

        foreach ($aModules as $kkk=>$vvv) {
          if (!$vvv['checked']) continue;

          $oModule = App::Get_Module($vvv['name']);

          list($bModuleDone, $aLog) = $oModule->createModuleDb($_POST['db_pref']);

          if ($bModuleDone) {
            $oModule->finishInstall(); // $msg =
          }
          else {
            $bDone=false;
            break;
          }
        }

      }

      if ($bDone) {

        // set config values
        $_POST['url_site'] = trim($_POST['url_site']);
        if (substr($_POST['url_site'], -1, 1) != '/') {
          $_POST['url_site'] .= '/';
        }

        $config_file = App::conf('path.include').'config.php';
        $config_content = file_get_contents($config_file);
        $config_content = set_config_value($config_content, 'DB_TYPE', "'".trim($_POST['db_type'])."'");
        $config_content = set_config_value($config_content, 'DB_HOST', "'".trim($_POST['db_host'])."'");
        $config_content = set_config_value($config_content, 'DB_USER', "'".trim($_POST['db_user'])."'");
        $config_content = set_config_value($config_content, 'DB_PASS', "'".trim($_POST['db_pass'])."'");
        $config_content = set_config_value($config_content, 'DB_NAME', "'".trim($_POST['db_name'])."'");
        $config_content = set_config_value($config_content, 'DB_PREF', "'".trim($_POST['db_pref'])."'");
        $config_content = set_config_value($config_content, 'URL_SITE',"'".trim($_POST['url_site'])."'");
        $config_content = set_config_value($config_content, 'DEBUG', 'false');
        $config_content = set_config_value($config_content, 'DEBUG_LEVEL', 'false');

        global $aConf;
        $aConf['url.users'] = $_POST['url_site'];

        if (!$handle = fopen($config_file, 'w')) {
          $msg = 'Cannot open config file ('.$config_file.')';
        }
        else {
          if (!fwrite($handle, $config_content)) {
            $msg = 'Cannot write to config file ('.$config_file.')';
          }
          fclose($handle);
        }


        createHtaccess($_POST['url_site']);

        // send emails

        if (!preg_match("!^(127\.|192\.|10\.|172\.)!",$_SERVER['SERVER_ADDR'])) {
          @mail('installation@plaxiva.com','PlaXiva CMS Installed!',
          "Hi, I have installed it!"."\n".
          "HTTP_HOST: {$_SERVER['HTTP_HOST']}\n".
          "URL_SITE: {$_POST['url_site']}\n".
          "SERVER_ADDR: {$_SERVER['SERVER_ADDR']}\n".
          "SCRIPT_FILENAME: {$_SERVER['SCRIPT_FILENAME']}\n".
          "REMOTE_ADDR: {$_SERVER['REMOTE_ADDR']}",
          "From: ".$_POST['admin_email']);

          @mail($_POST['admin_email'], 'PlaXiva CMS Installed!',
          "Hi,\n\n".
          "PlaXiva CMS Installed successfully!\n\n".
          "Administrative Panel: {$_POST['url_site']}admin/\n".
          "Administrator Login: admin/123qwe\n\n".
          "HTTP_HOST: {$_SERVER['HTTP_HOST']}\n".
          "URL_SITE: {$_POST['url_site']}\n".
          "SERVER_ADDR: {$_SERVER['SERVER_ADDR']}\n".
          "SCRIPT_FILENAME: {$_SERVER['SCRIPT_FILENAME']}\n".
          "REMOTE_ADDR: {$_SERVER['REMOTE_ADDR']}",
          "From: ".'support@plaxiva.com');

        }


      }

      $iStep = 2;

    }

  }
  else {
    if (!$bReady) {
      $msg = App::lng('pi_Check_Requirements');
    }
  }

  $aInputs = $oForm->getInputs($_POST);

  $oTpl->assign('aInputs', $aInputs);
  $oTpl->assign('aModules', $aModules);
  $oTpl->assign('bReady', $bReady);

  $oTpl->assign('aLog', $aLog);
  $oTpl->assign('bDone', $bDone);
  $oTpl->assign('bInstalled', $bInstalled);
  $oTpl->assign('iStep', $iStep);

  $oTpl->assign('aLng', App::conf('langs.list'));
  $oTpl->assign('sLng', App::conf('lng'));
  $oTpl->assign('msg', $msg);

  $oTpl->Show();


  ?>