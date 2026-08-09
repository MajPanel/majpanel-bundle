<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminRelationOptionsController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly IriConverterInterface $iriConverter,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    #[Route(
        '/api/admin/majpanel/relation-options/{entity}',
        name: 'majpanel_admin_relation_options',
        requirements: ['entity' => '[A-Za-z_][A-Za-z0-9_]*'],
        methods: ['GET'],
        priority: 100,
    )]
    public function __invoke(string $entity, Request $request): JsonResponse
    {
        $className = $this->resolveEntityClass($entity);
        $metadata = $this->entityManager->getClassMetadata($className);
        $labelFields = $this->resolveLabelFields($metadata, (string) $request->query->get('fields', ''));
        $search = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('relation_entity')
            ->from($className, 'relation_entity')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE + 1);

        foreach ($metadata->getIdentifierFieldNames() as $identifier) {
            $queryBuilder->addOrderBy('relation_entity.'.$identifier, 'ASC');
        }

        if ($search !== '') {
            $conditions = $queryBuilder->expr()->orX();
            $usesTextSearch = false;
            foreach ($labelFields as $field) {
                $type = $metadata->getTypeOfField($field);
                if (in_array($type, ['string', 'text'], true)) {
                    $conditions->add(sprintf('LOWER(relation_entity.%s) LIKE :relation_search', $field));
                    $usesTextSearch = true;
                } elseif (in_array($field, $metadata->getIdentifierFieldNames(), true) && ctype_digit($search)) {
                    $conditions->add(sprintf('relation_entity.%s = :relation_identifier', $field));
                    $queryBuilder->setParameter('relation_identifier', $search);
                }
            }

            if ($conditions->count() > 0) {
                $queryBuilder->andWhere($conditions);
                if ($usesTextSearch) {
                    $queryBuilder->setParameter('relation_search', '%'.mb_strtolower($search).'%');
                }
            } else {
                $queryBuilder->andWhere('1 = 0');
            }
        }

        /** @var list<object> $entities */
        $entities = $queryBuilder->getQuery()->getResult();
        $hasMore = count($entities) > self::PAGE_SIZE;
        $entities = array_slice($entities, 0, self::PAGE_SIZE);

        $selectedIris = $request->query->all('selected');
        foreach ($selectedIris as $iri) {
            if (!is_string($iri) || $iri === '') {
                continue;
            }

            try {
                $selected = $this->iriConverter->getResourceFromIri($iri);
                if ($selected instanceof $className) {
                    $entities[] = $selected;
                }
            } catch (\Throwable) {
                // Ignore stale or invalid values; the form can still select a replacement.
            }
        }

        $items = [];
        foreach ($entities as $relationEntity) {
            $iri = $this->iriConverter->getIriFromResource($relationEntity);
            if ($iri === null || isset($items[$iri])) {
                continue;
            }

            $items[$iri] = [
                'value' => $iri,
                'label' => $this->buildLabel($relationEntity, $labelFields),
            ];
        }

        return $this->json([
            'items' => array_values($items),
            'page' => $page,
            'hasMore' => $hasMore,
        ]);
    }

    /** @return class-string */
    private function resolveEntityClass(string $shortName): string
    {
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $reflection = $metadata->getReflectionClass();
            if ($reflection === null || strcasecmp($reflection->getShortName(), $shortName) !== 0) {
                continue;
            }

            try {
                $this->resourceMetadataFactory->create($metadata->getName());
            } catch (\Throwable) {
                throw $this->createNotFoundException('The relation target is not an API Platform resource.');
            }

            return $metadata->getName();
        }

        throw $this->createNotFoundException(sprintf('Relation entity "%s" was not found.', $shortName));
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @return list<string>
     */
    private function resolveLabelFields(ClassMetadata $metadata, string $requestedFields): array
    {
        $fields = array_values(array_unique(array_filter(array_map('trim', explode(',', $requestedFields)))));
        $fields = array_values(array_filter($fields, static fn (string $field): bool => $metadata->hasField($field)));

        if ($fields !== []) {
            return $fields;
        }

        $defaults = $metadata->getIdentifierFieldNames();
        foreach ($metadata->getFieldNames() as $field) {
            if (in_array($metadata->getTypeOfField($field), ['string', 'text'], true)) {
                $defaults[] = $field;
                break;
            }
        }

        return array_values(array_unique($defaults));
    }

    /** @param list<string> $labelFields */
    private function buildLabel(object $entity, array $labelFields): string
    {
        $parts = [];
        foreach ($labelFields as $field) {
            try {
                $value = $this->propertyAccessor->getValue($entity, $field);
            } catch (\Throwable) {
                continue;
            }

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i');
            }

            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = (string) $value;
            }
        }

        return implode(' ', $parts);
    }
}
