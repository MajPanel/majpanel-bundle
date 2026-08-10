import type { EntityFieldInputProps } from './types';

export default function NumberEntityField({ field, value, onChange }: EntityFieldInputProps) {
    return <input
        className="w-full rounded border px-3 py-2"
        type="number"
        required={field.required}
        step={field.step}
        value={String(value ?? '')}
        onChange={(event) => onChange(event.target.value)}
    />;
}
