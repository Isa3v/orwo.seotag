<?php
// Проверяем объявлен ли класс
if (class_exists('\Orwo\Seotag\InitFilter')) {
    // Запрашиваем данные текущего раздела
    $res = CIBlockSection::GetByID($arParams['SECTION_ID']);
    if ($arRes = $res->GetNext()) {
        // Название категории
        $arParams['IBLOCK_SECTION'] = $arRes['NAME'];
        // $arParams URL исходя из настроек для сверки с тегами.
        $arResult['SECTION_PAGE_URL'] = $arRes['SECTION_PAGE_URL'];
        $arResult['SECTION_ID'] = $arRes['ID'];
        $arResult['IBLOCK_SECTION'] = $arRes['NAME'];
    }

    // Получаем данные SEO Инфоблока
    $arFilterSeoPagesFilter = array(
      "IBLOCK_ID" => \Orwo\Seotag\InitFilter::seoIblockID(), // Из класса достаем ID сео инфоблока
      "ACTIVE_DATE" => "Y",
      "ACTIVE" => "Y", // Акивный
      "PROPERTY_SET_TAG_VALUE" => "Да", // Получаем с активным чекбоксом тегирования
      "PROPERTY_SET_ID_LIST" => $arResult['SECTION_ID'] // Есть URL раздела в списке
    );
    $dbFilterPages = \CIBlockElement::GetList(array("sort" => "desc"), $arFilterSeoPagesFilter, false, false, array("IBLOCK_ID", "ID", "NAME"));
    while ($obFilterPages = $dbFilterPages->GetNextElement()) {
        $arFields[] = $obFilterPages->GetProperties(); // Достаем свойства
    }

    foreach ($arFields as $item) {
        // Создаем ключ в транслите для секции
        $keySectionTag = Cutil::translit($item['SECTION_TAG']['VALUE'], "ru", array("replace_space"=>"_","replace_other"=>"_", "change_case"=>"U"));
        $arResult['SECTIONS'][$keySectionTag]['NAME'] = $item['SECTION_TAG']['VALUE'];

        // Добавляем элементы без шаблона
        if ($item['PROP_FILTER']['DESCRIPTION'] != '{FILTER_VALUE}' && !empty($item['PROP_FILTER']['DESCRIPTION'])) {
            // Делаем ссылку по шаблону
            $transCode = \Cutil::translit($item['PROP_FILTER']['DESCRIPTION'], "ru", array("replace_space"=>"_","replace_other"=>"_"));
            $link = mb_strtolower(str_ireplace("{filter_value}", $transCode, $item['NEW_SEF']['VALUE']));
            $link = str_ireplace("#SECTION_CODE_PATH#/", $arResult['SECTION_PAGE_URL'], $link);

            // Отправляем тег в массив
            $arResult['SECTIONS'][$keySectionTag]['ITEMS'][] = array(
                'NAME' => $item['NAME_TAG']['VALUE'],
                'LINK' => $link
              );
        // Если это шаблонное значение
        } else {
            // Получаем значения которые есть у данного раздела
            $rsProps = \CIBlockElement::GetList(array(), array(
                  "IBLOCK_ID"=>\Orwo\Seotag\InitFilter::catalogIblockID(),
                  "SECTION_ID" => $arResult['SECTION_ID'],
                  "INCLUDE_SUBSECTIONS" => "Y"
                ), array("PROPERTY_".$item['PROP_FILTER']['VALUE']));

            while ($arProps = $rsProps->Fetch()) {
                if (!empty($item['PROP_SLIDER']['VALUE'])) {
                    // Манипуляции для выборки из ползунка только 0.5 - 1 - 2 и т.д
                    // Округление в меньшую сторону 1.9 = 1
                    if ($arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'] < 1) {
                        $arRoundFiltered['0.5'] = $arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'];
                    } else {
                        $key = round($arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'], 0, PHP_ROUND_HALF_DOWN);
                        $arRoundFiltered[$key] = $arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'];
                    }
                }
                // Собираем массив
                $arProperties[] = $arProps;
            }


            foreach ($arProperties as $arProps) {
                if (!empty($arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'])) {
                    $propValue = $arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'];
                    // Делаем ссылку по шаблону
                    $newFilterURL = \Cutil::translit($propValue, "ru", array("replace_space"=>"_","replace_other"=>"_"));

                    if (!empty($item['PROP_SLIDER']['VALUE'])) {
                        $oldFilterURL = mb_strtolower($item['PROP_FILTER']['VALUE'].'-to-'.$arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE']);
                        if ($keyNewUrl = array_search($arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'], $arRoundFiltered)) {
                            $newFilterURL = $keyNewUrl;
                            $arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'] = $newFilterURL;
                        } else {
                            continue;
                        }
                    }
                    $link = mb_strtolower(str_ireplace("{filter_value}", $newFilterURL, $item['NEW_SEF']['VALUE']));
                    $link = str_ireplace("#SECTION_CODE_PATH#/", $arResult['SECTION_PAGE_URL'], $link);
                    // Для функции нам нужны значения
                    $arPattern = array('FILTER_VALUE' =>  $arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE']);

                    $name = \Orwo\Seotag\InitFilter::getPattern($item['NAME_TAG']['VALUE'], $arPattern);

                    $arResult['SECTIONS'][$keySectionTag]['ITEMS'][] = array(
                        'VALUE' => $arProps['PROPERTY_'.$item['PROP_FILTER']['VALUE'].'_VALUE'],
                        'NAME' => $name,
                        'LINK' => $link,
                      );
                }
            }

            // Сортирвем ползунки
            if (!empty($item['PROP_SLIDER']['VALUE'])) {
                foreach ($arResult['SECTIONS'][$keySectionTag]['ITEMS'] as $key=>$arr) {
                    $sortArray[$key]= $arr['VALUE'];
                }
                array_multisort($sortArray, SORT_NATURAL, $arResult['SECTIONS'][$keySectionTag]['ITEMS']);
            }
        }
    }
    $this->IncludeComponentTemplate();
}
