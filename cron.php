<?php
  define ('INDEX_INCLUDED', 1);
  define ('IS_CRON', 1);

  if (!(isset($argv[1]) && $argv[1] == 'run')) {
    exit();
  }

  include 'include/init.php';

  if (defined('DEMO') && DEMO == 1) {
    exit();
  }

  $sLng = App::conf('sett.system.users_lang');
  $aConf['lng'] = $sLng;
  $aConf['lng_pref'] = getLngPref($sLng);

  App::lng(false, 'common');

  App::Get_Module('system');

  $oDb = &Db::inst();

  $iCronPeriod = App::conf('sett.system.cron_period');

  $aDate = date('j.n.Y.G.i.w', time());
  list($iDay, $iMonth, $iYear, $iHour, $iMin, $iDow) = explode('.', $aDate);

  $iMin = (int)$iMin;
  $iMin = $iCronPeriod * floor($iMin/$iCronPeriod);

  if ($iDow == 0) {
    $iDow = 7;
  }

  $aWhere = array(
    'module|IN'   =>  App::conf('users.modules'),
    'min|LIKE'    =>  ','.$iMin.',',
    'hour|LIKE'   =>  ','.$iHour.',',
    'day|LIKE'    =>  ','.$iDay.',',
    'month|LIKE'  =>  ','.$iMonth.',',
    'dow|LIKE'    =>  ','.$iDow.',',
    'active=1',
  );

  $aCrons = $oDb->query('*', 'system.crons', $aWhere, 'sort ASC');

  $aCronRunned = array();

  foreach ($aCrons as $kkk => $aCron) {
    if (in_array($aCron['module'].','.$aCron['block'].','.$aCron['params'], $aCronRunned)) continue;

    $aLog = array(
      'cron_id'   => $aCron['id'],
      'module'    => $aCron['module'],
      'block'     => $aCron['block'],
      'params'    => $aCron['params'],
      'title'     => App::lng('title_'.$aCron['block'], $aCron['module']),
      'status'    => 0,
      'msg'       => '',
      'cron_date' => mktime($iHour, $iMin, 0, $iMonth, $iDay, $iYear),
      'date'      => time(),
    );

    $oDb->ins('system.crons_log', $aLog);

    $log_id = $oDb->InsertID();

    $sMsg = App::Run_Block($aCron['module'], $aCron['block'], array('params'=>$aCron['params']));

    $aMsg = explode('|', $sMsg);

    if (isset($aMsg[0]) && isset($aMsg[1]) && ($aMsg[0]==0 or $aMsg[0]==1)) {
      $oDb->upd('system.crons_log', $log_id, array('status'=>$aMsg[0], 'msg'=>$aMsg[1]));
    }

    $aCronRunned[] = $aCron['module'].','.$aCron['block'].','.$aCron['params'];
  }

  //print getPHPDebugInfo();
  //print $oDb->logPrint();

  Db::inst('Close');

  ?>