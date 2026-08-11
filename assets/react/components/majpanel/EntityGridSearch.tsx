import type { EntityField } from './entity-fields/types';

export type EntityGridSearchProps = {
    fields: EntityField[];
    query: string;
    selectedField: string;
    loading: boolean;
    onQueryChange: (query: string) => void;
    onSelectedFieldChange: (fieldName: string) => void;
};

const SELECTABLE_KINDS = new Set<EntityField['kind']>(['text', 'number', 'date', 'datetime']);

export default function EntityGridSearch({
    fields,
    query,
    selectedField,
    loading,
    onQueryChange,
    onSelectedFieldChange,
}: EntityGridSearchProps) {
    const searchableFields = fields.filter((field) => (
        field.searchable !== false && SELECTABLE_KINDS.has(field.kind)
    ));

    return <div className="flex w-full max-w-2xl flex-wrap gap-2">
        <label className="sr-only" htmlFor="majpanel-search-field">Search field</label>
        <select
            id="majpanel-search-field"
            className="rounded border border-slate-300 bg-white px-3 py-2"
            value={selectedField}
            onChange={(event) => onSelectedFieldChange(event.target.value)}
        >
            <option value="">All fields</option>
            {searchableFields.map((field) => <option key={field.name} value={field.name}>{field.label}</option>)}
        </select>
        <label className="sr-only" htmlFor="majpanel-search-query">Search records</label>
        <input
            id="majpanel-search-query"
            className="min-w-64 flex-1 rounded border border-slate-300 px-3 py-2"
            type="search"
            value={query}
            onChange={(event) => onQueryChange(event.target.value)}
            placeholder={selectedField ? 'Search selected field…' : 'Search all fields…'}
            aria-busy={loading}
        />
    </div>;
}
