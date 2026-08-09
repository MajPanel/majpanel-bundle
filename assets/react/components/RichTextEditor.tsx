import { CKEditor } from '@ckeditor/ckeditor5-react';
import {
    Alignment,
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    Fullscreen,
    Heading,
    Italic,
    Link,
    List,
    Paragraph,
    PasteFromOffice,
    RemoveFormat,
    SourceEditing,
    Strikethrough,
    Table,
    TableToolbar,
    Underline,
} from 'ckeditor5';
import { Box, FormHelperText, FormLabel } from '@mui/material';
import 'ckeditor5/ckeditor5.css';

type RichTextEditorProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
    error?: boolean;
    helperText?: string | null;
};

export default function RichTextEditor({
    label,
    value,
    onChange,
    required = false,
    error = false,
    helperText = null,
}: RichTextEditorProps) {
    const editorClasses = [
        '[&_.ck-editor\\_\\_editable\\_inline]:min-h-[220px]',
        '[&_.ck-editor\\_\\_editable\\_inline]:max-h-[480px]',
        '[&_.ck-editor\\_\\_editable\\_inline]:overflow-y-auto',
        '[&_.ck-toolbar]:!border-black/25',
        '[&_.ck-editor\\_\\_main>.ck-editor\\_\\_editable]:!border-black/25',
        'hover:[&_.ck-toolbar]:!border-black/85',
        'hover:[&_.ck-editor\\_\\_main>.ck-editor\\_\\_editable]:!border-black/85',
        error ? '[&_.ck-toolbar]:!border-[#d32f2f]' : '',
        error ? '[&_.ck-editor\\_\\_main>.ck-editor\\_\\_editable]:!border-[#d32f2f]' : '',
    ].filter(Boolean).join(' ');

    return (
        <Box className={editorClasses}>
            <FormLabel required={required} error={error} sx={{ display: 'block', mb: 1 }}>
                {label}
            </FormLabel>
            <CKEditor
                editor={ClassicEditor}
                data={value}
                config={{
                    licenseKey: 'GPL',
                    plugins: [
                        Essentials,
                        Fullscreen,
                        Paragraph,
                        Heading,
                        Bold,
                        Italic,
                        Underline,
                        Strikethrough,
                        Link,
                        List,
                        Alignment,
                        BlockQuote,
                        Table,
                        TableToolbar,
                        PasteFromOffice,
                        RemoveFormat,
                        SourceEditing,
                    ],
                    toolbar: {
                        items: [
                            'undo',
                            'redo',
                            '|',
                            'heading',
                            '|',
                            'bold',
                            'italic',
                            'underline',
                            'strikethrough',
                            'removeFormat',
                            '|',
                            'link',
                            'bulletedList',
                            'numberedList',
                            'alignment',
                            'blockQuote',
                            'insertTable',
                            '|',
                            'sourceEditing',
                            'fullscreen',
                        ],
                        shouldNotGroupWhenFull: false,
                    },
                    link: {
                        addTargetToExternalLinks: true,
                        defaultProtocol: 'https://',
                    },
                    table: {
                        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
                    },
                }}
                onChange={(_event, editor) => onChange(editor.getData())}
            />
            {helperText && <FormHelperText error={error}>{helperText}</FormHelperText>}
        </Box>
    );
}
