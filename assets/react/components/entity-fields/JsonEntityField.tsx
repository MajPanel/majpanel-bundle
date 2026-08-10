import type { EntityFieldInputProps } from './types';

export default function JsonEntityField({ field, value, onChange }: EntityFieldInputProps) {
    return <textarea
        className="min-h-28 w-full rounded border px-3 py-2"
        required={field.required}
        value={String(value ?? '')}
        onChange={(event) => onChange(event.target.value)}
    />;
}
