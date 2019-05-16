<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
if (!check_bitrix_sessid()) {
    return;
}
Loc::loadMessages(__FILE__);
\Bitrix\Main\UI\Extension::load("ui.hint");
?>

<script>
BX.ready(function() {
    BX.UI.Hint.init(BX('my-container'));
})
</script>
  <form action="<?echo $APPLICATION->GetCurPage();?>">
  <?echo bitrix_sessid_post(); ?>
  <input type="hidden" name="step" value="2">
  <input type="hidden" name="id" value="orwo.seotag">
  <input type="hidden" name="install" value="Y">

  <!--  -->
  <select name="catalogIblockID" required >
  <?if (Loader::includeModule('iblock')) {?>
      <?php $resIblock = CIBlock::GetList([], array('ACTIVE'=>'Y'), false); ?>
        <?while ($arIblock = $resIblock->Fetch()) {?>
          	<option value="<?=$arIblock['ID']?>"> <?=$arIblock['NAME']?> (ID: <?=$arIblock['ID']?>)</options>
        <?}?>
  <?}?>
  </select>
  <span data-hint="<?=Loc::getMessage("INSTALL_STEP1_TEXT")?>"></span>
  <input type="submit" name="" value="<?=Loc::getMessage("INSTALL_SUBMIT"); ?>">
  </form>
