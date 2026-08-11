import type { EntityFieldInputProps } from './types';

export default function DateEntityField({ field, value, onChange }: EntityFieldInputProps) {
    return <input
        className="w-full rounded border px-3 py-2"
        type="date"
        required={field.required}
        value={String(value ?? '')}
        onChange={(event) => onChange(event.target.value)}
    />;
}
