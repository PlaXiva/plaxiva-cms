<?php
  define ('INDEX_INCLUDED', 1);

  include 'include/init.php'; // to initiate session
// wwww
  include 'include/PhpCaptcha.class.php';

  //$oDb = &Db::inst();

  $sPath = dirname(__FILE__);

  $aFonts = array(
    $sPath.'/images/fonts/Verdana.ttf',
    $sPath.'/images/fonts/Vera.ttf',
  );

  $oVisualCaptcha = new PhpCaptcha($aFonts, 200, 60);
  $oVisualCaptcha->sOwnerText = App::conf('url.users');
  $oVisualCaptcha->SetCharSet("1,2,3,4,5,6,8,9,0");
  $oVisualCaptcha->Create();

  Db::inst('Close');
  ?>
