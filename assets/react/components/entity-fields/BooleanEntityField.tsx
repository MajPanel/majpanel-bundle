import type { EntityFieldInputProps } from './types';

export default function BooleanEntityField({ value, onChange }: EntityFieldInputProps) {
    return <input
        type="checkbox"
        checked={Boolean(value)}
        onChange={(event) => onChange(event.target.checked)}
    />;
}
