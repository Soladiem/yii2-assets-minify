Автоматическая минификация CSS/JS файлов и HTML кода
=

Требования
-
PHP 7.0 или выше

Установка
-
С использованием [composer](http://getcomposer.org/download/).
```
composer require soladiem/yii2-assets-minify
```
или добавте в composer.json
```
"soladiem/yii2-assets-minify": "^1.0"
```
Подключение
-
Конфигурационный файл приложения:

```php
[
    'bootstrap' => ['AssetsMinify'],
    'components' =>
    [
    //...
        'AssetsMinify' =>
        [
            'class' => '\soladiem\autoMinify\AssetsMinify',
        ],
    //...
    ]
]
```

Возможные настройки:

Включение/отключение использования компонента. По умолчанию **true**
```php
enabled = false
```

Массив имен файлов, исключенных из минификации
```php
excludeFiles = []
```

Время в секундах для чтения каждого asset-файла. По умолчанию значение **3**
```php
readfileTimeout = 3
```

Разрешить минификацию Javascript в HTML коде. По умолчанию **true**
```php
jsMinifyHtml = true
```

Разрешить минификацию CSS в HTML коде. По умолчанию **true**
```php
cssMinifyHtml = true
```

Вырезать Javascript комментарии. По умолчанию **true**
```php
jsCutFlaggedComments = true
```

Вырезать CSS комментарии. По умолчанию **true**
```php
cssCutFlaggedComments = true
```

Компиляция связанных Javascript файлов. По умолчанию **true**
```php
jsFileCompile = true
```

Компиляция связанных CSS файлов. По умолчанию **true**
```php
cssFileCompile = true
```

Загрузка и компиляция удаленных Javascript файлов. По умолчанию **false**
```php
jsFileRemoteCompile = false
```

Загрузка и компиляция удаленных CSS файлов. По умолчанию **false**
```php
cssFileRemoteCompile = false
```

Сжимать Javascript файл. По умолчанию **true**
```php
jsFileCompress = true
```

Сжимать CSS файл. По умолчанию **true**
```php
cssFileCompress = true
```

Разрешить сжатие HTML-кода. По умолчанию **true** 
```php
htmlCompress = true
```

Настройки для сжатия HTML-кода. По умолчанию
```php
$htmlCompressOptions = [
    'extra' => false,
    'no-comments' => true
];
```

Переместить CSS файлы в самый низ страницы. По умолчанию **true**
```php
cssFileBottom = true
```

Переместить CSS файлы в низ страницы и для загрузки использовать Javascript. По умолчанию **false**
```php
cssFileBottomLoadOnJs = false
```

Не подключать Javascript файлы при использовании Pjax. По умолчанию **true**
```php
noIncludeJsFilesOnPjax = true
```

Название папки для хранения минифицированного CSS файла. По умолчанию **css**
```php
pathCompileCssFile = 'css'
```

Название папки для хранения минифицированного Javascript файла. По умолчанию **js**
```php
pathCompileJsFile = 'js'
```

[sitkodenis.ru](https://sitkodenis.ru)