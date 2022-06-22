<?php
declare(strict_types=1);

namespace JCIT\components;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\MountManager;
use nizsheanez\assetConverter\Converter;
use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

class AssetConverter extends Converter
{
    public Cache|string|array $cache = 'cache';
    public string $cacheKey = 'assets.';
    public Filesystem|string|array $destinationFilesystem;
    public string $tmpLocalDir = '@app/runtime/assetConversion';

    private function afterConversion(MountManager $mountManager, string $basePath, string $file, string $tmpBasePath): void
    {
        if (!$mountManager->fileExists('target://' . $basePath . '/'. $file)) {
            $mountManager->copy('tmp://' . $basePath . '/'. $file, 'target://' . $basePath . '/'. $file);
        }

        FileHelper::removeDirectory($tmpBasePath);
    }

    private function beforeConversion(MountManager $mountManager, string $basePath): string
    {
        $tmpBasePath = \Yii::getAlias($this->tmpLocalDir . '/' . $basePath);

        if (is_dir($tmpBasePath)) {
            FileHelper::removeDirectory($tmpBasePath);
        }
        FileHelper::createDirectory($tmpBasePath);

        $this->copyDir($basePath, $mountManager);

        return $tmpBasePath;
    }

    /**
     * Converts a given asset file into a CSS or JS file.
     * @param string $asset the asset file path, relative to $basePath
     * @param string $basePath the directory the $asset is relative to.
     * @return string the converted asset file path, relative to $basePath.
     */
    public function convert($asset, $basePath)
    {
        $pos = strrpos($asset, '.');
        if ($pos === false) {
            return parent::convert($asset, $basePath);
        }

        $ext = substr($asset, $pos + 1);
        if (!isset($this->parsers[$ext])) {
            return parent::convert($asset, $basePath);
        }

        $parserConfig = ArrayHelper::merge($this->defaultParsersOptions[$ext], $this->parsers[$ext]);

        $this->destinationDir = $this->destinationDir ? trim($this->destinationDir, '/') : '';
        $resultFile = $this->destinationDir . '/' . ltrim(substr($asset, 0, $pos + 1), '/') . $parserConfig['output'];

        $from = $basePath . '/' . ltrim($asset, '/');
        $to = $basePath . '/' . $resultFile;

        if (!$this->needRecompile($from, $to)) {
            return $resultFile;
        }

        $mountManager = new MountManager([
            'target' => $this->destinationFilesystem,
            'tmp' => new Filesystem(new LocalFilesystemAdapter(\Yii::getAlias($this->tmpLocalDir))),
        ]);

        $tmpBasePath = $this->beforeConversion($mountManager, $basePath);

        $this->checkDestinationDir($tmpBasePath, $resultFile);

        $tmpFrom = $tmpBasePath . '/' . ltrim($asset, '/');
        $tmpTo = $tmpBasePath . '/' . $resultFile;

        $asConsoleCommand = isset($parserConfig['asConsoleCommand']) && $parserConfig['asConsoleCommand'];
        if ($asConsoleCommand) { //can't use parent function because it not support destination directory
            if (isset($this->commands[$ext])) {
                list ($distExt, $command) = $this->commands[$ext];
                $this->runCommand($command, $tmpBasePath, $asset, $resultFile);
            }
        } else {
            $parser = new $parserConfig['class']($parserConfig['options']);
            $parserOptions = isset($parserConfig['options']) ? $parserConfig['options'] : array();
            $parser->parse($tmpFrom, $tmpTo, $parserOptions);
        }

        if (YII_DEBUG) {
            \Yii::info("Converted $asset into $resultFile ", __CLASS__);
        }

        $this->afterConversion($mountManager, $basePath, $resultFile, $tmpBasePath);
        $this->cache->set($this->cacheKey . $to, $resultFile, 5 * 60);

        return $resultFile;
    }

    protected function copyDir(string $dir, MountManager $mountManager): void
    {
        /** @var DirectoryAttributes|FileAttributes $element */
        foreach ($mountManager->listContents('target://' . $dir) as $element) {
            $path = preg_replace('/target:\/\//', '', $element->path());

            if ($element->isDir()) {
                $this->copyDir($path, $mountManager);
                continue;
            }

            if (!$mountManager->has('tmp://' . $path)) {
                $mountManager->copy('target://' . $path, 'tmp://' . $path);
            }
        }
    }

    public function init(): void
    {
        $this->destinationFilesystem = Instance::ensure(
            $this->destinationFilesystem,
            Filesystem::class
        );

        $this->cache = Instance::ensure(
            $this->cache,
            Cache::class
        );

        parent::init();
    }

    public function needRecompile($from, $to)
    {
        return $this->force
            || (
                $this->cache->get($this->cacheKey . $to) === false
                && (
                    !$this->destinationFilesystem->has($to)
                    || ($this->destinationFilesystem->lastModified($to) < $this->destinationFilesystem->lastModified($from))
                )
            );
    }
}
