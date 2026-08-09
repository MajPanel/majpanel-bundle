import { useEffect, useMemo, useState, type FormEvent } from 'react';
import RelationAutocomplete from './RelationAutocomplete';
import RichTextEditor from './RichTextEditor';

export type EntityField = {
    name: string;
    label: string;
    kind: 'text' | 'textarea' | 'number' | 'boolean' | 'date' | 'datetime' | 'json' | 'relation';
    valueType: string;
    required: boolean;
    editable: boolean;
    showInGrid?: boolean;
    searchable?: boolean;
    maxLength?: number;
    step?: number;
    relation?: {
        type?: 'oneToOne' | 'manyToOne' | 'oneToMany' | 'manyToMany';
        multiple: boolean;
        target?: string;
        targetApiUrl?: string;
        optionsUrl: string;
        labelFields: string[];
    };
};

export type EntityAdminConfig = {
    fields: Record<string, { editable: boolean; showInGrid: boolean; searchable: boolean }>;
    actions: { create: boolean; edit: boolean; delete: boolean };
};

type EntityRecord = Record<string, unknown>;
type CollectionResult = {
    rows: EntityRecord[];
    totalItems: number;
    hasPreviousPage: boolean;
    hasNextPage: boolean;
};
type Props = {
    title: string;
    apiUrl: string;
    idField: string;
    fields: EntityField[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
};

const jsonHeaders = { Accept: 'application/ld+json', 'Content-Type': 'application/ld+json' };

function collection(data: unknown): CollectionResult {
    if (Array.isArray(data)) {
        return { rows: data as EntityRecord[], totalItems: data.length, hasPreviousPage: false, hasNextPage: false };
    }
    if (data && typeof data === 'object') {
        const object = data as Record<string, unknown>;
        const members = object.member ?? object['hydra:member'];
        const rows = Array.isArray(members) ? members as EntityRecord[] : [];
        const rawTotalItems = object.totalItems ?? object['hydra:totalItems'];
        const totalItems = typeof rawTotalItems === 'number' ? rawTotalItems : Number(rawTotalItems ?? rows.length);
        const rawView = object.view ?? object['hydra:view'];
        const view = rawView && typeof rawView === 'object' ? rawView as Record<string, unknown> : {};

        return {
            rows,
            totalItems: Number.isFinite(totalItems) ? totalItems : rows.length,
            hasPreviousPage: Boolean(view.previous ?? view['hydra:previous']),
            hasNextPage: Boolean(view.next ?? view['hydra:next']),
        };
    }
    return { rows: [], totalItems: 0, hasPreviousPage: false, hasNextPage: false };
}

function paginatedUrl(apiUrl: string, page: number): string {
    const url = new URL(apiUrl, window.location.origin);
    url.searchParams.set('page', String(page));

    return url.origin === window.location.origin ? `${url.pathname}${url.search}${url.hash}` : url.toString();
}

function displayValue(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') {
        const object = value as Record<string, unknown>;
        return String(object.name ?? object.title ?? object.label ?? object.id ?? JSON.stringify(value));
    }
    return String(value);
}

function initialValues(fields: EntityField[], record?: EntityRecord): EntityRecord {
    return Object.fromEntries(fields.filter((field) => field.editable).map((field) => {
        const value = record?.[field.name];
        if (field.kind === 'relation') {
            const relationValue = (item: unknown): string => {
                if (typeof item === 'string') return item;
                if (item && typeof item === 'object') {
                    const object = item as Record<string, unknown>;
                    return String(object['@id'] ?? object.id ?? '');
                }
                return '';
            };
            return [field.name, field.relation?.multiple
                ? (Array.isArray(value) ? value.map(relationValue).filter(Boolean) : [])
                : relationValue(value)];
        }
        if (field.kind === 'boolean') return [field.name, Boolean(value)];
        if (field.kind === 'json' && value && typeof value === 'object') {
            return [field.name, JSON.stringify(value, null, 2)];
        }
        return [field.name, value ?? ''];
    }));
}

export default function EntityCrudGrid({ title, apiUrl, idField, fields, canCreate, canUpdate, canDelete }: Props) {
    const [rows, setRows] = useState<EntityRecord[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [totalItems, setTotalItems] = useState(0);
    const [hasPreviousPage, setHasPreviousPage] = useState(false);
    const [hasNextPage, setHasNextPage] = useState(false);
    const [editing, setEditing] = useState<EntityRecord | null | undefined>(undefined);
    const [values, setValues] = useState<EntityRecord>({});

    const load = async () => {
        setLoading(true);
        setError('');
        try {
            const response = await fetch(paginatedUrl(apiUrl, page), { headers: { Accept: 'application/ld+json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Request failed (${response.status})`);
            const result = collection(await response.json());
            setRows(result.rows);
            setTotalItems(result.totalItems);
            setHasPreviousPage(result.hasPreviousPage);
            setHasNextPage(result.hasNextPage);
        } catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Unable to load records.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { void load(); }, [apiUrl, page]);

    const visibleFields = fields.filter((field) => field.showInGrid !== false);
    const filteredRows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        if (!query) return rows;
        const searchable = fields.filter((field) => field.searchable !== false);
        return rows.filter((row) => searchable.some((field) => displayValue(row[field.name]).toLocaleLowerCase().includes(query)));
    }, [fields, rows, search]);

    const openForm = (record: EntityRecord | null) => {
        setEditing(record);
        setValues(initialValues(fields, record ?? undefined));
        setError('');
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const editingId = editing?.[idField];
        const url = editingId === undefined ? apiUrl : `${apiUrl}/${encodeURIComponent(String(editingId))}`;
        const payload = Object.fromEntries(fields.filter((field) => field.editable).map((field) => {
            let value = values[field.name];
            if (field.kind === 'relation') {
                if (field.relation?.multiple) {
                    value = Array.isArray(value)
                        ? value.filter((item): item is string => typeof item === 'string' && item.trim() !== '')
                        : [];
                } else if (typeof value !== 'string' || value.trim() === '') {
                    value = null;
                }
            }
            if (field.kind === 'number' && value !== '') value = Number(value);
            if (field.kind === 'json' && typeof value === 'string' && value !== '') value = JSON.parse(value);
            return [field.name, value];
        }));

        try {
            const response = await fetch(url, {
                method: editingId === undefined ? 'POST' : 'PATCH',
                headers: editingId === undefined ? jsonHeaders : { ...jsonHeaders, 'Content-Type': 'application/merge-patch+json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            if (!response.ok) throw new Error(`Save failed (${response.status})`);
            setEditing(undefined);
            await load();
        } catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Unable to save the record.');
        }
    };

    const remove = async (row: EntityRecord) => {
        if (!window.confirm('Delete this record?')) return;
        const response = await fetch(`${apiUrl}/${encodeURIComponent(String(row[idField]))}`, {
            method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/ld+json' },
        });
        if (response.ok && rows.length === 1 && page > 1) setPage((currentPage) => currentPage - 1);
        else if (response.ok) await load();
        else setError(`Delete failed (${response.status})`);
    };

    return <section className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
            {canCreate && <button className="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700" onClick={() => openForm(null)}>Create</button>}
        </div>
        <input className="w-full max-w-md rounded border border-slate-300 px-3 py-2" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search this page…" />
        {error && <div className="rounded border border-red-300 bg-red-50 p-3 text-red-700">{error}</div>}
        <div className="overflow-x-auto rounded border border-slate-200 bg-white">
            <table className="w-full border-collapse text-left text-sm">
                <thead className="bg-slate-50"><tr>{visibleFields.map((field) => <th className="border-b px-4 py-3 font-semibold" key={field.name}>{field.label}</th>)}{(canUpdate || canDelete) && <th className="border-b px-4 py-3">Actions</th>}</tr></thead>
                <tbody>
                    {loading && <tr><td className="px-4 py-6 text-center" colSpan={visibleFields.length + 1}>Loading…</td></tr>}
                    {!loading && filteredRows.map((row) => <tr className="border-b last:border-0" key={String(row[idField])}>
                        {visibleFields.map((field) => <td className="max-w-xs truncate px-4 py-3" key={field.name}>{displayValue(row[field.name])}</td>)}
                        {(canUpdate || canDelete) && <td className="whitespace-nowrap px-4 py-3">
                            {canUpdate && <button className="mr-2 cursor-pointer rounded border border-blue-200 bg-blue-50 px-3 py-1.5 font-medium text-blue-700 hover:border-blue-300 hover:bg-blue-100" onClick={() => openForm(row)}>Edit</button>}
                            {canDelete && <button className="cursor-pointer rounded border border-red-200 bg-red-50 px-3 py-1.5 font-medium text-red-700 hover:border-red-300 hover:bg-red-100" onClick={() => void remove(row)}>Delete</button>}
                        </td>}
                    </tr>)}
                    {!loading && filteredRows.length === 0 && <tr><td className="px-4 py-6 text-center text-slate-500" colSpan={visibleFields.length + 1}>No records found.</td></tr>}
                </tbody>
            </table>
        </div>
        <nav className="flex flex-wrap items-center justify-between gap-3" aria-label={`${title} pagination`}>
            <span className="text-sm text-slate-600">{totalItems} {totalItems === 1 ? 'record' : 'records'} · Page {page}</span>
            <div className="flex gap-2">
                <button className="cursor-pointer rounded border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled={loading || !hasPreviousPage} onClick={() => setPage((currentPage) => Math.max(1, currentPage - 1))}>Previous</button>
                <button className="cursor-pointer rounded border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled={loading || !hasNextPage} onClick={() => setPage((currentPage) => currentPage + 1)}>Next</button>
            </div>
        </nav>
        {editing !== undefined && <div className="majpanel-modal-backdrop" role="presentation" onMouseDown={() => setEditing(undefined)}>
            <form className="majpanel-modal space-y-4" onSubmit={(event) => void submit(event)} onMouseDown={(event) => event.stopPropagation()}>
                <h2 className="text-xl font-semibold">{editing ? `Edit ${title}` : `Create ${title}`}</h2>
                {fields.filter((field) => field.editable).map((field) => <label className="block" key={field.name}>
                    <span className="mb-1 block text-sm font-medium">{field.label}</span>
                    {field.kind === 'relation' && field.relation
                        ? <RelationAutocomplete
                            label={field.label}
                            optionsUrl={field.relation.optionsUrl}
                            labelFields={field.relation.labelFields}
                            multiple={field.relation.multiple}
                            required={field.required}
                            value={(field.relation.multiple
                                ? (Array.isArray(values[field.name]) ? values[field.name] : [])
                                : String(values[field.name] ?? '')) as string | string[]}
                            onChange={(value) => setValues({ ...values, [field.name]: value })}
                        />
                        : field.kind === 'textarea'
                            ? <RichTextEditor label={field.label} required={field.required} value={String(values[field.name] ?? '')} onChange={(value) => setValues({ ...values, [field.name]: value })} />
                            : field.kind === 'json'
                                ? <textarea className="min-h-28 w-full rounded border px-3 py-2" required={field.required} value={String(values[field.name] ?? '')} onChange={(event) => setValues({ ...values, [field.name]: event.target.value })} />
                        : field.kind === 'boolean'
                            ? <input type="checkbox" checked={Boolean(values[field.name])} onChange={(event) => setValues({ ...values, [field.name]: event.target.checked })} />
                            : <input className="w-full rounded border px-3 py-2" type={field.kind === 'number' ? 'number' : field.kind === 'date' ? 'date' : field.kind === 'datetime' ? 'datetime-local' : 'text'} required={field.required} maxLength={field.maxLength} step={field.step} value={String(values[field.name] ?? '')} onChange={(event) => setValues({ ...values, [field.name]: event.target.value })} />}
                </label>)}
                <div className="flex justify-end gap-3 pt-2"><button type="button" className="rounded border px-4 py-2" onClick={() => setEditing(undefined)}>Cancel</button><button className="rounded bg-blue-600 px-4 py-2 text-white" type="submit">Save</button></div>
            </form>
        </div>}
    </section>;
}
