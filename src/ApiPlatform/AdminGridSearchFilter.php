<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

final class AdminGridSearchFilter implements FilterInterface
{
    private const PARTIAL_TYPES = [Types::STRING, Types::ASCII_STRING];
    private const EXACT_TYPES = [
        Types::BIGINT,
        Types::DECIMAL,
        Types::FLOAT,
        Types::GUID,
        Types::INTEGER,
        Types::SMALLINT,
        Types::DATE_IMMUTABLE,
        Types::DATE_MUTABLE,
        Types::DATETIME_IMMUTABLE,
        Types::DATETIME_MUTABLE,
        Types::DATETIMETZ_IMMUTABLE,
        Types::DATETIMETZ_MUTABLE,
    ];

    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $query = trim((string) ($context['filters']['q'] ?? ''));
        if ($query === '') {
            return;
        }

        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return;
        }

        $metadata = $manager->getClassMetadata($resourceClass);
        $selectedField = trim((string) ($context['filters']['searchField'] ?? ''));
        $fields = $selectedField !== '' ? [$selectedField] : $metadata->getFieldNames();
        $alias = $queryBuilder->getRootAliases()[0];
        $expressions = [];

        foreach ($fields as $field) {
            if (!$metadata->hasField($field)) {
                continue;
            }

            $type = $metadata->getTypeOfField($field);
            if (in_array($type, self::PARTIAL_TYPES, true)) {
                $parameter = $queryNameGenerator->generateParameterName($field);
                $expressions[] = $queryBuilder->expr()->like(
                    sprintf('LOWER(%s.%s)', $alias, $field),
                    ':'.$parameter,
                );
                $queryBuilder->setParameter($parameter, '%'.strtolower(addcslashes($query, '\\%_')).'%');
                continue;
            }

            if (in_array($type, self::EXACT_TYPES, true)) {
                [$valid, $value] = $this->normalizeExactValue($type, $query);
                if (!$valid) {
                    if ($selectedField !== '') {
                        $queryBuilder->andWhere('1 = 0');

                        return;
                    }

                    continue;
                }

                $parameter = $queryNameGenerator->generateParameterName($field);
                $expressions[] = $queryBuilder->expr()->eq(sprintf('%s.%s', $alias, $field), ':'.$parameter);
                $queryBuilder->setParameter($parameter, $value, $type);
            }
        }

        if ($expressions !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$expressions));
        }
    }

    /** @return array{bool, mixed} */
    private function normalizeExactValue(string $type, string $query): array
    {
        if (in_array($type, [Types::BIGINT, Types::DECIMAL, Types::FLOAT, Types::INTEGER, Types::SMALLINT], true)) {
            return [is_numeric($query), $query];
        }

        if ($type === Types::GUID) {
            return [preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $query) === 1, $query];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T].*)?$/', $query) !== 1) {
            return [false, null];
        }

        try {
            $value = in_array($type, [Types::DATE_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE], true)
                ? new \DateTimeImmutable($query)
                : new \DateTime($query);
        } catch (\Exception) {
            return [false, null];
        }

        return [true, $value];
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'q' => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Search the Majpanel grid.',
            ],
            'searchField' => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Limit Majpanel grid search to one field.',
            ],
        ];
    }
}
