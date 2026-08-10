import type { EntityFieldInputProps } from './types';

export default function TextEntityField({ field, value, onChange }: EntityFieldInputProps) {
    return <input
        className="w-full rounded border px-3 py-2"
        type="text"
        required={field.required}
        maxLength={field.maxLength}
        value={String(value ?? '')}
        onChange={(event) => onChange(event.target.value)}
    />;
}
