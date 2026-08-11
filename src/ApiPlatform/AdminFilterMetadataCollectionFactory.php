<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\ApiPlatform;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

final class AdminFilterMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public const ORDER_FILTER = 'majpanel.api.order_filter';
    public const SEARCH_FILTER = 'majpanel.api.search_filter';
    public const GRID_SEARCH_FILTER = 'majpanel.api.grid_search_filter';

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $index => $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                continue;
            }

            foreach ($operations as $operationName => $operation) {
                if (!$operation instanceof GetCollection || !$this->isAdminOperation($operation, $resource->getRoutePrefix())) {
                    continue;
                }

                $operations->add($operationName, $operation->withFilters(array_values(array_unique([
                    ...($operation->getFilters() ?? []),
                    self::ORDER_FILTER,
                    self::SEARCH_FILTER,
                    self::GRID_SEARCH_FILTER,
                ]))));
            }

            $collection[$index] = $resource->withOperations($operations);
        }

        return $collection;
    }

    private function isAdminOperation(GetCollection $operation, ?string $resourceRoutePrefix): bool
    {
        $path = ($operation->getRoutePrefix() ?? $resourceRoutePrefix ?? '').($operation->getUriTemplate() ?? '');

        return str_starts_with($path, '/admin');
    }
}
