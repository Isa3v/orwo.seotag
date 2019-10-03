### Установка
---
![Image](https://github.com/Isa3v/filterSeoTag/blob/master/installstep1.png?raw=true)
![Image](https://github.com/Isa3v/filterSeoTag/blob/master/installstep2.png?raw=true)
![Image](https://github.com/Isa3v/filterSeoTag/blob/master/installstep3.png?raw=true)
---
### Настройки перед работой. 
---
- В шаблоне умного фильтра, например: `/components/bitrix/catalog.smart.filter/`, в файл `result_modifier.php` в конце добавить:
```php
global $seoFilter;
$seoFilter = $arResult;
```
- После установки в папке `/bitrix/components/orwo/` - появится компонент для вывода блока тегов.  
  Желательно размещать его в `/components/bitrix/catalog/main/шаблон/section.php`.   
  Код для подключения 
```php
<?$APPLICATION->IncludeComponent(
  "orwo:orwo.seofilter.tags",
  "",
  array(
    "SECTION_ID" => $arSection['ID']
  )
);?>
```
`$arSection['ID']` - это ID раздела для выборки тегов.
- В настройках модуля можно добавить сгенерированные ссылки в карту сайта.
- В настройках можно изменить ID инфоблока для каталога и для SEO.
---
---
![Image](https://github.com/Isa3v/filterSeoTag/blob/master/readme.png?raw=true)
---

### Шаблоны мета-тегов
---
- `{FILTER_VALUE}` — Первый элемент (Всегда идет по дефолту). 
- `{FILTER_VALUE|0}` —  Если фильтр множественный (`/filter/brend-is-abac-or-comprag-or-remeza/apply/`), то свойства abac, comprag, remeza будут собраны в массив. Ключи начинаются с "0". 
> Например, что-бы получить remeza, нужно использовать `{FILTER_VALUE|2}` в шаблоне.
- `{SECTION_NAME}` —  имя категории к которой прендлежит фильтр.
#### Модификторы:
- `{CAPITALIZE_FILTER_VALUE}` —  Первая буква заглавная, остальные маленькие,
- `{LOWER_FILTER_VALUE}` —  Нижний регистр
- `{UPPER_FILTER_VALUE}` —  Верхний регистр
#### События (1.2): 
- `("orwo.seotag", "OnPropLinkCreate", [$arPropsEvent]);` —  Событие получает параметры условия перед созданием массива ссылок (тегов)
- `("orwo.seotag", "OnBeforeLinkAdd", [$arLink]);` —  Событие получает массив ссылок до момента их записи. 
---

### FAQ:
1. > ЧПУ заработало, но мета-теги остались прежними
- В конце файла `result_modifier.php` умного фильтра (`catalog.smart.filter`) нужно добавить:
```php
global $seoFilter;
$seoFilter = $arResult;
```
2. > Ссылки генерируются с неверным ЧПУ
- Проверьте настройки инфоблока каталога, т.к именно по этим настройкам происходит получение и генерация


### Примеры: 
#### Не шаблонное правило генерации (Отдельное значение свойства)
Нам нужно сделать фильтр вида: `https://example.ru/catalog/kompressory/filter/resiver-is-да/apply/` в такой `https://example.ru/catalog/kompressory/is_resiver/`.  
Для этого:
- Создаем новый элемент СЕО-инфоблока 
- В поле `"Свойство"` в первую строку вставляем символьный код свойства, в данном случае это RESIVER, а во второе поле вставляем значение свойства т.е "да"
- Выбираем в свойстве `"Применять для разделов"` нужный нам раздел
- в свойство `"URL новой страницы фильтра"`, прописываем ссылку "#SECTION_CODE_PATH#/is_resiver/"
- Включаем `"Добавить в тегирование"`. В поле `"Заголовок тега"` указываем название тега "С ресивером".
- Нажимаем "Применить" 

#### Генерация тегов и ЧПУ по шаблону
У нас есть свойства фильтра вида `https://example.ru/catalog/kompressory/filter/brend-is-ЗНАЧЕНИЕ/apply/`.  
Нам нужно сделать из них кучу тегов вида `https://example.ru/catalog/kompressory/brand/ЗНАЧЕНИЕ/`.  
Для этого: 
- Создаем новый элемент СЕО-инфоблока 
- В поле `"Свойство"` в первую строку вставляем символьный код свойства, а во второе поле `{FILTER_VALUE}`.
- Выбираем в свойстве `"Применять для разделов"` нужные нам разделы.
- в свойство `"URL новой страницы фильтра"`, прописываем ссылку "#SECTION_CODE_PATH#/brand/{FILTER_VALUE}/". 
- Мета-теги, назавние тегов генерируем так же через модификатор `{FILTER_VALUE}`.
- Нажимаем "Применить" 
