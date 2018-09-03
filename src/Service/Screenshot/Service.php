<?php

namespace Codappix\WebsiteComparison\Service\Screenshot;

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

use GuzzleHttp\Psr7\Uri;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Process\Process;

class Service
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var string
     */
    protected $screenshotDir = '';

    /**
     * @var int
     */
    protected $screenshotWidth = 3840;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        string $screenshotDir,
        int $screenshotWidth
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->screenshotDir = $this->convertReltiveFolder($screenshotDir);
        $this->screenshotWidth = $screenshotWidth;
    }

    public function createScreenshot(string $url, int $height): string
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
        $screenshotProcess->setTimeout(60 * 2);
        // TODO: Check for success
        $screenshotProcess->run();

        $this->eventDispatcher->dispatch(
            'service.screenshot.created',
            new GenericEvent('Created Screenshot', [
                'screenshot' => $completeScreenshotTarget,
                'url' => $url,
            ])
        );

        return $completeScreenshotTarget;
    }

    public function getScreenshotTarget(string $url, string $suffix = 'png'): string
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
        ) . '.' . $suffix;
    }

    public function getScreenshotDir(): string
    {
        return $this->screenshotDir;
    }

    /**
     * @throws \Exception If folder could not be created.
     */
    public function createDir(string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!is_dir($dir)) {
            throw new \Exception('Could not create directory: "' . $dir . '".', 1535528875);
        }
    }

    public function convertReltiveFolder(string $folder): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            dirname(dirname(dirname(dirname(__FILE__)))),
            rtrim($folder, '/'),
        ]) . DIRECTORY_SEPARATOR;
    }
}
