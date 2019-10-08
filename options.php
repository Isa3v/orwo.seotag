<?php
$module_id = "orwo.seotag";
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);
\Bitrix\Main\UI\Extension::load("ui.hint");
\Bitrix\Main\UI\Extension::load("ui.notification");
$moduleAccess = $APPLICATION->GetGroupRight($module_id);
if ($moduleAccess >= "W"):
    Loader::includeModule($module_id);
    IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/options.php");
    $aTabs = array(
        array("DIV" => "edit1", "TAB" => Loc::getMessage("I_TAB_EDIT1"), "ICON" => "", "TITLE" => Loc::getMessage("I_TAB_EDIT1")),
        array("DIV" => "edit2", "TAB" => Loc::getMessage("MAIN_TAB_RIGHTS"), "ICON" => "", "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_RIGHTS"))
    );
    $tabControl = new CAdminTabControl("tabControl", $aTabs);

    /* [SAVE OPTIONS] */
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $_REQUEST["Update"] != "" && check_bitrix_sessid()) {
        ob_start();
        require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
        ob_end_clean();
        if (!empty($_REQUEST['seoIblockID'])) {
            Option::set($module_id, "seoIblockID", $_REQUEST['seoIblockID']);
        }
        if (!empty($_REQUEST['catalogIblockID'])) {
            Option::set($module_id, "catalogIblockID", $_REQUEST['catalogIblockID']);
        }

        if (strlen($_REQUEST["back_url_settings"]) > 0) {
            LocalRedirect($_REQUEST["back_url_settings"]);
        }
        LocalRedirect($APPLICATION->GetCurPage()."?mid=".urlencode($module_id)."&lang=".urlencode(LANGUAGE_ID)."&".$tabControl->ActiveTabParam());
    }

    /* [GENERATE SITEMAP] */
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $_REQUEST["generate_sitemap"] != "" && check_bitrix_sessid()) {
        ob_start();
        require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
        ob_end_clean();
        print_r($_REQUEST);
				if($_REQUEST['filePath'] != '' && !empty($_REQUEST['filePath'])){
					$sitemap = \Orwo\Seotag\Sitemap::get($_REQUEST['filePath']);
				}else{
					$sitemap =\Orwo\Seotag\Sitemap::get();
				}
				if($sitemap == true){
					 LocalRedirect($APPLICATION->GetCurPage()."?mid=".urlencode($module_id)."&lang=".urlencode(LANGUAGE_ID)."&".$tabControl->ActiveTabParam().'&successSitemap=y');
				}else{
					LocalRedirect($APPLICATION->GetCurPage()."?mid=".urlencode($module_id)."&lang=".urlencode(LANGUAGE_ID)."&".$tabControl->ActiveTabParam().'&successSitemap=n');
				}
    }
    Asset::getInstance()->addJs("/bitrix/js/main/core/core.js");
    $tabControl->Begin();
    ?>

	<form method="post" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&amp;lang=<?=LANGUAGE_ID?>">
	<?$tabControl->BeginNextTab();?>
	<?if($_REQUEST['successSitemap'] == 'y'){?>
	<script>
	BX.UI.Notification.Center.notify({
		content: "<?=Loc::getMessage("successSitemapY")?>",
		position: "top-center"
	});
	</script>
	<?}elseif($_REQUEST['successSitemap'] == 'n'){?>
		<script>
		BX.UI.Notification.Center.notify({
			content: "<?=Loc::getMessage("successSitemapN")?>",
			position: "top-center"
		});
		</script>
	<?}?>
	<script>
	BX.ready(function() {
	    BX.UI.Hint.init(BX('my-container'));
	})
	</script>
	<?/* [IBLOCK OPTIONS] */?>
	<span><?=Loc::getMessage("OR_IBLOCK_OPTIONS")?></span>
	<span data-hint="<?=Loc::getMessage("OR_IBLOCK_OPTIONS_HINT")?>"></span>
	<?php $catalogIblockID = \Bitrix\Main\Config\Option::get($module_id, "catalogIblockID");?>
	<select name="catalogIblockID" required >
	<?if (Loader::includeModule('iblock')) {
        ?>
			<?php $resIblock = CIBlock::GetList([], array('ACTIVE'=>'Y'), false); ?>
				<?while ($arIblock = $resIblock->Fetch()) {
            if ($catalogIblockID == $arIblock['ID']) {
                ?>
            <option selected value="<?=$arIblock['ID']?>"> <?=$arIblock['NAME']?> (ID: <?=$arIblock['ID']?>)</options>
            <?php
            } else {
                ?>
						<option value="<?=$arIblock['ID']?>"> <?=$arIblock['NAME']?> (ID: <?=$arIblock['ID']?>)</options>
				<?php
            }
        } ?>
	<?php
    }?>
	</select>
	<br>
	<?/* [end IBLOCK OPTIONS] */?>

	<?/* [IBLOCK SEO OPTIONS] */?>
	<span><?=Loc::getMessage("OR_SEO_IBLOCK_OPTIONS")?></span>
	<span data-hint="<?=Loc::getMessage("OR_SEO_IBLOCK_OPTIONS_HINT")?>"></span>
	<?php $seoIblockID = \Bitrix\Main\Config\Option::get($module_id, "seoIblockID");?>
	<select name="seoIblockID" required >
	<?if (Loader::includeModule('iblock')) {
        ?>
			<?php $resIblock = CIBlock::GetList([], array('ACTIVE'=>'Y'), false); ?>
				<?while ($arIblock = $resIblock->Fetch()) {
            if ($seoIblockID == $arIblock['ID']) {
                ?>
            <option selected value="<?=$arIblock['ID']?>"> <?=$arIblock['NAME']?> (ID: <?=$arIblock['ID']?>)</options>
            <?php
            } else {
                ?>
						<option value="<?=$arIblock['ID']?>"> <?=$arIblock['NAME']?> (ID: <?=$arIblock['ID']?>)</options>
				<?php
            }
        } ?>
	<?php
    }?>
	</select>
	<br>
	<?/* [end IBLOCK SEO OPTIONS] */?>



	<?/* [SITEMAP GENERATE] */?>
	<span><?=Loc::getMessage("NEW_SITEMAP_CREATE_PATH")?></span>
	<span data-hint="<?=Loc::getMessage("NEW_SITEMAP_CREATE_PATH_HINT")?>"></span>
	<input type="text" name="filePath" size="70" placeholder="<?=$_SERVER['DOCUMENT_ROOT'].'/sitemap.xml'?>">
	<input type="submit" name="generate_sitemap" value="<?=Loc::getMessage("NEW_SITEMAP_CREATE")?>" title="<?=Loc::getMessage("NEW_SITEMAP_CREATE")?>" class="adm-btn-save">
	<span data-hint="<?=Loc::getMessage("NEW_SITEMAP_CREATE_HINT")?>"></span>
	<?/* [end SITEMAP GENERATE] */?>

	<?$tabControl->BeginNextTab();?>
	<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");?>

	<?$tabControl->Buttons();?>
		<input type="submit" name="Update" value="<?=Loc::getMessage("MAIN_SAVE")?>" title="<?=Loc::getMessage("MAIN_OPT_SAVE_TITLE")?>" class="adm-btn-save">
		<?=bitrix_sessid_post();?>
		<?if (strlen($_REQUEST["back_url_settings"]) > 0):?>
			<input type="button" name="Cancel" value="<?=Loc::getMessage("MAIN_OPT_CANCEL")?>" onclick="window.location='<?echo htmlspecialcharsbx(CUtil::addslashes($_REQUEST["back_url_settings"]))?>'">
			<input type="hidden" name="back_url_settings" value="<?=htmlspecialcharsbx($_REQUEST["back_url_settings"])?>">
		<?endif;?>
	<?$tabControl->End();?>
	</form>

<?endif;?>
