export type EntityFieldKind =
    | 'text'
    | 'textarea'
    | 'number'
    | 'boolean'
    | 'date'
    | 'datetime'
    | 'json'
    | 'relation';

export type EntityField = {
    name: string;
    label: string;
    kind: EntityFieldKind;
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

export type EntityFieldInputProps = {
    field: EntityField;
    value: unknown;
    onChange: (value: unknown) => void;
};
