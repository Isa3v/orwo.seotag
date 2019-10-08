<?if(!empty($arResult['SECTIONS'])){?>
  <h2><?=$arResult['IBLOCK_SECTION']?>  — Виды:</h2>

  <?foreach ($arResult['SECTIONS'] as $arSection) {?>
    <div class="stags">
      <div class="stags__title"><?=$arSection['NAME']?></div>
      <div class="stags__section">
      <?foreach ($arSection['ITEMS'] as $arItem) {?>
        <span class="stags__item"><a href="<?=$arItem['LINK']?>"><?=$arItem['NAME']?></a></span>
      <?}?>
      </div>
    </div>
  <?}?>
<?}?>
