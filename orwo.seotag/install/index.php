<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Highloadblock\HighloadBlockTable;

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

    /**
     * [doInstall Основные этапы установки]
     */
    public function doInstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION, $step, $catalogIblockID, $filterSef;
        $step = IntVal($step);
        if ($step != 2) {
            $APPLICATION->IncludeAdminFile(Loc::getMessage("INSTALL_STEP1"), __DIR__."/step1.php");
        } else {
            if (empty($catalogIblockID) && empty($filterSef)) {
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
            Option::set($this->MODULE_ID, "filterSef", $filterSef);
            // Настройка формы редактирования
            $this->IblockStyle($installSeoIblock['seoIblockID']);
            $Installhighload = $this->highloadInstall();

            $eventManager = \Bitrix\Main\EventManager::getInstance();
            // Подмена URL
            $eventManager->registerEventHandler("main", "OnPageStart", $this->MODULE_ID, "\Orwo\Seotag\InitFilter", "searchRewrite");
            // Часть действий сбрасываем на обработчик обновления/добавления
            $eventManager->registerEventHandler("iblock", "OnBeforeIBlockElementUpdate", $this->MODULE_ID, "\Orwo\Seotag\CreateLinks", "beforetUpdateElement");
            $eventManager->registerEventHandler("iblock", "OnAfterIBlockElementAdd", $this->MODULE_ID, "\Orwo\Seotag\CreateLinks", "beforetUpdateElement");
            // Подмена Мета-тегов
            $eventManager->registerEventHandler("main", "OnEpilog", $this->MODULE_ID, '\Orwo\Seotag\InitFilter', "addMeta", 10);
            // Дабавляем на панель администрирования кнопки
            $eventManager->registerEventHandler("main", "OnBeforeProlog", $this->MODULE_ID, '\Orwo\Seotag\EventHelp', "panelButton");
            // Вкладка "Документация" и "ЧПУ"
            $eventManager->registerEventHandler("main", "OnAdminIBlockElementEdit", $this->MODULE_ID, '\Orwo\Seotag\EventHelp', "onInitHelpTab", 10);
            // Контроль табов
            $eventManager->registerEventHandler('main', 'OnAdminTabControlBegin', $this->MODULE_ID, '\Orwo\Seotag\EventHelp', 'controllerTabs');
            // Иконка инфоблока
            $eventManager->registerEventHandler('main', 'OnBuildGlobalMenu', $this->MODULE_ID, '\Orwo\Seotag\EventHelp', 'iconSeoIblock');
            // Кастомное свойство
            $eventManager->registerEventHandler('iblock', 'OnIBlockPropertyBuildList', $this->MODULE_ID, '\Orwo\Seotag\EventHelp', 'propFilterInit');
        }
    }

    /**
     * [doUninstall удаление]
     */
    public function doUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        if (Loader::includeModule('iblock')) {
            // Удаляем инфоблок
            $rsTypeIBlock = new CIBlockType;
            $rsTypeIBlock::Delete("orwo_seotag");
              $highloadID = \Bitrix\Main\Config\Option::get($this->MODULE_ID, "highloadID");
            ModuleManager::unRegisterModule($this->MODULE_ID);
              if (\Bitrix\Main\Loader::includeModule("highloadblock")) {
                $deleteHighloadBlock = \Bitrix\Highloadblock\HighloadBlockTable::delete($highloadID);
           }
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
                "ID"        => $iblocktype,
                "SECTIONS"  => "Y",
                "SORT"      => 1,
                "LANG"      => ["ru"=>["NAME"=>Loc::getMessage('NAME_IBLOCK_TYPE')]]
              );
            // Добавляем инфоблок
            if ($rsTypeIBlock->Add($arFields)) {
                $rsIBlock = new CIBlock;
                $arFields = array(
                  "NAME"           => Loc::getMessage('NAME_IBLOCK'),
                  "ACTIVE"         => "Y",
                  "IBLOCK_TYPE_ID" => $iblocktype,
                  "SITE_ID"        => $arSiteId
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
                      "USER_TYPE" => 'propFilterInit',
                      "MULTIPLE" => Y,
                      "MULTIPLE_CNT" => 1,
                      "IBLOCK_ID" => $iblockID,
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

                    $rsIBlockProperty = new CIBlockProperty;
                    $rsIBlockProperty->Add($propFilter);
                    $rsIBlockProperty->Add($propIdList);
                    $rsIBlockProperty->Add($propRedirect);
                    $rsIBlockProperty->Add($propSEF);
                    $rsIBlockProperty->Add($propSetTag);
                    $rsIBlockProperty->Add($propNameTag);
                    $rsIBlockProperty->Add($propSectionTag);
                    // и возвращаем массив ID нового инфоблока и каталога
                    $result['seoIblockID'] = $iblockID;
                    return $result;
                }
            }
        }
    }


    /**
     * [highloadInstall установка Highload Блока ссылок]
     */
    public function highloadInstall()
    {
        if (\Bitrix\Main\Loader::includeModule("highloadblock")) {
            $highloadTableName = 'seolinks';
            $addHighloadBlock = \Bitrix\Highloadblock\HighloadBlockTable::add(array(
              'NAME' => 'SeoLinks',
              'TABLE_NAME' => $highloadTableName
            ));
            if ($addHighloadBlock) {
                $highLoadBlockId = $addHighloadBlock->getId();
                // Записываем в настройки HL блок
                Option::set($this->MODULE_ID, "highloadID", $highLoadBlockId);
                Option::set($this->MODULE_ID, "highloadTableName", $highloadTableName);

                $highLoad_1 = array(
                    'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
                    'FIELD_NAME'        => 'UF_NEW',
                    'USER_TYPE_ID'      => 'string',
                    'XML_ID'            => '',
                    'SORT'              => 500,
                    'MULTIPLE'          => 'N',
                    'MANDATORY'         => 'Y',
                    'SHOW_FILTER'       => 'N',
                    'SHOW_IN_LIST'      => '',
                    'EDIT_IN_LIST'      => '',
                    'IS_SEARCHABLE'     => 'N',
                    'SETTINGS'          => array(
                        'DEFAULT_VALUE' => '',
                    ),
                    'EDIT_FORM_LABEL'   => array(
                        'ru'    => 'Новый URL',
                        'en'    => 'URL',
                    ),
                    'LIST_COLUMN_LABEL' => array(
                        'ru'    => 'Новый URL',
                        'en'    => 'URL',
                    ),
                    'LIST_FILTER_LABEL' => array(
                        'ru'    => 'Новый URL',
                    'en'    => 'URL',
                    ),
                    'ERROR_MESSAGE'     => array(
                        'ru'    => 'err',
                        'en'    => 'err',
                    ),
                    'HELP_MESSAGE'      => array(
                        'ru'    => '',
                        'en'    => '',
                    ),
                    'SETTINGS' => ['SIZE' => '60']
                );

                $highLoad_2 = array(
                  'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
                  'FIELD_NAME'        => 'UF_OLD',
                  'USER_TYPE_ID'      => 'string',
                  'XML_ID'            => '',
                  'SORT'              => 500,
                  'MULTIPLE'          => 'N',
                  'MANDATORY'         => 'Y',
                  'SHOW_FILTER'       => 'N',
                  'SHOW_IN_LIST'      => '',
                  'EDIT_IN_LIST'      => '',
                  'IS_SEARCHABLE'     => 'N',
                  'SETTINGS'          => array(
                      'DEFAULT_VALUE' => '',
                  ),
                  'EDIT_FORM_LABEL'   => array(
                      'ru'    => 'Оригинальный URL',
                      'en'    => 'URL',
                  ),
                  'LIST_COLUMN_LABEL' => array(
                      'ru'    => 'Оригинальный URL',
                      'en'    => 'URL',
                  ),
                  'LIST_FILTER_LABEL' => array(
                      'ru'    => 'Оригинальный URL',
                  'en'    => 'URL',
                  ),
                  'ERROR_MESSAGE'     => array(
                      'ru'    => 'err',
                      'en'    => 'err',
                  ),
                  'HELP_MESSAGE'      => array(
                      'ru'    => '',
                      'en'    => '',
                  ),
                  'SETTINGS' => ['SIZE' => '60']
              );

                $highLoad_3 = array(
                  'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
                  'FIELD_NAME'        => 'UF_REDIRECT',
                  'USER_TYPE_ID'      => 'boolean',
                  'XML_ID'            => '',
                  'SORT'              => 500,
                  'MULTIPLE'          => 'N',
                  'MANDATORY'         => 'Y',
                  'SHOW_FILTER'       => 'N',
                  'SHOW_IN_LIST'      => '',
                  'EDIT_IN_LIST'      => '',
                  'IS_SEARCHABLE'     => 'N',
                  'SETTINGS'          => array(
                      'DEFAULT_VALUE' => '',
                  ),
                  'EDIT_FORM_LABEL'   => array(
                      'ru'    => 'Редирект на новую ссылку',
                      'en'    => 'REDIRECT',
                  ),
                  'LIST_COLUMN_LABEL' => array(
                      'ru'    => 'Редирект на новую ссылку',
                      'en'    => 'REDIRECT',
                  ),
                  'LIST_FILTER_LABEL' => array(
                      'ru'    => 'Редирект на новую ссылку',
                      'en'    => 'REDIRECT',
                  ),
                  'ERROR_MESSAGE'     => array(
                      'ru'    => 'err',
                      'en'    => 'err',
                  ),
                  'HELP_MESSAGE'      => array(
                      'ru'    => '',
                      'en'    => '',
                  ),
              );

                $highLoad_4 = array(
                  'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
                  'FIELD_NAME'        => 'UF_ID',
                  'USER_TYPE_ID'      => 'integer',
                  'XML_ID'            => '',
                  'SORT'              => 500,
                  'MULTIPLE'          => 'N',
                  'MANDATORY'         => 'Y',
                  'SHOW_FILTER'       => 'N',
                  'SHOW_IN_LIST'      => '',
                  'EDIT_IN_LIST'      => '',
                  'IS_SEARCHABLE'     => 'N',
                  'SETTINGS'          => array(
                      'DEFAULT_VALUE' => '',
                  ),
                  'EDIT_FORM_LABEL'   => array(
                      'ru'    => 'ID SEO элемента',
                      'en'    => 'ID',
                  ),
                  'LIST_COLUMN_LABEL' => array(
                      'ru'    => 'ID SEO элемента',
                      'en'    => 'ID',
                  ),
                  'LIST_FILTER_LABEL' => array(
                      'ru'    => 'ID SEO элемента',
                      'en'    => 'ID',
                  ),
                  'ERROR_MESSAGE'     => array(
                      'ru'    => 'err',
                      'en'    => 'err',
                  ),
                  'HELP_MESSAGE'      => array(
                      'ru'    => '',
                      'en'    => '',
                  ),
              );

                $highLoad_5 = array(
                  'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
                  'FIELD_NAME'        => 'UF_ACTIVE',
                  'USER_TYPE_ID'      => 'boolean',
                  'XML_ID'            => '',
                  'SORT'              => 500,
                  'MULTIPLE'          => 'N',
                  'MANDATORY'         => 'Y',
                  'SHOW_FILTER'       => 'N',
                  'SHOW_IN_LIST'      => '',
                  'EDIT_IN_LIST'      => '',
                  'IS_SEARCHABLE'     => 'N',
                  'SETTINGS'          => array(
                      'DEFAULT_VALUE' => true,
                  ),
                  'EDIT_FORM_LABEL'   => array(
                      'ru'    => 'Активность',
                      'en'    => 'Active',
                  ),
                  'LIST_COLUMN_LABEL' => array(
                      'ru'    => 'Активность',
                      'en'    => 'Active',
                  ),
                  'LIST_FILTER_LABEL' => array(
                      'ru'    => 'Активность',
                      'en'    => 'Active',
                  ),
                  'ERROR_MESSAGE'     => array(
                      'ru'    => 'err',
                      'en'    => 'err',
                  ),
                  'HELP_MESSAGE'      => array(
                      'ru'    => '',
                      'en'    => '',
                  ),
              );
                $highLoad_6 = array(
                'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
                'FIELD_NAME'        => 'UF_SECTION',
                'USER_TYPE_ID'      => 'string',
                'XML_ID'            => '',
                'SORT'              => 500,
                'MULTIPLE'          => 'N',
                'MANDATORY'         => 'Y',
                'SHOW_FILTER'       => 'N',
                'SHOW_IN_LIST'      => '',
                'EDIT_IN_LIST'      => '',
                'IS_SEARCHABLE'     => 'N',
                'SETTINGS'          => array(
                    'DEFAULT_VALUE' => '',
                ),
                'EDIT_FORM_LABEL'   => array(
                    'ru'    => 'ID раздела',
                    'en'    => 'URL',
                ),
                'LIST_COLUMN_LABEL' => array(
                    'ru'    => 'ID раздела',
                    'en'    => 'URL',
                ),
                'LIST_FILTER_LABEL' => array(
                    'ru'    => 'ID раздела',
                'en'    => 'URL',
                ),
                'ERROR_MESSAGE'     => array(
                    'ru'    => 'err',
                    'en'    => 'err',
                ),
                'HELP_MESSAGE'      => array(
                    'ru'    => '',
                    'en'    => '',
                ),
            );

                $highLoad_7 = array(
              'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
              'FIELD_NAME'        => 'UF_TAG',
              'USER_TYPE_ID'      => 'string',
              'XML_ID'            => '',
              'SORT'              => 500,
              'MULTIPLE'          => 'N',
              'MANDATORY'         => 'Y',
              'SHOW_FILTER'       => 'N',
              'SHOW_IN_LIST'      => '',
              'EDIT_IN_LIST'      => '',
              'IS_SEARCHABLE'     => 'N',
              'SETTINGS'          => array(
                  'DEFAULT_VALUE' => '',
              ),
              'EDIT_FORM_LABEL'   => array(
                  'ru'    => 'Имя тега',
                  'en'    => 'URL',
              ),
              'LIST_COLUMN_LABEL' => array(
                  'ru'    => 'Имя тега',
                  'en'    => 'URL',
              ),
              'LIST_FILTER_LABEL' => array(
                  'ru'    => 'Имя тега',
                  'en'    => 'URL',
              ),
              'ERROR_MESSAGE'     => array(
                  'ru'    => 'err',
                  'en'    => 'err',
              ),
              'HELP_MESSAGE'      => array(
                  'ru'    => '',
                  'en'    => '',
              ),
          );

                $highLoad_8 = array(
            'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
            'FIELD_NAME'        => 'UF_NOT_UPDATE',
            'USER_TYPE_ID'      => 'boolean',
            'XML_ID'            => '',
            'SORT'              => 500,
            'MULTIPLE'          => 'N',
            'MANDATORY'         => 'Y',
            'SHOW_FILTER'       => 'N',
            'SHOW_IN_LIST'      => '',
            'EDIT_IN_LIST'      => '',
            'IS_SEARCHABLE'     => 'N',
            'SETTINGS'          => array(
                'DEFAULT_VALUE' => false,
            ),
            'EDIT_FORM_LABEL'   => array(
                'ru'    => 'Не перезаписывать',
                'en'    => 'Not update',
            ),
            'LIST_COLUMN_LABEL' => array(
                'ru'    => 'Не перезаписывать',
                'en'    => 'Not update',
            ),
            'LIST_FILTER_LABEL' => array(
                'ru'    => 'Не перезаписывать',
                'en'    => 'Not update',
            ),
            'ERROR_MESSAGE'     => array(
                'ru'    => 'err',
                'en'    => 'err',
            ),
            'HELP_MESSAGE'      => array(
                'ru'    => 'Ссылка не будет перезаписана при обновлении элемента условий',
                'en'    => '',
            ),
        );
            $highLoad_9 = array(
              'ENTITY_ID'         => 'HLBLOCK_'.$highLoadBlockId,
              'FIELD_NAME'        => 'UF_TOP',
              'USER_TYPE_ID'      => 'boolean',
              'XML_ID'            => '',
              'SORT'              => 500,
              'MULTIPLE'          => 'N',
              'MANDATORY'         => 'Y',
              'SHOW_FILTER'       => 'N',
              'SHOW_IN_LIST'      => '',
              'EDIT_IN_LIST'      => '',
              'IS_SEARCHABLE'     => 'N',
              'SETTINGS'          => array(
                  'DEFAULT_VALUE' => false,
              ),
              'EDIT_FORM_LABEL'   => array(
                  'ru'    => 'Популярный',
                  'en'    => 'Popular',
              ),
              'LIST_COLUMN_LABEL' => array(
                  'ru'    => 'Популярный',
                  'en'    => 'Popular',
              ),
              'LIST_FILTER_LABEL' => array(
                  'ru'    => 'Популярный',
                  'en'    => 'Popular',
              ),
              'ERROR_MESSAGE'     => array(
                  'ru'    => 'err',
                  'en'    => 'err',
              ),
              'HELP_MESSAGE'      => array(
                  'ru'    => '',
                  'en'    => '',
              ),
          );


                $oUserTypeEntity  = new \CUserTypeEntity();
                $userTypeId = $oUserTypeEntity->Add($highLoad_1);
                $userTypeId = $oUserTypeEntity->Add($highLoad_2);
                $userTypeId = $oUserTypeEntity->Add($highLoad_3);
                $userTypeId = $oUserTypeEntity->Add($highLoad_4);
                $userTypeId = $oUserTypeEntity->Add($highLoad_5);
                $userTypeId = $oUserTypeEntity->Add($highLoad_6);
                $userTypeId = $oUserTypeEntity->Add($highLoad_7);
                $userTypeId = $oUserTypeEntity->Add($highLoad_8);
                $userTypeId = $oUserTypeEntity->Add($highLoad_9);
            }
        }
    }


    /**
     * [IblockStyle перемещаем свойства в инфоблоке + сериалиазция от битрикс]
     */
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
