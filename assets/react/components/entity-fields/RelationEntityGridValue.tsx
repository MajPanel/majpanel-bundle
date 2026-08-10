import type { EntityField } from './types';

type Props = {
    field: EntityField;
    value: unknown;
};

function identifierFromIri(value: string): string {
    const identifier = value.split('/').filter(Boolean).at(-1);

    return identifier ? decodeURIComponent(identifier) : value;
}

function relationLabel(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return identifierFromIri(value);
    if (typeof value !== 'object') return String(value);

    const relation = value as Record<string, unknown>;
    const stringValue = relation.__string;
    if (stringValue !== null && stringValue !== undefined && String(stringValue) !== '') {
        return String(stringValue);
    }

    const identifier = relation.id ?? relation['@id'];

    return typeof identifier === 'string' ? identifierFromIri(identifier) : String(identifier ?? '');
}

function relationGridUrl(field: EntityField): string | null {
    const targetApiUrl = field.relation?.targetApiUrl;
    if (!targetApiUrl) return null;

    const pathname = new URL(targetApiUrl, window.location.origin).pathname;
    const slug = pathname.split('/').filter(Boolean).at(-1);

    return slug ? `/majpanel/admin/${encodeURIComponent(slug)}` : null;
}

export default function RelationEntityGridValue({ field, value }: Props) {
    const values = Array.isArray(value) ? value : [value];
    const gridUrl = relationGridUrl(field);

    return <>{values.map((item, index) => {
        const label = relationLabel(item);
        if (!label) return null;

        return <span key={`${label}-${index}`}>
            {index > 0 && ', '}
            {gridUrl
                ? <a className="font-medium text-blue-700 hover:underline" href={gridUrl}>{label}</a>
                : label}
        </span>;
    })}</>;
}
