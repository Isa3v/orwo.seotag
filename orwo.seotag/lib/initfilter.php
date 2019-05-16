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

class InitFilter
{
    public static $cacheName = 'seotag_cache'; // Имя переменной кеша и папки

    public function seoIblockID()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "seoIblockID");
    }
    public function catalogIblockID()
    {
        return \Bitrix\Main\Config\Option::get("orwo.seotag", "catalogIblockID");
    }

    /**
     * [init получаем ссылки и загоняем в кеш]
     * Получает только свойство RESULT и декодирует
     * @return [array] [список элементов SEO инфоблока для работы ]
     */
    public function initCache()
    {
        $obCache = Cache::createInstance(); // получаем экземпляр класса
        //Проверяем не истек ли кеш
        if ($obCache->initCache(2419200, self::$cacheName, "/".self::$cacheName)) {
            $arCache = $obCache->getVars();
            $arResult = $arCache;
        } else {
            Loader::includeModule("iblock");
            $propID = \CIBlockProperty::GetByID("RESULT", self::seoIblockID())->GetNext()['ID'];
            $start = microtime(true);
            $resJsonProp = \CIBlockElement::GetPropertyValues(self::seoIblockID(), array("ACTIVE" => "Y"), false, array('ID' => array($propID)));
            while ($arJson = $resJsonProp->Fetch()) {
                foreach ($arJson[$propID] as $jsonProp) {
                    $arLink = Json::decode($jsonProp);
                    foreach ($arLink as $link) {
                        $arResult[] = $link;
                    }
                }
            }
        }
        // если кеш есть, то просто выводится содержимое кеша
        if ($obCache->startDataCache()) {
            $obCache->endDataCache($arResult);
        }
        return $arResult;
    }

    /**
     * [beforetUpdateElement создаем ссылки на стадии сохраниения элемента]
     * Зачем кучу запросов выполнять пользователю,
     * когда мы можем сразу собрать нужный массив
     * @param  [type] $arFields [Данные пришедшие с события]
     */
    public function beforetUpdateElement(&$arFields)
    {
        // Данные действия нужны только для сео инфоблока
        if ($arFields['IBLOCK_ID'] == self::seoIblockID()) {
            Loader::includeModule("iblock");
            // Получаем ID свойств
            $propFilterID = \CIBlockProperty::GetByID("PROP_FILTER", self::seoIblockID())->GetNext()['ID'];
            $newSefID = \CIBlockProperty::GetByID("NEW_SEF", self::seoIblockID())->GetNext()['ID'];
            $idListID = \CIBlockProperty::GetByID("SET_ID_LIST", self::seoIblockID())->GetNext()['ID'];
            $propSliderID = \CIBlockProperty::GetByID("PROP_SLIDER", self::seoIblockID())->GetNext()['ID'];
            $resultJsonID = \CIBlockProperty::GetByID("RESULT", self::seoIblockID())->GetNext()['ID'];
            $redirectID = \CIBlockProperty::GetByID("REDIRECT", self::seoIblockID())->GetNext()['ID'];

            foreach ($arFields['PROPERTY_VALUES'][$idListID] as $sectionID) {
                $res = \CIBlockSection::GetByID($sectionID['VALUE']);
                if ($arRes = $res->GetNext()) {
                    $urlList[] = array('SECTION_PAGE_URL' => $arRes['SECTION_PAGE_URL'], 'ID' => $arRes['ID'],  );
                }
            }

            $filterUrlList = [];
            // Понимаем какой шаблон для url
            foreach ($arFields['PROPERTY_VALUES'][$newSefID] as $sef) {
                if (!empty($sef['VALUE'])) {
                    $sefURL = $sef['VALUE'];
                } else {
                    return;
                }
            }
            // Делаем ссылки из нужных нам
            foreach ($urlList as $url) {
                if (!empty($url['SECTION_PAGE_URL'])) {
                    $filterUrlList[] = array(
                      'OLD_LINK' => $url['SECTION_PAGE_URL'].'filter/',
                      'NEW_LINK' => mb_strtolower(str_replace("#SECTION_CODE_PATH#/", $url['SECTION_PAGE_URL'], $sefURL)),
                      'ID' => $url['ID']
                    );
                }
            }
            // Тепереь собираем полный url
            $countArray = 0;
            $countLink = 0;

            foreach ($filterUrlList as $compliteURL) {
                foreach ($arFields['PROPERTY_VALUES'][$propFilterID] as $propValues) {
                    // Для шаблонных ссылок собираем сразу всевозможные варианты
                    if ($propValues['DESCRIPTION'] == "{FILTER_VALUE}" || empty($propValues['DESCRIPTION'])) {
                        // Запрос на выборку всех вариантов
                        $rsProps = \CIBlockElement::GetList(array(), array("IBLOCK_ID"=>self::catalogIblockID(), "SECTION_ID" => $compliteURL['ID'], "INCLUDE_SUBSECTIONS" => "Y"), array("PROPERTY_".$propValues['VALUE']));
                        while ($arProps = $rsProps->Fetch()) {

                            // Для свойств фильтра вида "ползунка"
                            if (!empty($arFields['PROPERTY_VALUES'][$propSliderID])) {
                                // Манипуляции для выборки из ползунка только 0.5 - 1 - 2 и т.д
                                // Округление в меньшую сторону 1.9 = 1
                                if ($arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE'] < 1) {
                                    $arRoundFiltered['0.5'] = $arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE'];
                                } else {
                                    $key = floor($arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE']);
                                    $arRoundFiltered[$key] = $arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE'];
                                }
                            }
                            // Собираем массив
                            $arProperties[] = $arProps;
                        }

                        // Работаем с массивом параметров
                        foreach ($arProperties as $arProps) {
                            if (!empty($arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE'])) {
                                // Разбиваем массив по 100 ключей в одном. Т.к у строки есть придел в ~65к символов
                                if ($countLink % 100 == 0) {
                                    $countArray++;
                                    $countLink = 0;
                                }
                                $arParams = array("replace_space"=>"_","replace_other"=>"_");
                                $newFilterURL = mb_strtolower(\Cutil::translit($arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE'], "ru", $arParams));
                                $oldFilterURL = mb_strtolower($propValues['VALUE'].'-is-'.$arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE']);

                                // Для свойств фильтра вида "ползунка"
                                if (!empty($arFields['PROPERTY_VALUES'][$propSliderID])) {
                                    // Ищем в массиве выборки данное значение, если не нашли пропускаем
                                    if ($keyNewUrl = array_search($arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE'], $arRoundFiltered)) {
                                        // Т.к округление в меньшую сторону, а для 0.5 начинаем от 0
                                        $minVal = ($keyNewUrl < 0.9 ? 0 : $keyNewUrl);
                                        $oldFilterURL = mb_strtolower($propValues['VALUE'].'-from-'.$minVal.'-to-'.$arProps['PROPERTY_'.$propValues['VALUE'].'_VALUE']);
                                        $newFilterURL = $keyNewUrl;
                                    } else {
                                        continue;
                                    }
                                }
                                // Делаем массив ссылок
                                $arResultSef[$countArray][$countLink] = array(
                                  'OLD' =>  $compliteURL['OLD_LINK'].$oldFilterURL.'/apply/',
                                  'NEW' => mb_strtolower(str_replace("{filter_value}", $newFilterURL, $compliteURL['NEW_LINK'])),
                                  'ID' => $arFields['ID']
                                );
                                // Если нужен редирект со старой страницы
                                if (!empty($arFields['PROPERTY_VALUES'][$redirectID])) {
                                    $arResultSef[$countArray][$countLink]['R'] = true;
                                }
                                $countLink++;
                            }
                        }
                    } else {
                        // Если введено конкретное значение:
                        $arParams = array("replace_space"=>"_","replace_other"=>"_");
                        $newFilterURL = mb_strtolower(\Cutil::translit($propValues['DESCRIPTION'], "ru", $arParams));
                        $oldFilterURL = mb_strtolower($propValues['VALUE'].'-is-'.$propValues['DESCRIPTION']);
                        // Делаем массив ссылок
                        $arResultSef[$countArray][$countLink] = array(
                          'OLD' =>  $compliteURL['OLD_LINK'].$oldFilterURL.'/apply/',
                          'NEW' => mb_strtolower(str_replace("{filter_value}", $newFilterURL, $compliteURL['NEW_LINK'])),
                          'ID' => $arFields['ID']
                        );
                        // Если нужен редирект со старой страницы
                        if (!empty($arFields['PROPERTY_VALUES'][$redirectID])) {
                            $arResultSef[$countArray][$countLink]['R'] = true;
                        }
                        $countLink++;
                    }
                }
            }
            $countResult = 0;
            // Чтоб не дублировались ключи т.к им задается чудо-ID удаляем вначале
            unset($arFields['PROPERTY_VALUES'][$resultJsonID]);
            // Записываем в системное свойство
            foreach ($arResultSef as $key => $value) {
                $arFields['PROPERTY_VALUES'][$resultJsonID][$countResult] = Json::encode($value, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
                $countResult++;
            }
            // Сбрасываем кеш сео шаблонов
            Cache::createInstance()->clean(self::$cacheName, "/".self::$cacheName);
        }
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
        $arResult = self::initCache();

        $originalCurPage = array_search($curPage, array_column($arResult, 'OLD'));
        $newCurPage =  array_search($curPage, array_column($arResult, 'NEW'));

        if ($newCurPage !== false && $arResult[$newCurPage]['NEW'] == $curPage) {
            // Вызываем подмену ссылки, и т.к json пробел считает "+", меняем
            self::linkRewrite($arResult[$newCurPage]['OLD'], $arResult[$newCurPage]['NEW']);
        } elseif ($newCurPage !== false && $arResult[$newCurPage]['NEW'] == $curPage) {
            // Если есть редирект
            if ($arResult[$originalCurPage]['R'] == true) {
                // Проверяем есть ли get параметры
                if (!empty($uri->getQuery())) {
                    LocalRedirect($arResult[$originalCurPage]['NEW'].'?'.$uri->getQuery(), false, '301 Moved permanently');
                } else {
                    LocalRedirect($arResult[$originalCurPage]['NEW'], false, '301 Moved permanently');
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

        // Получаем фильтры
        $arFilter = self::getFilter();
        if (empty($arFilter)) {
            // Дальше не продолжаем, если не получили переменную
            return false;
        }
        // Получаем данные сео инфоблока
        $arResult = self::initCache();
        // Если найден ключ с ссылкой OLD настоящая ссылка всегда идет в curpage
        $originalCurPage = array_search($curPage, array_column($arResult, 'OLD'));
        if ($originalCurPage !== false && $arResult[$originalCurPage]['OLD'] == $curPage) {

            // Получаем элементы из SEO инфоблока
            $arFilterSeoPagesFilter = array("IBLOCK_ID" => self::seoIblockID(), "ID" => $arResult[$originalCurPage]['ID'], "ACTIVE_DATE" => "Y", "ACTIVE" => "Y");
            $dbFilterPages = \CIBlockElement::GetList(array("sort" => "asc"), $arFilterSeoPagesFilter, false, false, array("IBLOCK_ID", "ID", "NAME", "DATE_ACTIVE_FROM"));
            while ($obFilterPages = $dbFilterPages->GetNextElement()) {
                $seoItem = $obFilterPages->GetFields();
            }

            // Запрашиваем шаблоны мета-тегов из SEO Инфоблока
            $resMeta  = new \Bitrix\Iblock\InheritedProperty\ElementValues(self::seoIblockID(), $seoItem["ID"]);
            $seoItem["META_TAGS"] = $resMeta->getValues();

            global $APPLICATION;
            // canonical
            $canonical = ($request->isHttps() == true ? "https://" : "http://").$_SERVER['HTTP_HOST'].$arResult[$originalCurPage]['NEW'];
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
                $APPLICATION->AddChainItem($resPageTitle, $arResult[$findKey]['NEW']);
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
                    $seoFilter['ACTIVE_FILTER']['VALUES'][$kItem]['FILTER_NAME'] = $arItem['NAME'];
                    $seoFilter['ACTIVE_FILTER']['VALUES'][$kItem]['FILTER_CODE'] = $arItem['CODE'];
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
            $numFilter = 0;
            foreach ($seoFilter['ACTIVE_FILTER']['VALUES'] as $k => $arPattern) {
                foreach ($arPattern as $key => $value) {
                    $seoFilter['VALUE_PATTERN'][$key.'|'.$numFilter] = $value;
                    // дефолтные значения
                    if ($numFilter == 0) {
                        $seoFilter['VALUE_PATTERN'][$key] = $value;
                    }
                }
                $numFilter++;
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
        preg_match_all('/\{(CAPITALIZE|LOWER|UPPER)(?:_)([\w]+)\}/', $string, $match, PREG_SET_ORDER);
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
