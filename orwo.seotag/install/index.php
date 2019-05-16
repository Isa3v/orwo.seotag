<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class orwo_seotag extends CModule
{
    public function __construct()
    {
        $this->MODULE_ID = "orwo.seotag";
        $arModuleVersion = array();
        include(__DIR__."/version.php");
        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
        $this->MODULE_GROUP_RIGHTS = "N";
        $this->MODULE_NAME = Loc::getMessage('MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MODULE_DESCRIPTION');
    }

    public function doInstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION, $step, $catalogIblockID;
        $step = IntVal($step);
        if ($step != 2) {
            $APPLICATION->IncludeAdminFile(Loc::getMessage("INSTALL_STEP1"), __DIR__."/step1.php");
        } else {
            if (empty($catalogIblockID)) {
                $step = 1;
                $APPLICATION->IncludeAdminFile(Loc::getMessage("INSTALL_STEP1"), __DIR__."/step1.php");
                return;
            }
            $this->InstallFiles();
            $installSeoIblock = $this->IblockInstall($catalogIblockID);

            ModuleManager::registerModule($this->MODULE_ID);
            // Устанавливаем опции модуля
            Option::set($this->MODULE_ID, "seoIblockID", $installSeoIblock['seoIblockID']);
            Option::set($this->MODULE_ID, "catalogIblockID", $catalogIblockID);
            // Настройка формы редактирования
            $this->IblockStyle($installSeoIblock['seoIblockID']);
            $eventManager = \Bitrix\Main\EventManager::getInstance();
            // Подмена URL
            $eventManager->registerEventHandler("main", "OnPageStart", $this->MODULE_ID, "\Orwo\Seotag\InitFilter", "searchRewrite");
            // Часть действий сбрасываем на обработчик обновления/добавления
            $eventManager->registerEventHandler("iblock", "OnBeforeIBlockElementUpdate", $this->MODULE_ID, "\Orwo\Seotag\InitFilter", "beforetUpdateElement");
            $eventManager->registerEventHandler("iblock", "OnBeforeIBlockElementAdd", $this->MODULE_ID, "\Orwo\Seotag\InitFilter", "beforetUpdateElement");
            // Подмена Мета-тегов
            $eventManager->registerEventHandler("main", "OnEpilog", $this->MODULE_ID, '\Orwo\Seotag\InitFilter', "addMeta");

            // Дабавляем на панель администрирования кнопки
            $eventManager->registerEventHandler("main", "OnBeforeProlog", $this->MODULE_ID, '\Orwo\Seotag\EventHelp', "panelButton");
            // Вкладка "Документация"
            $eventManager->registerEventHandler("main", "OnAdminIBlockElementEdit", $this->MODULE_ID, '\Orwo\Seotag\EventHelp', "onInitHelpTab");
        }
    }

    public function doUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        if (Loader::includeModule('iblock')) {
            // Удаляем инфоблок
            $rsTypeIBlock = new CIBlockType;
            $rsTypeIBlock::Delete("orwo_seotag");
            ModuleManager::unRegisterModule($this->MODULE_ID);
        }
    }

    public function InstallFiles()
    {
        CopyDirFiles(__DIR__."/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
        return true;
    }


    // Функция добавления инфоблока и свойств
    public function IblockInstall($catalogIblockID)
    {
        // Выбираем все ID сайтов для активации инфоблока
        $rsSites = \Bitrix\Main\SiteTable::getList()->fetchAll();
        foreach ($rsSites as $key => $value) {
            $arSiteId[] = $value['LID'];
        }
        if (Loader::includeModule('iblock')) {
            // Тип инфоблока
            $iblocktype = "orwo_seotag";
            // Добавляем тип инфоблока
            $rsTypeIBlock = new CIBlockType;
            $arFields = array(
                "ID"=>$iblocktype,
                "SECTIONS"=>"Y",
                "LANG"=> ["ru"=>["NAME"=>Loc::getMessage('NAME_IBLOCK_TYPE')]]
              );
            // Добавляем инфоблок
            if ($rsTypeIBlock->Add($arFields)) {
                $rsIBlock = new CIBlock;
                $arFields = array(
                  "NAME"=> Loc::getMessage('NAME_IBLOCK'),
                  "ACTIVE" => "Y",
                  "IBLOCK_TYPE_ID" => $iblocktype,
                  "SITE_ID" => $arSiteId
                );
                if ($iblockID = $rsIBlock->Add($arFields)) {
                    // Прописываем свойства
                    $propFilter = array(
                      "SORT" => 10,
                      "NAME" => Loc::getMessage('propFilter_NAME'),
                      "CODE" => "PROP_FILTER",
                      "HINT" => Loc::getMessage('propFilter_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "S",
                      "WITH_DESCRIPTION" => "Y",
                      "IBLOCK_ID" => $iblockID,
                    );
                    $propSlider = array(
                      "SORT" => 20,
                      "NAME" => Loc::getMessage('propSlider_NAME'),
                      "CODE" => "PROP_SLIDER",
                      "HINT" => Loc::getMessage('propSlider_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "L",
                      "LIST_TYPE" => "C",
                      "IBLOCK_ID" => $iblockID,
                      "VALUES" => array(array(
                          "VALUE" => "Y",
                          "DEF" => "N",
                        ))
                    );
                    $propIdList = array(
                      "SORT" => 30,
                      "NAME" => Loc::getMessage('propIdList_NAME'),
                      "CODE" => "SET_ID_LIST",
                      "HINT" => Loc::getMessage('propIdList_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "G",
                      "IBLOCK_ID" => $iblockID,
                      "LINK_IBLOCK_ID" => $catalogIblockID,
                      "MULTIPLE" => Y,
                      "MULTIPLE_CNT" => 10,
                      "COL_COUNT" => 30,
                    );
                    $propRedirect = array(
                      "SORT" => 40,
                      "NAME" => Loc::getMessage('propRedirect_NAME'),
                      "CODE" => "REDIRECT",
                      "HINT" => Loc::getMessage('propRedirect_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "L",
                      "LIST_TYPE" => "C",
                      "IBLOCK_ID" => $iblockID,
                      "VALUES" => array(array(
                          "VALUE" => "Y",
                          "DEF" => "N",
                        ))
                    );
                    $propSEF = array(
                      "SORT" => 50,
                      "NAME" => Loc::getMessage('propSEF_NAME'),
                      "CODE" => "NEW_SEF",
                      "HINT" => Loc::getMessage('propSEF_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "S",
                      "IBLOCK_ID" => $iblockID,
                      "DEFAULT_VALUE" => '#SECTION_CODE_PATH#/filter/{FILTER_VALUE}/'
                    );
                    $propSetTag = array(
                      "SORT" => 60,
                      "NAME" => Loc::getMessage('propSetTag_NAME'),
                      "CODE" => "SET_TAG",
                      "HINT" => Loc::getMessage('propSetTag_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "L",
                      "LIST_TYPE" => "C",
                      "IBLOCK_ID" => $iblockID,
                      "VALUES" => array(array(
                          "VALUE" => "Y",
                          "DEF" => "Y",
                        ))
                    );
                    $propNameTag = array(
                      "SORT" => 70,
                      "NAME" => Loc::getMessage('propNameTag_NAME'),
                      "CODE" => "NAME_TAG",
                      "HINT" => Loc::getMessage('propNameTag_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "S",
                      "IBLOCK_ID" => $iblockID,
                      "DEFAULT_VALUE" => '{FILTER_VALUE}'
                    );
                    $propSectionTag = array(
                      "SORT" => 80,
                      "NAME" => Loc::getMessage('propSectionTag_NAME'),
                      "CODE" => "SECTION_TAG",
                      "HINT" => Loc::getMessage('propSectionTag_HINT'),
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "S",
                      "IBLOCK_ID" => $iblockID
                    );
                    $propResult = array(
                      "SORT" => 90,
                      "NAME" => Loc::getMessage('propResult_NAME'),
                      "HINT" => Loc::getMessage('propResult_HINT'),
                      "CODE" => "RESULT",
                      "ACTIVE" => "Y",
                      "PROPERTY_TYPE" => "S",
                      "MULTIPLE" => Y,
                      "MULTIPLE_CNT" => 1,
                      "IBLOCK_ID" => $iblockID
                    );

                    $rsIBlockProperty = new CIBlockProperty;
                    $rsIBlockProperty->Add($propFilter);
                    $rsIBlockProperty->Add($propSlider);
                    $rsIBlockProperty->Add($propIdList);
                    $rsIBlockProperty->Add($propRedirect);
                    $rsIBlockProperty->Add($propSEF);
                    $rsIBlockProperty->Add($propSetTag);
                    $rsIBlockProperty->Add($propNameTag);
                    $rsIBlockProperty->Add($propSectionTag);
                    $rsIBlockProperty->Add($propResult);
                    // и возвращаем массив ID нового инфоблока и каталога
                    $result['seoIblockID'] = $iblockID;
                    return $result;
                }
            }
        }
    }


    // Функция стилизации и перемещения элементов формы редактирования
    public function IblockStyle($IBLOCK_ID)
    {
        // Вкладки и свойства
        $arFormSettings = array(
          array(
              array("edit1", "Создания правила"), // Название вкладки
              array("NAME", "*Имя правила"), // Свойство со звездочкой - помечается как обязательное
              array("ACTIVE", "Активность"),
              array("SORT", "Сортировка"),
              array("empty", "Настройка ЧПУ"),
          ),
      );
        // Закидываем свойства
        $rsProperty = CIBlockProperty::GetList(['sort' => 'asc'], ['IBLOCK_ID' => $IBLOCK_ID]);
        while ($prop = $rsProperty->Fetch()) {
            $test[] = $prop;
            if ($prop['CODE'] == 'RESULT') {
                continue;
            }
            $arFormSettings[0][] = array('PROPERTY_'.$prop['ID'], $prop['NAME']);
            if ($prop['CODE'] == 'NEW_SEF') {
                $arFormSettings[0][] = array("empty2", "Настройка тегов");
            }
        }
        $arFormSettings[0][] = array("empty3", "Настройка мета-тегов");

        // Сео вкладку перекидываем сюда
        $arFormSettings[0][] = array("IPROPERTY_TEMPLATES_ELEMENT_META_TITLE", "Шаблон META TITLE");
        $arFormSettings[0][] = array("IPROPERTY_TEMPLATES_ELEMENT_META_DESCRIPTION", "Шаблон META DESCRIPTION");
        $arFormSettings[0][] = array("IPROPERTY_TEMPLATES_ELEMENT_PAGE_TITLE", "Заголовок элемента");

        // Сериализация
        $arFormFields = array();
        foreach ($arFormSettings as $key => $arFormFields) {
            $arFormItems = array();
            foreach ($arFormFields as $strFormItem) {
                $arFormItems[] = implode('--#--', $strFormItem);
            }
            $arStrFields[] = implode('--,--', $arFormItems);
        }
        $arSettings = array("tabs" => implode('--;--', $arStrFields));
        // Применяем настройки для всех пользователей для данного инфоблока
        $rez = CUserOptions::SetOption("form", "form_element_".$IBLOCK_ID, $arSettings, $bCommon=true, $userId=false);
    }
}
