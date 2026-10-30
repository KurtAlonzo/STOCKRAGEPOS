export function managementFilters(
    filters: Record<string, string | number | boolean | null | undefined>,
) {
    return Object.fromEntries(
        Object.entries(filters).map(([key, value]) => [
            key,
            value === null
                ? undefined
                : typeof value === 'boolean'
                  ? value
                      ? '1'
                      : '0'
                  : value,
        ]),
    );
}
