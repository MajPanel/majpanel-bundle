import BooleanEntityField from './BooleanEntityField';
import DateEntityField from './DateEntityField';
import DatetimeEntityField from './DatetimeEntityField';
import JsonEntityField from './JsonEntityField';
import NumberEntityField from './NumberEntityField';
import RelationEntityField from './RelationEntityField';
import TextEntityField from './TextEntityField';
import TextareaEntityField from './TextareaEntityField';
import type { EntityFieldInputProps } from './types';

export default function EntityFieldInput(props: EntityFieldInputProps) {
    switch (props.field.kind) {
        case 'text':
            return <TextEntityField {...props} />;
        case 'textarea':
            return <TextareaEntityField {...props} />;
        case 'number':
            return <NumberEntityField {...props} />;
        case 'boolean':
            return <BooleanEntityField {...props} />;
        case 'date':
            return <DateEntityField {...props} />;
        case 'datetime':
            return <DatetimeEntityField {...props} />;
        case 'json':
            return <JsonEntityField {...props} />;
        case 'relation':
            return <RelationEntityField {...props} />;
    }
}
