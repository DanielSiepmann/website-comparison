<?php

namespace Codappix\WebsiteComparison\Service;

/*
 * Copyright (C) 2018 Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Codappix\WebsiteComparison\Model\UrlListDto;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 *
 */
class ScreenshotCrawlerService
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var RemoteWebDriver
     */
    protected $driver;

    /**
     * @var string
     */
    protected $baseUrl = '';

    /**
     * @var string
     */
    protected $screenshotDir = '';

    /**
     * @var int
     */
    protected $screenshotWidth = 3840;

    /**
     * @var string
     */
    protected $compareDirectory = '';

    /**
     * @var string
     */
    protected $diffResultDir = '';

    /**
     * @var bool
     */
    protected $hasDifferences = false;

    public function __construct(
        OutputInterface $output,
        RemoteWebDriver $driver,
        string $baseUrl,
        string $screenshotDir = 'output/',
        int $screenshotWidth = 3840
    ) {
        $this->output = $output;
        $this->driver = $driver;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->screenshotDir = $this->convertReltiveFolder($screenshotDir);
        $this->screenshotWidth = $screenshotWidth;
    }

    public function crawl()
    {
        $this->createDir($this->screenshotDir);

        $linkList = new UrlListDto();
        $linkList->addUrl($this->baseUrl);

        while ($url = $linkList->getNextUrl()) {
            $this->driver->get($url);
            $screenshotHeight = $this->driver->findElement(WebDriverBy::cssSelector('body'))
                ->getSize()
                ->getHeight();
            $createdScreenshot = $this->createScreenshot($this->driver->getCurrentURL(), $screenshotHeight);

            if ($this->compareDirectory !== '') {
                $this->compareScreenshot($createdScreenshot);
            }

            $linkList->markUrlAsFinished($url);
            array_map([$linkList, 'addUrl'], $this->fetchFurtherLinks(
                $this->driver->findElements(WebDriverBy::cssSelector('a'))
            ));
        }
    }

    public function compare(string $compareDirectory, string $diffResultDir): bool
    {
        // TODO: Check for existence of directory
        $this->compareDirectory = $this->convertReltiveFolder($compareDirectory);
        $this->diffResultDir = $this->convertReltiveFolder($diffResultDir);
        $this->crawl();

        return $this->hasDifferences;
    }

    /**
     * @throws \Exception If folder could not be created.
     */
    protected function createDir(string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!is_dir($dir)) {
            throw new \Exception('Could not create directory: "' . $dir . '".', 1535528875);
        }
    }

    protected function createScreenshot(string $url, int $height): string
    {
        // TODO: Include width in screenshot dir.
        // This enables to compare different resolutions
        $screenshotTarget = $this->getScreenshotTarget($url);
        $this->createDir($this->screenshotDir . dirname($screenshotTarget));
        $completeScreenshotTarget = $this->screenshotDir . $screenshotTarget;

        $screenshotProcess = new Process([
            'chromium-browser',
            '--headless',
            '--disable-gpu',
            '--window-size=' . $this->screenshotWidth . ',' . $height,
            '--screenshot=' . $completeScreenshotTarget,
            $url
        ]);
        // TODO: Check for success
        $screenshotProcess->run();

        if ($this->output->isVerbose()) {
            $this->output->writeln(sprintf(
                '<info>Created screenshot "%s" for url "%s".</info>',
                $completeScreenshotTarget,
                $url
            ));
        }

        return $completeScreenshotTarget;
    }

    protected function compareScreenshot(string $screenshot)
    {
        try {
            if ($this->doScreenshotsDiffer($screenshot)) {
                $this->hasDifferences = true;
                $this->output->writeln(sprintf(
                    '<error>Screenshot "%s" is different then "%s". Diff was written to "%s".</error>',
                    $screenshot,
                    $this->getBaseScreenshot($screenshot),
                    $this->getDiffFileName($screenshot)
                ));
                return;
            }
        } catch (\ImagickException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            return;
        }

        if ($this->output->isVerbose()) {
            $this->output->writeln('<info>Screenshot is same.</info>');
        }
    }

    protected function doScreenshotsDiffer(string $screenshot): bool
    {
        $actualScreenshot = new \Imagick($screenshot);
        $actualGeometry = $actualScreenshot->getImageGeometry();
        $compareScreenshot = new \Imagick($this->getBaseScreenshot($screenshot));
        $compareGeometry = $compareScreenshot->getImageGeometry();

        if ($actualGeometry !== $compareGeometry) {
            throw new \ImagickException(sprintf(
                "Screenshots don't have an equal geometry. Should be %sx%s but is %sx%s",
                $compareGeometry['width'],
                $compareGeometry['height'],
                $actualGeometry['width'],
                $actualGeometry['height']
            ));
        }

        $result = $actualScreenshot->compareImages($compareScreenshot, \Imagick::METRIC_ROOTMEANSQUAREDERROR);
        if ($result[1] > 0) {
            /** @var \Imagick $diffScreenshot */
            $diffScreenshot = $result[0];
            $diffScreenshot->setImageFormat('png');
            $fileName = $this->getDiffFileName($screenshot);
            $this->createDir(dirname($fileName));
            file_put_contents($fileName, $diffScreenshot);

            return true;
        }

        return false;
    }

    protected function getBaseScreenshot(string $compareScreenshot): string
    {
        return str_replace(
            $this->screenshotDir,
            $this->compareDirectory,
            $compareScreenshot
        );
    }

    protected function getScreenshotTarget(string $url)
    {
        $uri = new Uri($url);

        return implode(
            DIRECTORY_SEPARATOR,
            array_filter(
                [
                    $uri->getScheme(),
                    $uri->getHost(),
                    trim($uri->getPath(), '/'),
                    $uri->getQuery(),
                ],
                function (string $string) {
                    return trim($string, ' /') !== '';
                }
            )
        ) . '.png';
    }

    protected function fetchFurtherLinks(array $webElements): array
    {
        $links = [];
        foreach ($webElements as $webElement) {
            try {
                $link = $this->fetchLinkFromElement($webElement);
            } catch (\Exception $e) {
                continue;
            }

            $links[] = $link;
        }

        return $links;
    }

    protected function fetchLinkFromElement(RemoteWebElement $element): string
    {
        $uri = null;
        $href = $element->getAttribute('href');
        if (is_string($href)) {
            $uri = new Uri($href);
        }

        if ($uri === null) {
            throw new \Exception('Did not get a Uri for element.', 1535530859);
        }

        if ($this->isInternalLink($uri)) {
            return (string) $uri;
        }

        throw new \Exception('Was external link.', 1535639056);
    }

    protected function isInternalLink(Uri $uri): bool
    {
        $validHosts = [
            '',
            (new Uri($this->baseUrl))->getHost(),
        ];

        return in_array($uri->getHost(), $validHosts);
    }

    protected function convertReltiveFolder(string $folder): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            dirname(dirname(dirname(__FILE__))),
            rtrim($folder, '/'),
        ]) . DIRECTORY_SEPARATOR;
    }

    protected function getDiffFileName(string $screenshot): string
    {
        return str_replace($this->screenshotDir, $this->diffResultDir, $screenshot);
    }
}
