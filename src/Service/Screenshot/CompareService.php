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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class CompareService
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var Service
     */
    protected $screenshotService;

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
        EventDispatcherInterface $eventDispatcher,
        Service $screenshotService,
        string $compareDirectory,
        string $diffResultDir
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->screenshotService = $screenshotService;
        $this->compareDirectory = $this->screenshotService->convertReltiveFolder($compareDirectory);
        $this->diffResultDir = $this->screenshotService->convertReltiveFolder($diffResultDir);
    }

    public function hasDifferences(): bool
    {
        return $this->hasDifferences;
    }

    public function compareScreenshot(string $screenshot)
    {
        try {
            if ($this->doScreenshotsDiffer($screenshot)) {
                $this->eventDispatcher->dispatch(
                    'service.screenshot.isDifferent',
                    new GenericEvent('New Screenshot is different then base version.', [
                        'screenshot' => $screenshot,
                        'diff' => $this->getDiffFileName($screenshot)
                    ])
                );
                return;
            }
        } catch (\ImagickException $e) {
            $this->eventDispatcher->dispatch(
                'service.screenshot.error',
                new GenericEvent($e->getMessage(), [$e])
            );
            return;
        }

        $this->eventDispatcher->dispatch(
            'service.screenshot.isSame',
            new GenericEvent('New Screenshot is the same as base version.', [
                'screenshot' => $screenshot,
            ])
        );
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

        $result = $actualScreenshot->compareImages(
            $compareScreenshot,
            \Imagick::METRIC_ROOTMEANSQUAREDERROR
        );
        if ($result[1] > 0) {
            /** @var \Imagick $diffScreenshot */
            $diffScreenshot = $result[0];
            $diffScreenshot->setImageFormat('png');
            $fileName = $this->getDiffFileName($screenshot);
            $this->screenshotService->createDir(dirname($fileName));
            file_put_contents($fileName, $diffScreenshot);

            return true;
        }

        return false;
    }

    protected function getBaseScreenshot(string $compareScreenshot): string
    {
        return str_replace(
            $this->screenshotService->getScreenshotDir(),
            $this->compareDirectory,
            $compareScreenshot
        );
    }

    protected function getDiffFileName(string $screenshot): string
    {
        return str_replace(
            $this->screenshotService->getScreenshotDir(),
            $this->diffResultDir,
            $screenshot
        );
    }
}
