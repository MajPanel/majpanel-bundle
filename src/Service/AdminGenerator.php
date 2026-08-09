<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Service;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\UnicodeString;

final class AdminGenerator
{
    private const MANIFEST_FILE = '/config/majpanel_entities.json';
    private const MENU_FILE = '/templates/admin/_generated_menu.html.twig';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly RouterInterface $router,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly string $entityNamespace,
    ) {
    }

    /**
     * @return array{entity: string, component: string, template: string, api_url: string, fields: int, associations: list<string>}
     */
    public function generate(string $entityName): array
    {
        $className = $this->resolveEntityClass($entityName);
        $metadata = $this->entityManager->getClassMetadata($className);
        $api = $this->readApiResource($className);
        $shortName = (new \ReflectionClass($className))->getShortName();
        $componentName = $shortName.'Admin';
        $slug = $this->slugFromApiUrl($api['url'], $shortName);
        $title = $api['short_name'] ?? $shortName;
        $title = $this->humanize($this->pluralize($title));
        $fields = $this->buildFields($metadata, $className);
        $idField = $metadata->getIdentifierFieldNames()[0] ?? 'id';

        $componentPath = $this->projectDir.'/assets/react/controllers/'.$componentName.'.tsx';
        $templatePath = $this->projectDir.'/templates/admin/'.$slug.'/index.html.twig';

        $this->filesystem->dumpFile(
            $componentPath,
            $this->renderReactComponent(
                $componentName,
                $title,
                $idField,
                $fields,
                $api,
                $this->readExistingRelationLabelFields($componentPath),
                $this->readExistingAdminConfig($componentPath),
            ),
        );
        $this->filesystem->dumpFile(
            $templatePath,
            $this->renderEntityTemplate($componentName, $title, $api['url']),
        );

        $manifest = $this->readManifest();
        $manifest[$className] = [
            'class' => $className,
            'name' => $shortName,
            'label' => $title,
            'slug' => $slug,
            'component' => $componentName,
            'api_url' => $api['url'],
        ];
        ksort($manifest);
        $this->writeManifest($manifest);
        $this->writeMenu($manifest);

        return [
            'entity' => $className,
            'component' => $componentPath,
            'template' => $templatePath,
            'api_url' => $api['url'],
            'fields' => count($fields),
            'associations' => $metadata->getAssociationNames(),
        ];
    }

    /** @return array{entity: string, component: string|null, template: string|null} */
    public function delete(string $entityName): array
    {
        $className = $this->resolveEntityClass($entityName);
        $manifest = $this->readManifest();
        $entry = $manifest[$className] ?? null;

        if ($entry === null) {
            return ['entity' => $className, 'component' => null, 'template' => null];
        }

        $componentPath = $this->projectDir.'/assets/react/controllers/'.$entry['component'].'.tsx';
        $templateDirectory = $this->projectDir.'/templates/admin/'.$entry['slug'];
        $this->filesystem->remove([$componentPath, $templateDirectory]);

        unset($manifest[$className]);
        $this->writeManifest($manifest);
        $this->writeMenu($manifest);

        return [
            'entity' => $className,
            'component' => $componentPath,
            'template' => $templateDirectory,
        ];
    }

    private function resolveEntityClass(string $entityName): string
    {
        $entityName = trim(trim($entityName), '\\');
        if ($entityName === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $entityName) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid entity name "%s".', $entityName));
        }

        $className = str_contains($entityName, '\\')
            ? $entityName
            : trim($this->entityNamespace, '\\').'\\'.$entityName;
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(sprintf('Entity class "%s" was not found.', $className));
        }

        if ($this->entityManager->getMetadataFactory()->isTransient($className)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" is not a Doctrine entity.', $className));
        }

        return $className;
    }

    /** @return array{url: string, short_name: string|null, create: bool, update: bool, delete: bool} */
    private function readApiResource(string $className): array
    {
        try {
            $resources = $this->resourceMetadataFactory->create($className);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(sprintf('Entity "%s" is not an API Platform resource.', $className), 0, $exception);
        }

        $hasCollection = false;
        $shortName = null;

        foreach ($resources as $resource) {
            $shortName ??= $resource->getShortName();
            foreach ($resource->getOperations() ?? [] as $operation) {
                $hasCollection = $hasCollection || $operation instanceof GetCollection;
            }
        }

        if (!$hasCollection) {
            throw new \InvalidArgumentException(sprintf('API resource "%s" has no GET collection operation.', $className));
        }

        $url = $this->findCollectionUrl($className);
        $capabilities = $this->findAdminCapabilities($className, $url);

        return [
            'url' => $url,
            'short_name' => $shortName,
            'create' => $capabilities['create'],
            'update' => $capabilities['update'],
            'delete' => $capabilities['delete'],
        ];
    }

    private function findCollectionUrl(string $className): string
    {
        if (!method_exists($this->router, 'getRouteCollection')) {
            throw new \RuntimeException('The configured router cannot expose the API route collection.');
        }

        $collectionCandidates = [];
        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            if ($route->getDefault('_api_resource_class') !== $className) {
                continue;
            }

            if (!str_contains((string) $routeName, 'get_collection')) {
                continue;
            }

            $path = str_replace('.{_format}', '', $route->getPath());
            if (str_starts_with($path, '/api/admin/')) {
                return $path;
            }

            $collectionCandidates[] = $path;
        }

        if ($collectionCandidates !== []) {
            throw new \RuntimeException(sprintf(
                'The resource "%s" has collection routes, but no protected /api/admin route. Found: %s',
                $className,
                implode(', ', $collectionCandidates),
            ));
        }

        throw new \RuntimeException(sprintf('The admin GET collection route for "%s" was not found.', $className));
    }

    /** @return array{create: bool, update: bool, delete: bool} */
    private function findAdminCapabilities(string $className, string $collectionUrl): array
    {
        $canCreate = false;
        $canUpdate = false;
        $canDelete = false;

        if (!method_exists($this->router, 'getRouteCollection')) {
            return ['create' => false, 'update' => false, 'delete' => false];
        }

        foreach ($this->router->getRouteCollection() as $route) {
            if ($route->getDefault('_api_resource_class') !== $className) {
                continue;
            }

            $path = str_replace('.{_format}', '', $route->getPath());
            $methods = array_map('strtoupper', $route->getMethods());
            $isCollection = $path === $collectionUrl;
            $isItem = str_starts_with($path, $collectionUrl.'/');

            $canCreate = $canCreate || ($isCollection && in_array('POST', $methods, true));
            $canUpdate = $canUpdate || ($isItem && (in_array('PATCH', $methods, true) || in_array('PUT', $methods, true)));
            $canDelete = $canDelete || ($isItem && in_array('DELETE', $methods, true));
        }

        return ['create' => $canCreate, 'update' => $canUpdate, 'delete' => $canDelete];
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @return list<array<string, mixed>>
     */
    private function buildFields(ClassMetadata $metadata, string $className): array
    {
        $reflection = new \ReflectionClass($className);
        $fields = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            $mapping = $metadata->getFieldMapping($fieldName);
            $doctrineType = $metadata->getTypeOfField($fieldName) ?? 'string';
            $kind = $this->fieldKind($doctrineType);
            $isIdentifier = $metadata->isIdentifier($fieldName);
            $isTimestamp = in_array(strtolower($fieldName), ['createdat', 'updatedat', 'createdon', 'updatedon'], true);
            $editable = !$isIdentifier && !$isTimestamp && $reflection->hasMethod('set'.ucfirst($fieldName));
            $field = [
                'name' => $fieldName,
                'label' => $this->humanize($fieldName),
                'kind' => $kind,
                'valueType' => $this->valueType($doctrineType),
                'required' => $editable && !$mapping->nullable,
                'editable' => $editable,
            ];

            if ($mapping->length !== null) {
                $field['maxLength'] = $mapping->length;
            }
            if ($kind === 'number') {
                $field['step'] = $this->numberStep($doctrineType, $mapping->scale);
            }

            $fields[] = $field;
        }

        foreach ($metadata->getAssociationMappings() as $association) {
            $fieldName = $association->fieldName;
            $targetClass = $association->targetEntity;
            $targetMetadata = $this->entityManager->getClassMetadata($targetClass);
            $targetApi = $this->readApiResource($targetClass);
            $targetShortName = (new \ReflectionClass($targetClass))->getShortName();
            $multiple = $association->isToMany();
            $editable = $this->associationIsEditable($reflection, $fieldName, $multiple);
            $required = false;

            if ($editable && $association->isToOneOwningSide()) {
                $required = $association->joinColumns !== []
                    && array_reduce(
                        $association->joinColumns,
                        static fn (bool $required, $joinColumn): bool => $required && $joinColumn->nullable === false,
                        true,
                    );
            }

            $fields[] = [
                'name' => $fieldName,
                'label' => $this->humanize($fieldName),
                'kind' => 'relation',
                'valueType' => 'relation',
                'required' => $required,
                'editable' => $editable,
                'relation' => [
                    'type' => $this->associationType($association->type()),
                    'multiple' => $multiple,
                    'target' => $targetShortName,
                    'targetApiUrl' => $targetApi['url'],
                    'optionsUrl' => '/api/admin/majpanel/relation-options/'.$targetShortName,
                    'labelFields' => $this->defaultRelationLabelFields($targetMetadata),
                ],
            ];
        }

        return $fields;
    }

    private function associationIsEditable(\ReflectionClass $reflection, string $fieldName, bool $multiple): bool
    {
        if ($reflection->hasMethod('set'.ucfirst($fieldName))) {
            return true;
        }

        if (!$multiple) {
            return false;
        }

        $singular = $this->singularize($fieldName);

        return $reflection->hasMethod('add'.ucfirst($singular))
            && $reflection->hasMethod('remove'.ucfirst($singular));
    }

    private function associationType(int $type): string
    {
        return match ($type) {
            ClassMetadata::ONE_TO_ONE => 'oneToOne',
            ClassMetadata::MANY_TO_ONE => 'manyToOne',
            ClassMetadata::ONE_TO_MANY => 'oneToMany',
            ClassMetadata::MANY_TO_MANY => 'manyToMany',
            default => throw new \LogicException(sprintf('Unsupported Doctrine association type "%d".', $type)),
        };
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @return list<string>
     */
    private function defaultRelationLabelFields(ClassMetadata $metadata): array
    {
        $labelFields = $metadata->getIdentifierFieldNames();

        foreach ($metadata->getFieldNames() as $fieldName) {
            if (in_array($metadata->getTypeOfField($fieldName), ['string', 'text'], true)) {
                $labelFields[] = $fieldName;
                break;
            }
        }

        return array_values(array_unique($labelFields));
    }

    private function fieldKind(string $doctrineType): string
    {
        return match ($doctrineType) {
            'text' => 'textarea',
            'smallint', 'integer', 'bigint', 'decimal', 'float' => 'number',
            'boolean' => 'boolean',
            'date', 'date_immutable' => 'date',
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => 'datetime',
            'json', 'array', 'simple_array' => 'json',
            default => 'text',
        };
    }

    private function valueType(string $doctrineType): string
    {
        return match ($doctrineType) {
            'smallint', 'integer', 'bigint' => 'integer',
            'decimal' => 'decimal',
            'float' => 'number',
            'boolean' => 'boolean',
            'date', 'date_immutable' => 'date',
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => 'datetime',
            'json', 'array', 'simple_array' => 'json',
            default => 'string',
        };
    }

    private function numberStep(string $doctrineType, ?int $scale): float|int
    {
        if (in_array($doctrineType, ['smallint', 'integer', 'bigint'], true)) {
            return 1;
        }

        if ($scale !== null) {
            return $scale > 0 ? 10 ** -$scale : 1;
        }

        return 0.01;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array{url: string, short_name: string|null, create: bool, update: bool, delete: bool} $api
     * @param array<string, list<string>> $existingRelationLabelFields
     * @param array<string, mixed> $existingAdminConfig
     */
    private function renderReactComponent(
        string $componentName,
        string $title,
        string $idField,
        array $fields,
        array $api,
        array $existingRelationLabelFields,
        array $existingAdminConfig,
    ): string
    {
        $fieldsJson = json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $relationLabelFields = [];
        foreach ($fields as $field) {
            if (($field['kind'] ?? null) === 'relation') {
                $fieldName = $field['name'];
                $relationLabelFields[$fieldName] = $existingRelationLabelFields[$fieldName]
                    ?? $field['relation']['labelFields'];
            }
        }
        $relationLabelFieldsJson = json_encode(
            $relationLabelFields === [] ? (object) [] : $relationLabelFields,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $adminConfig = [
            'fields' => [],
            'actions' => [
                'create' => $api['create'],
                'edit' => $api['update'],
                'delete' => $api['delete'],
            ],
        ];
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $adminConfig['fields'][$fieldName] = [
                'editable' => (bool) $field['editable'],
                'showInGrid' => true,
                'searchable' => true,
            ];

            foreach (['editable', 'showInGrid', 'searchable'] as $option) {
                $existingValue = $existingAdminConfig['fields'][$fieldName][$option] ?? null;
                if (is_bool($existingValue)) {
                    $adminConfig['fields'][$fieldName][$option] = $existingValue;
                }
            }
        }
        foreach (['create', 'edit', 'delete'] as $action) {
            $existingValue = $existingAdminConfig['actions'][$action] ?? null;
            if (is_bool($existingValue)) {
                $adminConfig['actions'][$action] = $existingValue;
            }
        }
        $adminConfigJson = json_encode(
            $adminConfig,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $titleJson = json_encode($title, JSON_THROW_ON_ERROR);
        $idJson = json_encode($idField, JSON_THROW_ON_ERROR);
        $canCreate = $this->boolLiteral($api['create']);
        $canUpdate = $this->boolLiteral($api['update']);
        $canDelete = $this->boolLiteral($api['delete']);

        return <<<TSX
// Generated by `php bin/console majpanel`. Changes may be overwritten.
import EntityCrudGrid, { type EntityAdminConfig, type EntityField } from '../components/EntityCrudGrid';

type {$componentName}Props = {
    apiUrl: string;
};

// Customize the text shown by relation select boxes here.
// Example: category: ['id', 'name', 'family'] renders "12 Majid Kazerooni".
// majpanel:relation-labels:start
const relationLabelFields: Record<string, string[]> = {$relationLabelFieldsJson};
// majpanel:relation-labels:end

// Choose editable form fields, visible grid columns, searchable fields, and row actions.
// majpanel:admin-config:start
const adminConfig: EntityAdminConfig = {$adminConfigJson};
// majpanel:admin-config:end

const generatedFields: EntityField[] = {$fieldsJson};

const fields = generatedFields.map((field): EntityField => ({
    ...field,
    editable: adminConfig.fields[field.name]?.editable ?? field.editable,
    showInGrid: adminConfig.fields[field.name]?.showInGrid ?? true,
    searchable: adminConfig.fields[field.name]?.searchable ?? true,
    ...(field.kind === 'relation' && field.relation
        ? { relation: {
            ...field.relation,
            labelFields: relationLabelFields[field.name] ?? field.relation.labelFields,
        } }
        : {}),
}));

export default function {$componentName}({ apiUrl }: {$componentName}Props) {
    return (
        <EntityCrudGrid
            title={$titleJson}
            apiUrl={apiUrl}
            idField={$idJson}
            fields={fields}
            canCreate={{$canCreate} && adminConfig.actions.create}
            canUpdate={{$canUpdate} && adminConfig.actions.edit}
            canDelete={{$canDelete} && adminConfig.actions.delete}
        />
    );
}
TSX;
    }

    /** @return array<string, mixed> */
    private function readExistingAdminConfig(string $componentPath): array
    {
        if (!is_file($componentPath)) {
            return [];
        }

        $contents = file_get_contents($componentPath);
        if ($contents === false || preg_match(
            '/\/\/ majpanel:admin-config:start\s*const adminConfig:[^=]+=(.*?);\s*\/\/ majpanel:admin-config:end/s',
            $contents,
            $matches,
        ) !== 1) {
            return [];
        }

        try {
            $decoded = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, list<string>> */
    private function readExistingRelationLabelFields(string $componentPath): array
    {
        if (!is_file($componentPath)) {
            return [];
        }

        $contents = file_get_contents($componentPath);
        if ($contents === false || preg_match(
            '/\/\/ majpanel:relation-labels:start\s*const relationLabelFields:[^=]+=(.*?);\s*\/\/ majpanel:relation-labels:end/s',
            $contents,
            $matches,
        ) !== 1) {
            return [];
        }

        try {
            $decoded = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->parseTypeScriptRelationLabelFields($matches[1]);
        }

        if (!is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $field => $labelFields) {
            if (!is_string($field) || !is_array($labelFields)) {
                continue;
            }

            $validFields = array_values(array_filter($labelFields, 'is_string'));
            if ($validFields !== []) {
                $labels[$field] = $validFields;
            }
        }

        return $labels;
    }

    /** @return array<string, list<string>> */
    private function parseTypeScriptRelationLabelFields(string $configuration): array
    {
        if (preg_match_all(
            '/["\']?([A-Za-z_][A-Za-z0-9_]*)["\']?\s*:\s*\[(.*?)\]/s',
            $configuration,
            $entries,
            PREG_SET_ORDER,
        ) === false) {
            return [];
        }

        $labels = [];
        foreach ($entries as $entry) {
            if (preg_match_all('/["\']([^"\']+)["\']/', $entry[2], $fieldMatches) === false) {
                continue;
            }

            $fields = array_values(array_unique(array_map('trim', $fieldMatches[1])));
            if ($fields !== []) {
                $labels[$entry[1]] = $fields;
            }
        }

        return $labels;
    }

    private function renderEntityTemplate(string $componentName, string $title, string $apiUrl): string
    {
        return sprintf(
            "{# Generated by `php bin/console majpanel`. Changes may be overwritten. #}\n".
            "{%% extends '@Majpanel/admin/index.html.twig' %%}\n\n".
            "{%% block admin_title %%}%s{%% endblock %%}\n\n".
            "{%% block admin_content %%}\n".
            "    <div {{ react_component('%s', { apiUrl: '%s' }) }}></div>\n".
            "{%% endblock %%}\n",
            $title,
            $componentName,
            $apiUrl,
        );
    }

    /** @return array<string, array{class: string, name: string, label: string, slug: string, component: string, api_url: string}> */
    private function readManifest(): array
    {
        $path = $this->projectDir.self::MANIFEST_FILE;
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the MajPanel entity manifest.');
        }

        /** @var array<string, array{class: string, name: string, label: string, slug: string, component: string, api_url: string}> $manifest */
        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $manifest;
    }

    /** @param array<string, array{class: string, name: string, label: string, slug: string, component: string, api_url: string}> $manifest */
    private function writeManifest(array $manifest): void
    {
        $this->filesystem->dumpFile(
            $this->projectDir.self::MANIFEST_FILE,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
        );
    }

    /** @param array<string, array{class: string, name: string, label: string, slug: string, component: string, api_url: string}> $manifest */
    private function writeMenu(array $manifest): void
    {
        $menu = "{# Generated by `php bin/console majpanel`. #}\n";
        foreach ($manifest as $entry) {
            $label = htmlspecialchars($entry['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $slug = addslashes($entry['slug']);
            $menu .= <<<TWIG
<a
    class="flex items-center gap-3 rounded-md border p-3 no-underline max-[700px]:flex-col max-[700px]:gap-1 max-[700px]:px-1 max-[700px]:py-2.5 max-[700px]:text-xs {{ app.request.attributes.get('entity') == '{$slug}' ? 'border-[#90caf9] bg-[#e3f2fd] text-[#1565c0]' : 'border-gray-200 text-gray-700 hover:border-[#90caf9] hover:bg-[#e3f2fd] hover:text-[#1565c0]' }}"
    href="{{ path('majpanel_admin_entity', { entity: '{$slug}' }) }}"
>
    <span class="text-xl" aria-hidden="true">▣</span>
    <span>{$label}</span>
</a>
TWIG;
        }

        $this->filesystem->dumpFile($this->projectDir.self::MENU_FILE, $menu);
    }

    private function slugFromApiUrl(string $apiUrl, string $fallback): string
    {
        $slug = basename(trim($apiUrl, '/'));

        return $slug !== '' ? $slug : $this->pluralize((new UnicodeString($fallback))->snake()->toString());
    }

    private function humanize(string $value): string
    {
        return (new UnicodeString($value))->snake()->replace('_', ' ')->title(true)->toString();
    }

    private function pluralize(string $value): string
    {
        if (preg_match('/(?:s|x|z|ch|sh)$/i', $value) === 1) {
            return $value.'es';
        }
        if (preg_match('/[^aeiou]y$/i', $value) === 1) {
            return substr($value, 0, -1).'ies';
        }

        return $value.'s';
    }

    private function singularize(string $value): string
    {
        if (preg_match('/ies$/i', $value) === 1) {
            return substr($value, 0, -3).'y';
        }
        if (preg_match('/(?:ches|shes|ses|xes|zes)$/i', $value) === 1) {
            return substr($value, 0, -2);
        }
        if (str_ends_with(strtolower($value), 's')) {
            return substr($value, 0, -1);
        }

        return $value;
    }

    private function boolLiteral(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
