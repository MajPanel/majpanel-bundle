import { useEffect, useMemo, useState } from 'react';
import { Autocomplete, CircularProgress, TextField } from '@mui/material';

export type RelationOption = {
    value: string;
    label: string;
};

type RelationOptionsResponse = {
    items?: RelationOption[];
};

type RelationAutocompleteProps = {
    label: string;
    optionsUrl: string;
    labelFields: string[];
    multiple: boolean;
    value: string | string[];
    onChange: (value: string | string[]) => void;
    required?: boolean;
    error?: boolean;
    helperText?: string | null;
};

function fallbackLabel(iri: string): string {
    const part = iri.split('/').filter(Boolean).at(-1);

    return part ? `#${decodeURIComponent(part)}` : iri;
}

export default function RelationAutocomplete({
    label,
    optionsUrl,
    labelFields,
    multiple,
    value,
    onChange,
    required = false,
    error = false,
    helperText = null,
}: RelationAutocompleteProps) {
    const selectedValues = useMemo(
        () => (Array.isArray(value) ? value : value ? [value] : []),
        [value],
    );
    const [options, setOptions] = useState<RelationOption[]>([]);
    const [inputValue, setInputValue] = useState('');
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setLoadError(null);

            const parameters = new URLSearchParams({
                fields: labelFields.join(','),
                page: '1',
            });
            if (inputValue.trim() !== '') {
                parameters.set('q', inputValue.trim());
            }
            selectedValues.forEach((selected) => parameters.append('selected[]', selected));

            try {
                const response = await fetch(`${optionsUrl}?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) {
                    throw new Error(`Unable to load options (${response.status}).`);
                }

                const data = await response.json() as RelationOptionsResponse;
                setOptions(data.items ?? []);
            } catch (caught) {
                if (!(caught instanceof DOMException && caught.name === 'AbortError')) {
                    setLoadError(caught instanceof Error ? caught.message : 'Unable to load relation options.');
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [inputValue, labelFields, optionsUrl, selectedValues]);

    const optionsByValue = new Map(options.map((option) => [option.value, option]));
    const selectedOptions = selectedValues.map(
        (selected) => optionsByValue.get(selected) ?? { value: selected, label: fallbackLabel(selected) },
    );

    return (
        <Autocomplete
            multiple={multiple}
            options={options}
            value={multiple ? selectedOptions : (selectedOptions[0] ?? null)}
            loading={loading}
            inputValue={inputValue}
            filterOptions={(availableOptions) => availableOptions}
            getOptionLabel={(option) => option.label}
            isOptionEqualToValue={(option, selected) => option.value === selected.value}
            onInputChange={(_event, nextInputValue, reason) => {
                if (reason === 'input' || reason === 'clear') {
                    setInputValue(nextInputValue);
                }
            }}
            onChange={(_event, selected) => {
                if (multiple) {
                    const selectedList = selected as RelationOption[];
                    onChange(selectedList.map((option) => option.value));
                } else {
                    const selectedOption = selected as RelationOption | null;
                    onChange(selectedOption?.value ?? '');
                }
            }}
            noOptionsText={inputValue ? 'No matching items' : 'No items'}
            renderInput={(parameters) => (
                <TextField
                    {...parameters}
                    label={label}
                    required={required && !multiple}
                    error={error || loadError !== null}
                    helperText={loadError ?? helperText}
                    slotProps={{
                        ...parameters.slotProps,
                        input: {
                            ...parameters.slotProps.input,
                            endAdornment: (
                                <>
                                    {loading ? <CircularProgress color="inherit" size={20} /> : null}
                                    {parameters.slotProps.input.endAdornment}
                                </>
                            ),
                        },
                    }}
                />
            )}
        />
    );
}
