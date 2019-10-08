<?php

namespace Orwo\Seotag;

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Uri;
use Bitrix\Main\Context;

class EventHelp extends \Orwo\Seotag\InitFilter
{
    /**
     * [panelButton добавление кнопки редактирвания на панель]
     */
    public function panelButton()
    {
        global $APPLICATION, $USER;
        if (!$USER->IsAdmin()) {
            return false;
        }
        $request = Context::getCurrent()->getRequest();
        $uri = new Uri($request->getRequestUri());
        $curPage = $uri->getPath();

        // Получаем данные сео инфоблока
        $originalCurPage = parent::getLink($curPage, true);
        // Если найден ключ с ссылкой OLD настоящая ссылка всегда идет в curpage
        if (!empty($originalCurPage)) {
            // Выбираем текущую ссылку т.к curpage у нас заменился ранее, то проверяем обе ссылки
            $editID = $originalCurPage["UF_ID"];
            $editIblock = parent::seoIblockID();
            Loader::includeModule("iblock");
            $arButtons = \CIBlock::GetPanelButtons(
                $editIblock,
                $editID,
                0,
                array("SECTION_BUTTONS" => false, "SESSID" => false)
            );
            if ($arButtons["edit"]["edit_element"]["ACTION"]) {
                $APPLICATION->AddPanelButton(
                    array(
                        "ID" => "3001", //определяет уникальность кнопки
                        "TYPE" => "BIG",
                        "TEXT" => "Редактировать SEO — фильтр",
                        "MAIN_SORT" => 3000, //индекс сортировки для групп кнопок
                        "SORT" => 1, //сортировка внутри группы
                        "HREF" => $arButtons["edit"]["edit_element"]["ACTION"], //или javascript:MyJSFunction())
                        "ICON" => "bx-panel-site-wizard-icon", //название CSS-класса с иконкой кнопки
                    ),
                    $bReplace = false //заменить существующую кнопку?
                );
            }
        }
    }


    public function onInitHelpTab($arFields)
    {
        if ($arFields['IBLOCK']['ID'] == parent::seoIblockID()) {
            return array(
                "TABSET" => "seoFilter",
                "GetTabs" => array(__CLASS__, "getHelpTabs"),
                "ShowTab" => array(__CLASS__, "showHelpTab"),
            );
        }
    }

    public function getHelpTabs($arArgs)
    {
        return [["DIV" => "elementsTab", "TAB" => "Настройки ссылок", 'TITLE' => "Список сгенерированных ссылок"], ["DIV" => "helpTab", "TAB" => "Документация"]];
    }

    public function showHelpTab($divName, $arArgs, $bVarsFromForm)
    {
        $seoHighloadID = \Orwo\Seotag\InitFilter::seoHighloadID();
        // Создаем сущность для работы с блоком:
        \Bitrix\Main\UI\Extension::load("ui.alerts");
        if (\Bitrix\Main\Loader::includeModule('highloadblock')) {
            $arHLBlock = \Bitrix\Highloadblock\HighloadBlockTable::getById($seoHighloadID)->fetch();
            $obEntity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arHLBlock);
            $strEntityDataClass = $obEntity->getDataClass();
            $rsMap = $strEntityDataClass;
            //Получение списка:
            $rsData = $strEntityDataClass::getList(array(
                'select' => array('*'),
                'filter' => array('UF_ID' => $arArgs['ID'])
            ));
            $columnFields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields('HLBLOCK_' . $seoHighloadID, 0, LANGUAGE_ID);
        }

        if ($divName == "helpTab") {
            echo '<tr><td style="padding: 5px;"><script src="//gist.github.com/Isa3v/e20394135dac1a5925e61cfd75c81cfa.js">
            </script><script>document.querySelector(".gist-meta").innerHTML = "Original Works";</script></td></tr>';
        }
        if ($divName == "elementsTab") {
            if ($arArgs['ID'] != 0) {
                $arHeaders[] = ["id" => 'ID',  "content" => 'ID',  "default" => "true"];
                foreach ($columnFields as $key => $value) {
                    $arHeaders[] = ["id" => $key, "content" => $value['EDIT_FORM_LABEL'], "default" => "true"];
                }
                $lAdmin = new \CAdminList('hlsef');
                $lAdmin->AddHeaders($arHeaders);

                while ($arHighloadItem = $rsData->Fetch()) {
                    $arActions = array();
                    $arHighloadItem['UF_OLD'] = urldecode($arHighloadItem['UF_OLD']);
                    $row = &$lAdmin->AddRow($arHighloadItem['ID'], $arHighloadItem);
                    $arActions[] = array(
                        "ICON" => "edit",
                        "DEFAULT" => true,
                        "TEXT" => 'Изменить',
                        "ACTION" => "(new BX.CAdminDialog({'content_url':'/bitrix/admin/highloadblock_row_edit.php?ENTITY_ID=" . $seoHighloadID . "&ID=" . $arHighloadItem['ID'] . "&lang=ru&mode=list&bxpublic=Y'})).Show();"
                    );
                    $arActions[] = array(
                        "TEXT" => 'Открыть',
                        "ACTION" => "BX.adminPanel.Redirect([], 'highloadblock_row_edit.php?&ENTITY_ID=" . $seoHighloadID . "&ID=" . $arHighloadItem['ID'] . "', event)"
                    );
                    $row->AddActions($arActions);
                }
                echo '<tr><td>';
                echo '<div class="ui-alert ui-alert-icon-info ui-alert-xs">
                    <span class="ui-alert-message"><strong>Обновления значений!</strong> Для того, чтобы значения ссылки не перезаписалась используйте свойство <i>"Не перезаписывать"</i> в редактировании ссылки</span>
                  </div>';

                $lAdmin->DisplayList();
                echo '<br>
            <div class="ui-alert ui-alert-warning ui-alert-icon-danger ui-alert-xs">
              <span class="ui-alert-message">
              <strong>Перезапись ссылок:</strong>
              <input type="checkbox" name="recteate" value="1" id="recteate" class="adm-designed-checkbox">
              <label class="adm-designed-checkbox-label" for="recteate" title=""></label>
              <span> Если активировать чекбокс, все ссылки, за исключением ссылок с параметром <i>"Не перезаписывать"</i>, будут удалены и сгенерированны заново.</span>
            </div>';
                echo '</td></td>';
            }
        }
    }

    /**
     * [propFilterInit Кастомное свойства выбора свойства]
     */
    public function propFilterInit()
    {
        return array(
            'PROPERTY_TYPE'           => 'S',
            'USER_TYPE'               => 'propFilterInit',
            'DESCRIPTION'             => 'Условия свойств (SEO filter)',
            'GetPropertyFieldHtml'    => array(__CLASS__, 'getHtmlpropFilter'),

        );
    }
    /**
     * [getHtmlpropFilter Внешний вид кастомного свойства]
     */
    public function getHtmlpropFilter($arProperty, $value, $strHTMLControlName)
    {
        $html = '<div style="margin-bottom: .5rem;">';
        $html .= '<input id="seo-inp-val" class="adm-input seo-inp-val" list="seo-inp-val-select" placeholder="Код свойства"  name="' . $strHTMLControlName['VALUE'] . '" value="' . $value['VALUE'] . '"><datalist id="seo-inp-val-select">';
        $resPropertyCatalog = \CIBlockProperty::GetList([], array('IBLOCK_ID' => parent::catalogIblockID()));
        while ($prop = $resPropertyCatalog->Fetch()) {
            if ($prop['USER_TYPE'] != 'directory' && $prop['PROPERTY_TYPE'] != 'F') {
                $html .= '<option value="' . $prop['CODE'] . '" label="' . $prop['NAME'] . '" data-type="' . $prop['PROPERTY_TYPE'] . '"></option>';
            }
        }
        $html .= '</datalist>';
        $html .= '<span> Значение свойства: </span>';
        $html .= '<input id="seo-inp-desc" class="adm-input seo-inp-desc" type="text" placeholder="Значение свойства" list="seo-inp-select" name="' . $strHTMLControlName['DESCRIPTION'] . '" value="' . $value['DESCRIPTION'] . '">';
        $html .= '<datalist id="seo-inp-select"><option value="{FILTER_VALUE}" label="Любое из значений (По шаблону)"></option>';
        $html .= '</datalist>';
        $html .= '</div>';
        return $html;
    }

    /**
     * [iconSeoIblock замена иконки инфоблока в панели администратора]
     * "OnBuildGlobalMenu" - эвент
     */
    public function iconSeoIblock(&$aGlobalMenu, &$aModuleMenu)
    {
        foreach ($aModuleMenu as $key => $value) {
            if ($value['items_id'] == 'menu_iblock_/orwo_seotag') {
                $navSeoFilter = $key;
                $aModuleMenu[$navSeoFilter]['icon'] = 'seo_menu_icon';
            }
            if ($value['section'] == 'highloadblock') {
                $seoHighloadID = \Orwo\Seotag\InitFilter::seoHighloadID();
                $searchUrl = 'highloadblock_rows_list.php?ENTITY_ID=' . $seoHighloadID . '&lang=ru';
                $navHighload = $key;
            }
        }
        foreach ($aModuleMenu[$navHighload]['items'] as $k => $v) {
            if ($v['url'] == $searchUrl) {
                $aModuleMenu[$navSeoFilter]['items'][] = $aModuleMenu[$navHighload]['items'][$k];
                unset($aModuleMenu[$navHighload]['items'][$k]);
            }
        }
    }
}
