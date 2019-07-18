<?php
namespace Orwo\Seotag;

use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Uri;
use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;

class InitFilter
{
    public function seoIblockID()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "seoIblockID");
    }
    public function catalogIblockID()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "catalogIblockID");
    }
    public function seoHighloadID()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "highloadID");
    }
    public function seoHighloadTable()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "highloadTableName");
    }
    public function filterSef()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "filterSef");
    }


    /**
     * [Упрощенные SQL запросы для работы. Чтоб не создавать сущность и кучи запросов]
     */

    /**
     * [getLink Получить данные из Highload блока по ссылке]
     * @param  [type] $link    [ссылка]
     * @param  [type] $oldLink [если true, то ищет по оригинальной ссылке]
     * @return [type]          [Массив с данными о ссылке]
     */
    public function getLink($link=null, $oldLink = false)
    {
        $tableName = self::seoHighloadTable();
        if ($oldLink == true) {
            $whereIs = 'UF_OLD';
        } else {
            $whereIs = 'UF_NEW';
        }
        // Т.е мы запрашиваем с сылки которую передает пользователь
        // то обезопасим запрос через sqlhelper
        $sqlHelper = Application::getConnection()->getSqlHelper();
        $searchLink = $link;
        $arResult = Application::getConnection()->query("SELECT * FROM `".$tableName."` WHERE `".$whereIs."` = '".$sqlHelper->forSql($searchLink)."'", 1)->fetchRaw();

        return $arResult;
    }
    /**
     * [getLink Получить все ссылки]
     * @return [type]  [Массив с ссылками]
     */
    public function getAllLinks()
    {
        $tableName = self::seoHighloadTable();
        $sqlHelper = Application::getConnection()->getSqlHelper();
        $arResult = Application::getConnection()->query("SELECT * FROM `".$tableName."` WHERE `UF_ACTIVE` =  1")->fetchAll();
        return $arResult;
    }

    public function delAllLinks($ID = null)
    {
        if ($ID == null) {
            return;
        }
        $tableName = self::seoHighloadTable();
        $sqlHelper = Application::getConnection()->getSqlHelper();
        $arResult = Application::getConnection()->query("DELETE FROM `".$tableName."` WHERE `UF_ACTIVE` =  1 AND `UF_ID` = '".$sqlHelper->forSql($ID)."' AND (`UF_NOT_UPDATE` IS NULL OR `UF_NOT_UPDATE` = 0)");
        return true;
    }

    public function delLinkID($ID = null)
    {
        if ($ID == null) {
            return;
        }
        $tableName = self::seoHighloadTable();
        $sqlHelper = Application::getConnection()->getSqlHelper();
        $arResult = Application::getConnection()->query("DELETE FROM `".$tableName."` WHERE `ID` = '".$sqlHelper->forSql($ID)."' AND (`UF_NOT_UPDATE` IS NULL OR `UF_NOT_UPDATE` = 0)");
        return true;
    }


    /**
     * [searchRewrite поиск текущей страницы для переброса на linkRewrite]
     * Вызывается подключением к событию OnPageStart (перед прологом)
     */
    public function searchRewrite()
    {
        // Создание объекта Uri из адреса текущей страницы:
        $request = Context::getCurrent()->getRequest();
        $uri = new Uri($request->getRequestUri());
        // Оригинальный url кодируется, потому:
        $curPage = urldecode($uri->getPath());
        $query = $uri->getQuery();
        $newCurPage = self::getLink($curPage);

        if (!empty($newCurPage)) {
            self::linkRewrite($newCurPage['UF_OLD'], $newCurPage['UF_NEW']);
        } elseif ($originalCurPage = self::getLink($curPage, true)) {
            // Если есть редирект
            if ($originalCurPage['UF_REDIRECT'] == 1) {
                // Проверяем есть ли get параметры
                if (!empty($uri->getQuery())) {
                    LocalRedirect($originalCurPage['UF_NEW'].'?'.$uri->getQuery(), false, '301 Moved permanently');
                } else {
                    LocalRedirect($originalCurPage['UF_NEW'], false, '301 Moved permanently');
                }
            }
        }
    }

    /**
     * [linkRewrite помдена ссылки]
     * @param  [type] $contentLink [Сслыка с которой подтягиваем контент]
     * @param  [type] $newLink     [Ссылка на которой выводим]
     * Вызыватеся только перед прологом. Иначе магии не произойдет
     * Заставляет битрикс думать о другой странице (настоящей)
     */
    public static function linkRewrite($contentLink, $newLink)
    {
        // Создание объекта Uri из адреса текущей страницы:
        $context = Context::getCurrent();
        $request = $context->getRequest();
        $uri = new Uri($request->getRequestUri());
        $curPage = $uri->getPath();
        if (!empty($contentLink) && !empty($newLink)) {
            $server = $context->getServer();
            $server_array = $server->toArray();
            $_SERVER['REQUEST_URI'] = $contentLink;
            $server_array['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
            $server->set($server_array);
            $context->initialize(new HttpRequest($server, $_GET, array(), array(), $_COOKIE), $context->getResponse(), $server);
            $request->getRequestUri();
        }
    }

    /**
     * [addMeta замена мета-тегов]
     * Получем getFilter паттерны
     * Получем нужные сео шаблоны и заменяем
     */
    public function addMeta()
    {
        $request = Context::getCurrent()->getRequest();
        $uri = new Uri($request->getRequestUri());
        $curPage = $uri->getPath();
        global $USER;

        // Получаем фильтры
        $arFilter = self::getFilter();
        if (empty($arFilter)) {
            // Дальше не продолжаем, если не получили переменную
            return false;
        }
        if($USER->IsAdmin()){
          print_r($arFilter['VALUE_PATTERN']);
        }
        // Если найден ключ с ссылкой OLD настоящая ссылка всегда идет в curpage
        $originalCurPage = self::getLink($curPage, true);
        if (!empty($originalCurPage)) {
            // Получаем элементы из SEO инфоблока
            $arFilterSeoPagesFilter = array("IBLOCK_ID" => self::seoIblockID(), "ID" => $originalCurPage['UF_ID'], "ACTIVE_DATE" => "Y", "ACTIVE" => "Y");
            $dbFilterPages = \CIBlockElement::GetList(array(), $arFilterSeoPagesFilter, false, false, array("IBLOCK_ID", "ID", "NAME", "DATE_ACTIVE_FROM"));
            while ($obFilterPages = $dbFilterPages->GetNextElement()) {
                $seoItem = $obFilterPages->GetFields();
            }



            // Запрашиваем шаблоны мета-тегов из SEO Инфоблока
            $resMeta  = new \Bitrix\Iblock\InheritedProperty\ElementValues(self::seoIblockID(), $seoItem["ID"]);
            $seoItem["META_TAGS"] = $resMeta->getValues();
            $seoFilter['VALUE_PATTERN'] = $arRes['UF_TAG'];

            global $APPLICATION;
            // canonical
            $canonical = ($request->isHttps() == true ? "https://" : "http://").$_SERVER['HTTP_HOST'].$originalCurPage['UF_NEW'];
            $APPLICATION->SetPageProperty('canonical', $canonical);

            // title
            if (!empty($seoItem["META_TAGS"]['ELEMENT_META_TITLE'])) {
                $resTitle = self::getPattern($seoItem["META_TAGS"]['ELEMENT_META_TITLE'], $arFilter['VALUE_PATTERN']);
                $APPLICATION->SetPageProperty("title", $resTitle);
            }
            // description
            if (!empty($seoItem["META_TAGS"]['ELEMENT_META_DESCRIPTION'])) {
                $resDescription = self::getPattern($seoItem["META_TAGS"]['ELEMENT_META_DESCRIPTION'], $arFilter['VALUE_PATTERN']);
                $APPLICATION->SetPageProperty("description", $resDescription);
            }
            // h1
            if (!empty($seoItem["META_TAGS"]['ELEMENT_PAGE_TITLE'])) {
                $resPageTitle =  self::getPattern($seoItem["META_TAGS"]['ELEMENT_PAGE_TITLE'], $arFilter['VALUE_PATTERN']);
                $APPLICATION->SetTitle($resPageTitle);
                // Хлебные крошки
                $APPLICATION->AddChainItem($resPageTitle, $originalCurPage['UF_NEW']);
            }
        }
    }

    /**
     * [getFilter работа с переменными]
     * Получает данные из глобальной переменной $seoFilter
     * прописанной в result_modifier умного фильтра
     */
    public function getFilter()
    {
        // Получаем данные из глбольной переменной
        global $seoFilter;
        if (empty($seoFilter)) {
            return false;
        }
        // Получаем данные активных фильтров
        foreach ($seoFilter['ITEMS'] as $keySection => $arItem) {
            foreach ($arItem['VALUES'] as $kItem => $vItem) {

                // Выбранные фильтры
                if (isset($vItem['CHECKED']) || ($kItem == 'MIN' && isset($vItem['HTML_VALUE']) || $kItem == 'MAX' && isset($vItem['HTML_VALUE']))) {
                    $seoFilter['ACTIVE_FILTER']['VALUES'][$kItem]['SECTION_NAME'] = $seoFilter['SECTION_TITLE'];
                    $seoFilter['ACTIVE_FILTER']['VALUES'][$kItem]['FILTER_VALUE'] = $vItem['VALUE'];
                    $seoFilter['ACTIVE_FILTER']['RESULT'][$kItem] = $vItem;
                }
                // Ползунки значения в HTML_VALUE
                if ($kItem == 'MIN' && isset($vItem['HTML_VALUE']) || $kItem == 'MAX' && isset($vItem['HTML_VALUE'])) {
                    $seoFilter['ACTIVE_FILTER']['VALUES'][$kItem]['FILTER_VALUE'] = $vItem['HTML_VALUE'];
                    // Для ползунокв 0-0.999
                    if ($vItem['HTML_VALUE'] == 0) {
                        $seoFilter['ACTIVE_FILTER']['VALUES'][$kItem]['FILTER_VALUE'] = 0.5;
                    }
                }
            }
        }

        // Создаем паттерны для замены {FILTER_VALUE} и т.д.
        // Для выбора паттерна в шаблонах
        if (!empty($seoFilter['ACTIVE_FILTER'])) {
            foreach ($seoFilter['ACTIVE_FILTER']['VALUES'] as $k => $arPattern) {
                $numFilter = 0;
                foreach ($arPattern as $key => $value) {
                    $seoFilter['VALUE_PATTERN'][$key.'|'.$numFilter] = $value;
                    // дефолтные значения
                    if (!empty($value)) {
                        $seoFilter['VALUE_PATTERN'][$key] = $value;
                        $numFilter++;
                    }
                }
            }
            return $seoFilter;
        } else {
            return false;
        }
    }

    /**
     * [getPattern подмена паттернов в строке]
     * @param  string $string    [Строка с паттернами]
     * @param  array  $arPattern [Массив с ключ = паттерн, значение = значение паттерна]
     * @return string            [Возрващается строка с замененными значениями]
     */
    public function getPattern($string = '', $arPattern = '')
    {
        // Модификаторы:
        preg_match_all('/\{(CAPITALIZE|LOWER|UPPER)(?:_)([\w\|\d]+)\}/', $string, $match, PREG_SET_ORDER);
        foreach ($match as $k => $v) {
            if ($v[1] == 'CAPITALIZE') {
                $string =  str_ireplace($v[0], mb_convert_case(mb_strtolower($arPattern[$v[2]]), MB_CASE_TITLE, "UTF-8"), $string);
            } elseif ($v[1] == 'LOWER') {
                $string =   str_ireplace($v[0], mb_strtolower($arPattern[$v[2]]), $string);
            } elseif ($v[1] == 'UPPER') {
                $string =  str_ireplace($v[0], mb_strtoupper($arPattern[$v[2]]), $string);
            }
        }
        foreach ($arPattern as $k => $v) {
            $string =  str_ireplace('{'.$k.'}', $v, $string);
        }
        return $string;
    }
    
}
