import RelationAutocomplete from '../RelationAutocomplete';
import TextEntityField from './TextEntityField';
import type { EntityFieldInputProps } from './types';

export default function RelationEntityField({ field, value, onChange }: EntityFieldInputProps) {
    if (!field.relation) {
        return <TextEntityField field={field} value={value} onChange={onChange} />;
    }

    return <RelationAutocomplete
        label={field.label}
        optionsUrl={field.relation.optionsUrl}
        labelFields={field.relation.labelFields}
        multiple={field.relation.multiple}
        required={field.required}
        value={(field.relation.multiple
            ? (Array.isArray(value) ? value : [])
            : String(value ?? '')) as string | string[]}
        onChange={onChange}
    />;
}
