<?php
namespace Orwo\Seotag;

use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Uri;
use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Web\Json;

class EventHelp extends \Orwo\Seotag\InitFilter
{

  /**
   * [panelButton добавление кнопки редактирвания на панель]
   */
    public function panelButton()
    {
        global $APPLICATION, $USER;
        // Зачем пользователю то, что он все ровно не увидит
        if (!$USER->IsAdmin()) {
            return false;
        }
        $request = Context::getCurrent()->getRequest();
        $uri = new Uri($request->getRequestUri());
        $curPage = $uri->getPath();

        // Получаем данные сео инфоблока
        $arResult = parent::initCache();
        // Если найден ключ с ссылкой OLD настоящая ссылка всегда идет в curpage
        $originalCurPage = array_search($curPage, array_column($arResult, 'OLD'));
        if ($originalCurPage !== false && $arResult[$originalCurPage]['OLD'] == $curPage) {
            // Выбираем текущую ссылку т.к curpage у нас заменился ранее, то проверяем обе ссылки
            $editID = $arResult[$originalCurPage]["ID"];
            $editIblock = parent::seoIblockID();
            Loader::includeModule("iblock");
            $arButtons = \CIBlock::GetPanelButtons(
              $editIblock,
              $editID,
              0,
              array("SECTION_BUTTONS"=>false, "SESSID"=>false)
          );
            if ($arButtons["edit"]["edit_element"]["ACTION"]) {
                $APPLICATION->AddPanelButton(
                  array(
                    "ID" => "3001", //определяет уникальность кнопки
                    "TEXT" => "Редактировать SEO — фильтр",
                    "ALT"  => "Всего сгенерированно тегов: ".count($arResult),
                    "MAIN_SORT" => 3000, //индекс сортировки для групп кнопок
                    "SORT" => 1, //сортировка внутри группы
                    "HREF" => $arButtons["edit"]["edit_element"]["ACTION"], //или javascript:MyJSFunction())
                    "ICON" => "bx-panel-site-template-icon", //название CSS-класса с иконкой кнопки
                  ),
                  $bReplace = false //заменить существующую кнопку?
                );
            }
        }
    }


    public function onInitHelpTab(&$arFields)
    {
        if ($arFields['IBLOCK']['ID'] == parent::seoIblockID()) {
            return array(
            "TABSET" => "YYY",
            "GetTabs" => array(__CLASS__, "getHelpTabs"),
            "ShowTab" => array(__CLASS__, "showHelpTab"),
          );
        }
    }

    public function getHelpTabs($arArgs)
    {
        return [["DIV" => "helpTab", "TAB" => "Документация"]];
    }

    public function showHelpTab($divName, $arArgs, $bVarsFromForm)
    {
        if ($divName == "helpTab") {
            // Документация подтягивается с гитхаба
            echo '<tr><td style="padding: 5px;">
            <script src="//gist.github.com/Isa3v/e20394135dac1a5925e61cfd75c81cfa.js"></script>
            <script>document.querySelector(".gist-meta").innerHTML = "Original Works";</script>
            </td></tr>';

        }
    }
}
