# Asset converter that uses flysystem for Yii2

This extension provides an asset converter that uses [flysystem](https://flysystem.thephpleague.com/v1/docs/) for Yii2.
It is based on [Nizsheanez's asset converter](https://packagist.org/packages/nizsheanez/yii2-asset-converter).

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require jc-it/yii2-flysystem-asset-converter
```

or add

```
"jc-it/yii2-flysystem-asset-converter": "^<latest version>"
```

to the `require` section of your `composer.json` file.

## Configuration
```php
...
'components' => [
    'assetFilesystem' => [
        'class' => \creocoder\flysystem\LocalFilesystem::class,
        'path' => '@webroot/assets',
    ],
    'assetManager' => [
        'converter' => [
            'class' => \JCIT\components\AssetConverter::class,
            'filesystem' => 'assetFilesystem',
        ],
    ],
],
```

## TODO
- Add tests

## Credits
- [Joey Claessen](https://github.com/joester89)
