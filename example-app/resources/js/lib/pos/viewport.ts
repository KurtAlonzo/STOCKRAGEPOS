export function getCheckoutViewport(
    width: number,
): 'unsupported' | 'portrait' | 'wide' {
    return width < 768 ? 'unsupported' : width < 1024 ? 'portrait' : 'wide';
}
