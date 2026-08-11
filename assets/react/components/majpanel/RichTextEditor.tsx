import { CKEditor } from '@ckeditor/ckeditor5-react';

import {
    Alignment,
    Autoformat,
    BlockQuote,
    Bold,
    ClassicEditor,
    Code,
    CodeBlock,
    Essentials,
    FindAndReplace,
    FontBackgroundColor,
    FontColor,
    FontFamily,
    FontSize,
    Fullscreen,
    Heading,
    Highlight,
    HorizontalLine,
    Image,
    ImageCaption,
    ImageInsert,
    ImageResize,
    ImageStyle,
    ImageToolbar,
    ImageUpload,
    Indent,
    IndentBlock,
    Italic,
    Link,
    List,
    ListProperties,
    MediaEmbed,
    PageBreak,
    Paragraph,
    PasteFromOffice,
    RemoveFormat,
    SourceEditing,
    SpecialCharacters,
    SpecialCharactersEssentials,
    Strikethrough,
    Subscript,
    Superscript,
    Table,
    TableCaption,
    TableCellProperties,
    TableColumnResize,
    TableProperties,
    TableToolbar,
    Underline,
} from 'ckeditor5';

import {
    Box,
    FormHelperText,
    FormLabel,
} from '@mui/material';

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
        '[&_.ck-editor__editable_inline]:min-h-[220px]',
        '[&_.ck-editor__editable_inline]:max-h-[480px]',
        '[&_.ck-editor__editable_inline]:overflow-y-auto',

        '[&_.ck-toolbar]:!border-black/25',
        '[&_.ck-editor__main>.ck-editor__editable]:!border-black/25',

        'hover:[&_.ck-toolbar]:!border-black/85',
        'hover:[&_.ck-editor__main>.ck-editor__editable]:!border-black/85',

        error ? '[&_.ck-toolbar]:!border-[#d32f2f]' : '',
        error
            ? '[&_.ck-editor__main>.ck-editor__editable]:!border-[#d32f2f]'
            : '',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <Box className={editorClasses}>
            <FormLabel
                required={required}
                error={error}
                sx={{
                    display: 'block',
                    mb: 1,
                }}
            >
                {label}
            </FormLabel>

            <CKEditor
                editor={ClassicEditor}
                data={value}
                config={{
                    licenseKey: 'GPL',

                    plugins: [
                        Essentials,
                        Autoformat,

                        Paragraph,
                        Heading,

                        Bold,
                        Italic,
                        Underline,
                        Strikethrough,
                        Subscript,
                        Superscript,
                        Code,

                        FontFamily,
                        FontSize,
                        FontColor,
                        FontBackgroundColor,
                        Highlight,

                        Link,

                        List,
                        ListProperties,

                        Alignment,

                        Indent,
                        IndentBlock,

                        BlockQuote,
                        CodeBlock,

                        HorizontalLine,
                        PageBreak,

                        FindAndReplace,

                        SpecialCharacters,
                        SpecialCharactersEssentials,

                        Image,
                        ImageCaption,
                        ImageStyle,
                        ImageToolbar,
                        ImageUpload,
                        ImageInsert,
                        ImageResize,

                        MediaEmbed,

                        Table,
                        TableToolbar,
                        TableCaption,
                        TableProperties,
                        TableCellProperties,
                        TableColumnResize,

                        PasteFromOffice,

                        RemoveFormat,
                        SourceEditing,

                        Fullscreen,
                    ],

                    toolbar: {
                        items: [
                            'undo',
                            'redo',

                            '|',

                            'heading',

                            '|',

                            'fontFamily',
                            'fontSize',
                            'fontColor',
                            'fontBackgroundColor',

                            '|',

                            'bold',
                            'italic',
                            'underline',
                            'strikethrough',

                            '|',

                            'subscript',
                            'superscript',
                            'code',

                            '|',

                            'highlight',
                            'removeFormat',

                            '|',

                            'link',

                            '|',

                            'bulletedList',
                            'numberedList',

                            '|',

                            'outdent',
                            'indent',

                            '|',

                            'alignment',

                            '|',

                            'blockQuote',
                            'codeBlock',

                            '|',

                            'insertImage',
                            'mediaEmbed',
                            'insertTable',

                            '|',

                            'horizontalLine',
                            'pageBreak',

                            '|',

                            'specialCharacters',
                            'findAndReplace',

                            '|',

                            'sourceEditing',
                            'fullscreen',
                        ],

                        shouldNotGroupWhenFull: false,
                    },

                    heading: {
                        options: [
                            {
                                model: 'paragraph',
                                title: 'Paragraph',
                                class: 'ck-heading_paragraph',
                            },
                            {
                                model: 'heading1',
                                view: 'h1',
                                title: 'Heading 1',
                                class: 'ck-heading_heading1',
                            },
                            {
                                model: 'heading2',
                                view: 'h2',
                                title: 'Heading 2',
                                class: 'ck-heading_heading2',
                            },
                            {
                                model: 'heading3',
                                view: 'h3',
                                title: 'Heading 3',
                                class: 'ck-heading_heading3',
                            },
                            {
                                model: 'heading4',
                                view: 'h4',
                                title: 'Heading 4',
                                class: 'ck-heading_heading4',
                            },
                        ],
                    },

                    fontSize: {
                        options: [
                            9,
                            11,
                            13,
                            'default',
                            17,
                            19,
                            21,
                            27,
                            35,
                        ],
                        supportAllValues: true,
                    },

                    fontFamily: {
                        options: [
                            'default',
                            'Arial, Helvetica, sans-serif',
                            'Courier New, Courier, monospace',
                            'Georgia, serif',
                            'Lucida Sans Unicode, Lucida Grande, sans-serif',
                            'Tahoma, Geneva, sans-serif',
                            'Times New Roman, Times, serif',
                            'Trebuchet MS, Helvetica, sans-serif',
                            'Verdana, Geneva, sans-serif',
                        ],
                        supportAllValues: true,
                    },

                    link: {
                        addTargetToExternalLinks: true,
                        defaultProtocol: 'https://',
                    },

                    list: {
                        properties: {
                            styles: true,
                            startIndex: true,
                            reversed: true,
                        },
                    },

                    image: {
                        toolbar: [
                            'toggleImageCaption',
                            'imageTextAlternative',
                            '|',
                            'imageStyle:inline',
                            'imageStyle:wrapText',
                            'imageStyle:breakText',
                            '|',
                            'resizeImage',
                        ],
                    },

                    table: {
                        contentToolbar: [
                            'tableColumn',
                            'tableRow',
                            'mergeTableCells',
                            'tableProperties',
                            'tableCellProperties',
                            'toggleTableCaption',
                        ],
                    },

                    menuBar: {
                        isVisible: true,
                    },
                }}
                onChange={(_event, editor) => {
                    onChange(editor.getData());
                }}
            />

            {helperText && (
                <FormHelperText error={error}>
                    {helperText}
                </FormHelperText>
            )}
        </Box>
    );
}
