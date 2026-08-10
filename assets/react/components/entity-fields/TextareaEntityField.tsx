import RichTextEditor from '../RichTextEditor';
import type { EntityFieldInputProps } from './types';

export default function TextareaEntityField({ field, value, onChange }: EntityFieldInputProps) {
    return <RichTextEditor
        label={field.label}
        required={field.required}
        value={String(value ?? '')}
        onChange={onChange}
    />;
}
