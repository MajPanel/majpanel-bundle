import { useEffect, useMemo, useState, type FormEvent } from 'react';
import EntityFieldInput from './entity-fields/EntityFieldInput';
import type { EntityField } from './entity-fields/types';

export type { EntityField, EntityFieldKind } from './entity-fields/types';

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
type SortState = {
    fieldName: string;
    direction: 'ascending' | 'descending';
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

function compareFieldValues(left: unknown, right: unknown, field: EntityField): number {
    if (left === right) return 0;
    if (left === null || left === undefined || left === '') return 1;
    if (right === null || right === undefined || right === '') return -1;

    if (field.kind === 'number') {
        return Number(left) - Number(right);
    }
    if (field.kind === 'boolean') {
        return Number(Boolean(left)) - Number(Boolean(right));
    }

    return displayValue(left).localeCompare(displayValue(right), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
}

function EditIcon() {
    return <svg aria-hidden="true" className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21h-10.5A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
    </svg>;
}

function DeleteIcon() {
    return <svg aria-hidden="true" className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0V4.477c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
    </svg>;
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
    const [sort, setSort] = useState<SortState | null>(null);
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
    const displayedRows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        const searchable = fields.filter((field) => field.searchable !== false);
        const matchingRows = query
            ? rows.filter((row) => searchable.some((field) => displayValue(row[field.name]).toLocaleLowerCase().includes(query)))
            : rows;

        if (!sort) return matchingRows;
        const sortField = fields.find((field) => field.name === sort.fieldName);
        if (!sortField) return matchingRows;
        const direction = sort.direction === 'ascending' ? 1 : -1;

        return matchingRows
            .map((row, index) => ({ row, index }))
            .sort((left, right) => (
                compareFieldValues(left.row[sort.fieldName], right.row[sort.fieldName], sortField) * direction
                || left.index - right.index
            ))
            .map(({ row }) => row);
    }, [fields, rows, search, sort]);

    const toggleSort = (fieldName: string) => {
        setSort((currentSort) => ({
            fieldName,
            direction: currentSort?.fieldName === fieldName && currentSort.direction === 'ascending'
                ? 'descending'
                : 'ascending',
        }));
    };

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
                <thead className="bg-slate-50"><tr>{visibleFields.map((field) => {
                    const direction = sort?.fieldName === field.name ? sort.direction : undefined;

                    return <th
                        aria-sort={direction ?? 'none'}
                        className="border-b p-0 font-semibold"
                        key={field.name}
                    >
                        <button
                            className="flex w-full cursor-pointer items-center justify-between gap-2 px-4 py-3 text-left hover:bg-slate-100"
                            type="button"
                            onClick={() => toggleSort(field.name)}
                        >
                            <span>{field.label}</span>
                            <span aria-hidden="true" className={direction ? 'text-blue-600' : 'text-slate-400'}>
                                {direction === 'ascending' ? '↑' : direction === 'descending' ? '↓' : '↕'}
                            </span>
                        </button>
                    </th>;
                })}{(canUpdate || canDelete) && <th className="border-b px-4 py-3">Actions</th>}</tr></thead>
                <tbody>
                    {loading && <tr><td className="px-4 py-6 text-center" colSpan={visibleFields.length + 1}>Loading…</td></tr>}
                    {!loading && displayedRows.map((row) => <tr className="border-b last:border-0" key={String(row[idField])}>
                        {visibleFields.map((field) => <td className="max-w-xs truncate px-4 py-3" key={field.name}>{displayValue(row[field.name])}</td>)}
                        {(canUpdate || canDelete) && <td className="whitespace-nowrap px-4 py-3">
                            {canUpdate && <button
                                aria-label={`Edit ${title} record`}
                                title="Edit"
                                type="button"
                                className="mr-2 inline-flex size-9 cursor-pointer items-center justify-center rounded border border-blue-200 bg-blue-50 text-blue-700 hover:border-blue-300 hover:bg-blue-100"
                                onClick={() => openForm(row)}
                            ><EditIcon /></button>}
                            {canDelete && <button
                                aria-label={`Delete ${title} record`}
                                title="Delete"
                                type="button"
                                className="inline-flex size-9 cursor-pointer items-center justify-center rounded border border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100"
                                onClick={() => void remove(row)}
                            ><DeleteIcon /></button>}
                        </td>}
                    </tr>)}
                    {!loading && displayedRows.length === 0 && <tr><td className="px-4 py-6 text-center text-slate-500" colSpan={visibleFields.length + 1}>No records found.</td></tr>}
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
                    <EntityFieldInput
                        field={field}
                        value={values[field.name]}
                        onChange={(value) => setValues((currentValues) => ({ ...currentValues, [field.name]: value }))}
                    />
                </label>)}
                <div className="flex justify-end gap-3 pt-2"><button type="button" className="rounded border px-4 py-2" onClick={() => setEditing(undefined)}>Cancel</button><button className="rounded bg-blue-600 px-4 py-2 text-white" type="submit">Save</button></div>
            </form>
        </div>}
    </section>;
}
