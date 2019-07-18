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
    // Получаем ID Highloadblock блока из модуля
    $seoHighloadID = \Orwo\Seotag\InitFilter::seoHighloadID();
    // Создаем сущность для работы с блоком:
    if (\Bitrix\Main\Loader::includeModule('highloadblock')) {
        $arHLBlock = Bitrix\Highloadblock\HighloadBlockTable::getById($seoHighloadID)->fetch();
        $obEntity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arHLBlock);
        $strEntityDataClass = $obEntity->getDataClass();

        //Получение списка:
        $rsData = $strEntityDataClass::getList(array(
            'select' => array('UF_NEW', 'UF_ID', 'UF_SECTION', 'UF_TAG', 'UF_TOP'),
            'filter' => array('UF_SECTION' => $arParams['SECTION_ID'])
         ));
        while ($arHighloadItem = $rsData->Fetch()) {
            $arItems[] = $arHighloadItem;
        }
    }

    // Получаем данные SEO Инфоблока
    $arFilterSeoPagesFilter = array(
        "IBLOCK_ID" => \Orwo\Seotag\InitFilter::seoIblockID(), // Из класса достаем ID сео инфоблока
        "ACTIVE_DATE" => "Y",
        "ACTIVE" => "Y", // Акивный
        "PROPERTY_SET_TAG_VALUE" => "Y", // Получаем с активным чекбоксом тегирования
        "PROPERTY_SET_ID_LIST" => $arResult['SECTION_ID'] // Есть URL раздела в списке
      );
    $dbFilterPages = \CIBlockElement::GetList(array("sort" => "desc"), $arFilterSeoPagesFilter, false, false, array("IBLOCK_ID", "ID", "NAME", 'PROPERTY_SECTION_TAG'));
    while ($obFilterPages = $dbFilterPages->fetch()) {
        // Ключ = транслит
        if(!empty($obFilterPages['PROPERTY_SECTION_TAG_VALUE'])){
          $keySectionTag = Cutil::translit($obFilterPages['PROPERTY_SECTION_TAG_VALUE'], "ru", array("replace_space"=>"_","replace_other"=>"_", "change_case"=>"U"));
        }else{
          $keySectionTag = $obFilterPages['ID'];
        }
        // Имя категории
        if(!empty($obFilterPages['PROPERTY_SECTION_TAG_VALUE'])){
        $arResult['SECTIONS'][$keySectionTag]['NAME'] = $obFilterPages['PROPERTY_SECTION_TAG_VALUE'];
        }
        // Собираем элементы Highload блока по разделам
        foreach ($arItems as  $arValue) {
            if ($arValue['UF_ID'] == $obFilterPages['ID']) {
                if($arValue['UF_TOP'] == 1){
                  $arResult['SECTIONS'][$keySectionTag]['IN_TOP'] = 'Y';
                }
                $arResult['SECTIONS'][$keySectionTag]['ITEMS'][] =  array(
                  'NAME' => $arValue['UF_TAG'],
                  'LINK' => $arValue['UF_NEW'],
                  'TOP' => $arValue['UF_TOP']
                );
            }
        }
    }

    $this->IncludeComponentTemplate();
}
