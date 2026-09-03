<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\CodeQuality\Rector\Class_\EventListenerToEventSubscriberRector;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.DIRECTORY_SEPARATOR.'src',
        __DIR__.DIRECTORY_SEPARATOR.'tests',
    ]);

    // No symfonyContainerXml() here: this is a library bundle, not a Symfony application - it
    // has no booted kernel and no var/cache/*/*.xml container dump for Rector to read.
    $rectorConfig->import(SymfonySetList::SYMFONY_CODE_QUALITY);
    $rectorConfig->import(SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION);
    $rectorConfig->import(SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES);
    $rectorConfig->import(DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES);
    $rectorConfig->import(DoctrineSetList::GEDMO_ANNOTATIONS_TO_ATTRIBUTES);
    $rectorConfig->import(PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES);

    // Do not apply this rector rules
    $rectorConfig->skip([
        EventListenerToEventSubscriberRector::class,
    ]);
};
