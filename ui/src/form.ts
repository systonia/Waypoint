/**
 * <form> -> plain object using PHP's own bracket convention ("items[]" appends,
 * "address[city]" nests), so the JSON shape matches what a #[Body] DTO expects.
 */
export function serializeForm(form: HTMLFormElement): Record<string, unknown> {
    const result: Record<string, unknown> = {};
    for (const [rawKey, value] of new FormData(form).entries()) {
        const [first, ...rest] = rawKey.split('[').map((part, i) => (i === 0 ? part : part.replace(']', '')));
        assign(result, first ?? '', rest, value);
    }
    return result;
}

function assign(target: Record<string, unknown>, key: string, rest: string[], value: FormDataEntryValue): void {
    const [next, ...remaining] = rest;
    if (next === undefined) {
        target[key] = value;
    } else if (next === '') {
        const list = Array.isArray(target[key]) ? (target[key] as unknown[]) : (target[key] = []);
        list.push(value);
    } else {
        const existing = target[key];
        const nested =
            typeof existing === 'object' && existing !== null && !Array.isArray(existing)
                ? (existing as Record<string, unknown>)
                : (target[key] = {} as Record<string, unknown>);
        assign(nested, next, remaining, value);
    }
}
