import type { EntityFieldInputProps } from './types';

export default function DatetimeEntityField({ field, value, onChange }: EntityFieldInputProps) {
    return <input
        className="w-full rounded border px-3 py-2"
        type="datetime-local"
        required={field.required}
        value={String(value ?? '')}
        onChange={(event) => onChange(event.target.value)}
    />;
}
