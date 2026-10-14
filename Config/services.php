<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('MauticPlugin\\AsyntaiBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, ['Tests', 'vendor'])).'}');

    // Name, display name and icon for the Plugins page.
    $services->alias('mautic.integration.asyntai', MauticPlugin\AsyntaiBundle\Integration\AsyntaiIntegration::class);
    // The form for the integration window on the Plugins page.
    $services->alias('asyntai.integration.configuration', MauticPlugin\AsyntaiBundle\Integration\Support\ConfigSupport::class);
};
