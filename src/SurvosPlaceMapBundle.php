<?php

declare(strict_types=1);

namespace Survos\PlaceMapBundle;

use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Survos\Kit\AbstractUxBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\Kit\Traits\HasDoctrineEntities;
use Survos\PlaceMapBundle\Repository\PlaceRepository;
use Survos\PlaceMapBundle\Service\NominatimClient;
use Survos\PlaceMapBundle\Service\PlaceMapBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Open-source place library: a Place entity (point and/or GeoJSON polygon,
 * imported from OpenStreetMap/Nominatim) plus PlaceMapBuilder, which converts
 * Place rows into Symfony\UX\Map (ux-map / Leaflet) Marker/Polygon value
 * objects. Rendering itself is ux-map's own `ux_map()` Twig function — this
 * bundle ships no map component of its own, only the place-library data layer
 * (Place entity + `place:import` command, both auto-registered — see
 * HasDoctrineEntities / AbstractSurvosBundle::loadExtension()) and a small
 * Stimulus enhancement for clustering + thumbnail-on-zoom (see
 * assets/controllers/place_map_enhance_controller.js and the README).
 *
 * The consuming app must itself require symfony/ux-map + a renderer bridge
 * (e.g. symfony/ux-leaflet-map) — this bundle only depends on the value
 * objects (Marker/Polygon/Icon), not a specific renderer.
 */
#[RequiredBundle(SurvosKitBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosPlaceMapBundle extends AbstractUxBundle
{
    use HasDoctrineEntities;

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()->children()
            ->scalarNode('nominatim_user_agent')
                ->info('Required by Nominatim usage policy: "AppName/version (https://your-site; contact@you)".')
                ->isRequired()
            ->end()
        ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        // PlaceImportOsmCommand (#[AsCommand]) is auto-registered by the src/Command/ scan;
        // src/Repository/ is not auto-scanned, so PlaceRepository needs the explicit tag below.
        $this->registerRepositories($container, PlaceRepository::class);

        $builder->autowire(NominatimClient::class)
            ->setArgument('$userAgent', $config['nominatim_user_agent'])
            ->setPublic(false);

        $builder->autowire(PlaceMapBuilder::class)
            // imgproxy-bundle is optional — null when absent (falls back to raw source URLs).
            ->setArgument('$imgproxy', new Reference(ImgproxyUrlBuilder::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setPublic(true);
    }
}
